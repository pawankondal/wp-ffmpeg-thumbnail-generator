<?php
/*
WP FFmpeg Thumbnail Generator is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
*/

/**
 * Plugin Name: WP FFmpeg Thumbnail Generator
 * Description: Auto-generate video thumbnails using FFmpeg
 * Version: 1.0.0
 * Author: Pawan Kumar
 * License: GPL2+
 * Text Domain: wp-ffmpeg-thumbnail-generator
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Define constants
define('WPFMPEG_VERSION', '1.0.0');
define('WPFMPEG_PATH', plugin_dir_path(__FILE__));
define('WPFMPEG_URL', plugin_dir_url(__FILE__));

// Include files
require_once WPFMPEG_PATH . 'includes/class-ffmpeg-handler.php';
require_once WPFMPEG_PATH . 'includes/admin-settings.php';

// Initialize plugin
function wpfmpeg_init() {
    $handler = new WPFFmpeg_Handler();
    $handler->init();
}
add_action('plugins_loaded', 'wpfmpeg_init');
