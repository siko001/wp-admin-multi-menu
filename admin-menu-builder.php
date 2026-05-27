<?php

/**
 * Plugin Name: WP Admin Multi Menu
 * Plugin URI: https://github.com/siko001/wp-admin-multi-menu
 * Description: Visual admin menu builder with support for deeply nested WordPress admin menu flyouts.
 * Version: 1.0.5
 * Author: Neil VM
 * Author URI: https://neilmallia.com
 * License: GPL v2 or later
 * Text Domain: flexible-admin-nested-menu
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FANM_VERSION', '1.0.5');
define('FANM_FILE', __FILE__);
define('FANM_PATH', plugin_dir_path(__FILE__));
define('FANM_URL', plugin_dir_url(__FILE__));

require_once FANM_PATH . 'includes/class-menu-repository.php';
require_once FANM_PATH . 'includes/class-access-repository.php';
require_once FANM_PATH . 'includes/class-admin-menu-scanner.php';
require_once FANM_PATH . 'includes/class-tree-renderer.php';
require_once FANM_PATH . 'includes/class-assets.php';
require_once FANM_PATH . 'includes/class-woocommerce-compatibility.php';
require_once FANM_PATH . 'src/Support/GitHubPluginUpdater.php';
require_once FANM_PATH . 'includes/class-builder-page.php';
require_once FANM_PATH . 'includes/class-access-page.php';
require_once FANM_PATH . 'includes/class-access-enforcer.php';
require_once FANM_PATH . 'includes/class-ajax-controller.php';
require_once FANM_PATH . 'includes/class-plugin.php';

register_activation_hook(FANM_FILE, ['FANM_Plugin', 'activate']);
register_deactivation_hook(FANM_FILE, ['FANM_Plugin', 'deactivate']);

add_action('plugins_loaded', ['FANM_Plugin', 'init']);
