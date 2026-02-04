<?php
/**
 * Plugin Name:       Sejm API
 * Description:       Zadanie rekrutacyjne. Pobiera dane posłów z publicznego API Sejmu i zapisuje je jako Custom Post Type.
 * Version:           1.0.0
 * Author:            Damian Zytner
 * Text Domain:       sejm-api
 */

if (!defined('ABSPATH')) {
	exit;
}

define('SEJM_API_VERSION', '1.0.0');
define('SEJM_API_PATH', plugin_dir_path(__FILE__));
define('SEJM_API_URL', plugin_dir_url(__FILE__));

require_once SEJM_API_PATH . 'includes/class-sejm-api.php';

register_activation_hook(__FILE__, ['Sejm_API', 'activate']);
register_deactivation_hook(__FILE__, ['Sejm_API', 'deactivate']);

add_action('plugin_loaded', ['Sejm_API', 'init']);

