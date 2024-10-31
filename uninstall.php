<?php
// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Cleanup code here
delete_option('sales_health_monitor_secret_token');
delete_option('sales_health_monitor_settings');