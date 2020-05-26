=== VIPS Image Editor ===
Contributors: joppuyo
Tags: vips, image
Requires at least: 4.9.0
Tested up to: 4.9.8
Requires PHP: 7.0.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

High performance WordPress image processing with VIPS

== Changelog ==

= 1.1.0 =
* Feature: vips thumbnail is used instead of resize if vips version is newer than 8.6.0 for faster resizing
* Fix: Fixed issue where error was not handled correctly if target size was larger than image size
* Fix: Disabled vips cache by default since it took up more memory without any performance benefits

= 1.0.3 =
* Fix Bedrock compatibility

= 1.0.2 =
* Add package name to composer.json

= 1.0.1 =
* Add WordPress readme.txt

= 1.0.0 =
* Initial release
