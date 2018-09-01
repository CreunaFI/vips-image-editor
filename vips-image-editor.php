<?php

/*
Plugin Name: Vips image editor
Plugin URI: https://github.com/joppuyo/wp-vips-image-editor
Description: Power WordPress image operations with Vips
Version: 1.0.0
Author: Johannes Siipola
Author URI: https://siipo.la
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

include 'vendor/autoload.php';
include 'class-image-editor-vips.php';

add_action('admin_notices', function() {
    if (!extension_loaded('vips')) {
        echo '<div class="notice notice-warning"><p>';
        echo __("VIPS PHP extension is not loaded. VIPS image editor can't function without it. VIPS editor has been disabled.", 'vips-image-editor');
        echo '</p></div>';
    }
});

add_filter('wp_image_editors', function($image_editors) {
    if (extension_loaded('vips')) {
        array_unshift($image_editors, 'Image_Editor_Vips');
    }
    return $image_editors;
}, 9999);

