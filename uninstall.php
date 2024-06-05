<?php

// Inside an uninstall.php in your plugin directory
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('smarty_plugin_settings');

// Clear scheduled events
$timestamp = wp_next_scheduled('smarty_generate_google_feed_event');
wp_unschedule_event($timestamp, 'smarty_generate_google_feed_event');

// Remove generated files
$feed_file_path = WP_content_dir() . '/uploads/smarty_google_feed.xml';
if (file_exists($feed_file_path)) {
    unlink($feed_file_path);
}

// Remove all transients
delete_transient('smarty_google_feed');
delete_transient('smarty_google_reviews_feed');
