<?php

// Inside an uninstall.php in your plugin directory
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('mn_plugin_settings');

// Clear scheduled events
$timestamp = wp_next_scheduled('mn_generate_google_feed_event');
wp_unschedule_event($timestamp, 'mn_generate_google_feed_event');

// Remove generated files
$feed_file_path = WP_content_dir() . '/uploads/mn_google_feed.xml';
if (file_exists($feed_file_path)) {
    unlink($feed_file_path);
}

// Remove all transients
delete_transient('mn_google_feed');
delete_transient('mn_google_reviews_feed');
