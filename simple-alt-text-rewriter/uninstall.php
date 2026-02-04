<?php

/**
 * Fired when the plugin is uninstalled.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('satr_gemini_api_key');
delete_option('satr_api_base_url');
delete_option('satr_custom_prompt');
delete_option('satr_description_prompt');
