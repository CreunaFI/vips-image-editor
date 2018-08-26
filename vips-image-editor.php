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

add_filter('wp_image_editors', function($image_editors) {
    array_unshift($image_editors, 'Image_Editor_Vips');
    return $image_editors;
}, 9999);

