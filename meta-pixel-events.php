<?php
/**
 * Plugin Name: Meta Pixel Event Tracker (Custom)
 * Description: Tracks AddToCart and AddToWishlist events properly using Meta Pixel
 * Version: 1.0
 * Author: Throughout 
 * Plugin URI: https://www.throughout.io
 * Update URI: https://github.com/ThroooutProjects/wp-meta-pixel-events
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MPE_VERSION', '1.0');
define('MPE_PIXEL_ID', '1799375824327124');
define('MPE_PLUGIN_FILE', __FILE__);
define('MPE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MPE_PLUGIN_URL', plugin_dir_url(__FILE__));

$mpe_autoload = MPE_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($mpe_autoload)) {
    require_once $mpe_autoload;
}

require_once MPE_PLUGIN_DIR . 'includes/helpers.php';
require_once MPE_PLUGIN_DIR . 'includes/capi.php';
require_once MPE_PLUGIN_DIR . 'includes/pixel.php';
require_once MPE_PLUGIN_DIR . 'includes/updater.php';
require_once MPE_PLUGIN_DIR . 'includes/woocommerce.php';
require_once MPE_PLUGIN_DIR . 'includes/frontend.php';

if (is_admin()) {
    require_once MPE_PLUGIN_DIR . 'includes/admin-log.php';
    require_once MPE_PLUGIN_DIR . 'includes/admin-settings.php';
}
