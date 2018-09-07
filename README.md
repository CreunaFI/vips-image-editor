# VIPS Image Editor

[![Build Status](https://travis-ci.com/CreunaFI/vips-image-editor.svg?branch=master)](https://travis-ci.com/CreunaFI/vips-image-editor) [![Packagist](https://img.shields.io/packagist/v/joppuyo/vips-image-editor.svg)](https://packagist.org/packages/joppuyo/vips-image-editor)

High performance WordPress image processing with [VIPS](https://jcupitt.github.io/libvips/).

## Requirements

* PHP 7 or later
* vips package installed on your Linux system
* vips PHP extension

## Installation
 
1. Install vips on your system. On Ubuntu, this can be done using `apt install libvips-dev`
2. Install vips extension using [pecl](https://pecl.php.net/), this can be done with command `pecl install vips`
3. Enable PHP extension, this is usually done by adding the line `extension=vips.so` in `php.ini`
4. Download the latest plugin version from the [releases tab](https://github.com/CreunaFI/vips-image-editor/releases)
5. Extract the plugin under `wp-content/plugins`
6. Enable the plugin in WordPress admin interface
