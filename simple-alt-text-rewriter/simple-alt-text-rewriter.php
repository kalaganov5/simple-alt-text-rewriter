<?php

/**
 * Plugin Name: Simple Alt Text Rewriter
 * Description: Generates SEO-optimized alt text using article context.
 * Version: 1.0.4
 * Author: Vladimir Kalaganov
 * Author URI: https://kalaganov5.com/
 * Plugin URI: https://github.com/kalaganov5/simple-alt-text-rewriter
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-alt-text-rewriter
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SATR_PATH', plugin_dir_path(__FILE__));
define('SATR_URL', plugin_dir_url(__FILE__));

require_once SATR_PATH . 'includes/class-api-client.php';
require_once SATR_PATH . 'includes/class-context-finder.php';
require_once SATR_PATH . 'includes/class-admin-ui.php';

// Initialize the plugin
function satr_init()
{
    $api_client = new SATR_Api_Client();
    $context_finder = new SATR_Context_Finder();
    new SATR_Admin_UI($api_client, $context_finder);
}
add_action('plugins_loaded', 'satr_init');
