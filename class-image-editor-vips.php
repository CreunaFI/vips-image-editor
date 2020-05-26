<?php

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';

class Image_Editor_Vips extends \WP_Image_Editor
{
    /**
     * VIPS resource.
     *
     * @var resource
     */
    protected $image;
    protected $debug = false;


    public function __construct($file)
    {
        parent::__construct($file);
        if (apply_filters('vips_ie_enable_cache', true) === false) {
            Jcupitt\Vips\Config::cacheSetMax(0);
        }
    }

    /**
     * Checks to see if current environment supports VIPS.
     *
     * @since 3.5.0
     *
     * @static
     *
     * @param array $args
     * @return bool
     */
    public static function test($args = [])
    {
        return true;
    }

    private static function debug(...$messages) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log(print_r($messages, true));
        }
    }

    /**
     * Checks to see if editor supports the mime-type specified.
     *
     * @since 3.5.0
     *
     * @static
     *
     * @param string $mime_type
     * @return bool
     */
    public static function supports_mime_type($mime_type)
    {
        switch ($mime_type) {
            case 'image/jpeg':
                return true;
            case 'image/png':
                return true;
        }
        return false;
    }

    /**
     * Loads image from $this->file into new VIPS Resource.
     *
     * @since 3.5.0
     *
     * @return bool|WP_Error True if loaded successfully; WP_Error on failure.
     */
    public function load()
    {
        if ($this->image)
            return true;

        if (!is_file($this->file)) {
            return new WP_Error('error_loading_image', __('File doesn&#8217;t exist?'), $this->file);
        }

        // Increase memory
        wp_raise_memory_limit('image');

        try {
            $this->image = Jcupitt\Vips\Image::newFromFile($this->file);
            $this->update_size($this->image->width, $this->image->height);
            $this->mime_type = mime_content_type($this->file);

            return $this->set_quality();
        } catch (Exception $exception) {
            return new WP_Error('image_load_error', __('Failed to load image.'), $exception);
        }
    }

    /**
     * Sets or updates current image size.
     *
     * @since 3.5.0
     *
     * @param int $width
     * @param int $height
     * @return true
     */
    protected function update_size($width = false, $height = false)
    {
        if (!$width) {
            $width = $this->image->width;
        }

        if (!$height) {
            $height = $this->image->height;
        }

        return parent::update_size($width, $height);
    }

    /**
     * Resizes current image.
     * Wraps _resize, since _resize returns a VIPS Resource.
     *
     * At minimum, either a height or width must be provided.
     * If one of the two is set to null, the resize will
     * maintain aspect ratio according to the provided dimension.
     *
     * @since 3.5.0
     *
     * @param  int|null $max_w Image width.
     * @param  int|null $max_h Image height.
     * @param  bool $crop
     * @return true|WP_Error
     */
    public function resize($max_w, $max_h, $crop = false)
    {
        if (($this->size['width'] == $max_w) && ($this->size['height'] == $max_h)) {
            return true;
        }
        try {
            $resized = $this->_resize($max_w, $max_h, $crop);
            $this->image = $resized;
            return true;
        } catch (Exception $exception) {
            return new WP_Error('failed_to_crop', __('Failed to crop image'), $exception);
        }
    }

    /**
     *
     * @param int $max_w
     * @param int $max_h
     * @param bool|array $crop
     * @return resource|WP_Error
     */
    protected function _resize($max_w, $max_h, $crop = false)
    {
        $dims = image_resize_dimensions($this->size['width'], $this->size['height'], $max_w, $max_h, $crop);
        if (!$dims) {
            return new WP_Error('error_getting_dimensions', __('Could not calculate resized image dimensions'), $this->file);
        }
        list($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = $dims;

        try {

            $resized = null;

            if (version_compare(vips_version(), '8.6.0', '>=') && apply_filters('vips_ie_thumbnail', true) === true) {
                // Vips thumbnail() is faster than resizing, let's use it if it's available

                $scale = max($this->size['width'] / $src_w, $this->size['height'] / $src_h);

                $target_w = ceil($dst_w * $scale);
                $target_h = ceil($dst_h * $scale);

                $resized = $this->image->thumbnail_image($target_w, [
                    'height' => $target_h,
                ]);
            } else {
                $resized = $this->image->resize(max($dst_h / $src_h, $dst_w / $src_w));
            }

            $scale = max($dst_w / $src_w, $dst_h / $src_h);

            $dst_x_scaled = floor($src_x * $scale);
            $dst_y_scaled = floor($src_y * $scale);

            $cropped = $resized->crop($dst_x_scaled, $dst_y_scaled, $dst_w, $dst_h);

            $this->update_size($dst_w, $dst_h);
            return $cropped;
        } catch (Exception $exception) {
            return new WP_Error('crop_error', __('Failed to crop image'), $exception);
        }
    }

    /**
     * Resize multiple images from a single source.
     *
     * @since 3.5.0
     *
     * @param array $sizes {
     *     An array of image size arrays. Default sizes are 'small', 'medium', 'medium_large', 'large'.
     *
     *     Either a height or width must be provided.
     *     If one of the two is set to null, the resize will
     *     maintain aspect ratio according to the provided dimension.
     *
     * @type array $size {
     *         Array of height, width values, and whether to crop.
     *
     * @type int $width Image width. Optional if `$height` is specified.
     * @type int $height Image height. Optional if `$width` is specified.
     * @type bool $crop Optional. Whether to crop the image. Default false.
     *     }
     * }
     * @return array An array of resized images' metadata by size.
     */
    public function multi_resize($sizes)
    {
        $metadata = [];
        $orig_size = $this->size;

        foreach ($sizes as $size => $size_data) {
            if (!isset($size_data['width']) && !isset($size_data['height'])) {
                continue;
            }

            if (!isset($size_data['width'])) {
                $size_data['width'] = null;
            }
            if (!isset($size_data['height'])) {
                $size_data['height'] = null;
            }

            if (!isset($size_data['crop'])) {
                $size_data['crop'] = false;
            }

            $image = $this->_resize($size_data['width'], $size_data['height'], $size_data['crop']);
            $duplicate = (($orig_size['width'] == $size_data['width']) && ($orig_size['height'] == $size_data['height']));

            if (!is_wp_error($image) && !$duplicate) {
                $resized = $this->_save($image);
                if (!is_wp_error($resized) && $resized) {
                    unset($resized['path']);
                    $metadata[$size] = $resized;
                }
            }

            $this->size = $orig_size;
        }

        return $metadata;
    }

    /**
     * Crops Image.
     *
     * @since 3.5.0
     *
     * @param int $src_x The start x position to crop from.
     * @param int $src_y The start y position to crop from.
     * @param int $src_w The width to crop.
     * @param int $src_h The height to crop.
     * @param int $dst_w Optional. The destination width.
     * @param int $dst_h Optional. The destination height.
     * @param bool $src_abs Optional. If the source crop points are absolute.
     * @return bool|WP_Error
     */
    public function crop($src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false)
    {
        // If destination width/height isn't specified, use same as
        // width/height from source.
        if (!$dst_w)
            $dst_w = $src_w;
        if (!$dst_h)
            $dst_h = $src_h;

        if ($src_abs) {
            $src_w -= $src_x;
            $src_h -= $src_y;
        }

        try {
            $this->image = $this->image->crop($src_x, $src_y, $src_w, $src_h);
            $this->update_size();
            return true;
        } catch (Exception $exception) {
            return new WP_Error('image_crop_error', __('Image crop failed.'), $exception);
        }
    }

    /**
     * Rotates current image counter-clockwise by $angle.
     * Ported from image-edit.php
     *
     * @since 3.5.0
     *
     * @param float $angle
     * @return true|WP_Error
     */
    public function rotate($angle)
    {
        try {
            // Angle is counter clockwise because WordPress is strange
            $angle = -$angle;
            // Modulo magic
            $angle = (360 + ($angle % 360)) % 360;
            if ($angle === 90) {
                $this->image = $this->image->rot90();
            } else if ($angle === 180) {
                $this->image = $this->image->rot180();
            } else if ($angle === 270) {
                $this->image = $this->image->rot270();
            }
            $this->update_size();
            return true;
        } catch (Exception $exception) {
            return new WP_Error('image_rotate_error', __('Image rotate failed.'), $exception);
        }
    }

    /**
     * Flips current image.
     *
     * @since 3.5.0
     *
     * @param bool $horz Flip along Horizontal Axis
     * @param bool $vert Flip along Vertical Axis
     * @return true|WP_Error
     */
    public function flip($horz, $vert)
    {
        try {
            if ($vert) {
                $this->image = $this->image->fliphor();
            }
            if ($horz) {
                $this->image = $this->image->flipver();
            }
            return true;
        } catch (Exception $exception) {
            return new WP_Error('image_flip_Error', __('Failed to flip image.'), $exception);
        }
    }

    /**
     * Saves current in-memory image to file.
     *
     * @since 3.5.0
     *
     * @param string|null $filename
     * @param string|null $mime_type
     * @return array|WP_Error {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string}
     */
    public function save($filename = null, $mime_type = null)
    {
        $saved = $this->_save($this->image, $filename, $mime_type);

        if (!is_wp_error($saved)) {
            $this->file = $saved['path'];
            $this->mime_type = $saved['mime-type'];
        }

        return $saved;
    }

    /**
     * @param resource $image
     * @param string|null $filename
     * @param string|null $mime_type
     * @return WP_Error|array
     */
    protected function _save($image, $filename = null, $mime_type = null)
    {
        list($filename, $extension, $mime_type) = $this->get_output_format($filename, $mime_type);

        if (!$filename) {
            $filename = $this->generate_filename(null, null, $extension);
        }

        $parameters = [];

        if ($mime_type === 'image/jpeg') {

            $interlace = apply_filters('vips_ie_interlace', false);
            $optimize_coding = apply_filters('vips_ie_optimize_coding', false);
            $trellis_quant = apply_filters('vips_ie_trellis_quant', false);
            $overshoot_deringing = apply_filters('vips_ie_overshoot_deringing', false);
            $optimize_scans = apply_filters('vips_ie_optimize_scans', false);

            $parameters = [
                'Q' => $this->get_quality(),
                'interlace' => $interlace,
                'trellis_quant' => $trellis_quant,
                'overshoot_deringing' => $overshoot_deringing,
                'optimize_scans' => $optimize_scans,
            ];
        }

        try {

            // Check directory, vips does not create folders for us
            $directory = dirname($filename);
            if (!is_dir($directory)) {
                mkdir($directory);
            }

            if (is_wp_error($image)) {
                throw new Exception('Image is a WP_Error');
            }

            $image->writeToFile($filename, $parameters);
            // Set correct file permissions
            $stat = stat(dirname($filename));
            $perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
            @ chmod($filename, $perms);
            /**
             * Filters the name of the saved image file.
             *
             * @since 2.6.0
             *
             * @param string $filename Name of the file.
             */
            return [
                'path' => $filename,
                'file' => wp_basename(apply_filters('image_make_intermediate_size', $filename)),
                'width' => $this->size['width'],
                'height' => $this->size['height'],
                'mime-type' => $mime_type,
            ];
        } catch (Exception $exception) {
            return new WP_Error('image_save_error', 'Failed to save image', $exception);
        }
    }

    /**
     * Returns stream of current image.
     *
     * @since 3.5.0
     *
     * @param string $mime_type The mime type of the image.
     * @return bool True on success, false on failure.
     */
    public function stream($mime_type = null)
    {
        list($filename, $extension, $mime_type) = $this->get_output_format(null, $mime_type);

        switch ($mime_type) {
            case 'image/png':
                header('Content-Type: image/png');
                echo $this->image->writeToBuffer('.png');
                return true;
            case 'image/jpeg':
                header('Content-Type: image/jpeg');
                echo $this->image->writeToBuffer('.jpg', [
                    'Q' => $this->get_quality()
                ]);
                return true;
        }
        return false;
    }
}
