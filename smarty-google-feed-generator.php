<?php
/**
 * Plugin Name: Google Feed Generator for WooCommerce
 * Plugin URI:  https://smartystudio.net
 * Description: Generates google product and product review feeds for Google Merchant Center.
 * Version:     1.0.0
 * Author:      Smarty Studio | Martin Nestorov
 * Author URI:  https://smartystudio.net
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Add rewrite rules for custom endpoints.
 */
function smarty_feed_generator_add_rewrite_rules() {
    add_rewrite_rule('^custom-google-feed/?', 'index.php?smarty_google_feed=1', 'top');
    add_rewrite_rule('^custom-google-reviews-feed/?', 'index.php?smarty_google_reviews_feed=1', 'top');
}
add_action('init', 'smarty_feed_generator_add_rewrite_rules');

/**
 * Register query vars for custom endpoints.
 */
function smarty_feed_generator_query_vars($vars) {
    $vars[] = 'smarty_google_feed';
    $vars[] = 'smarty_google_reviews_feed';
    return $vars;
}
add_filter('query_vars', 'smarty_feed_generator_query_vars');

/**
 * Handle requests to custom endpoints.
 */
function smarty_feed_generator_template_redirect() {
    if (get_query_var('smarty_google_feed')) {
        smarty_generate_google_feed();
        exit;
    }
    if (get_query_var('smarty_google_reviews_feed')) {
        smarty_generate_google_reviews_feed();
        exit;
    }
}
add_action('template_redirect', 'smarty_feed_generator_template_redirect');

/**
 * Generates the custom Google product feed.
 */
function smarty_generate_google_feed() {
    header('Content-Type: application/xml; charset=utf-8');

    if (class_exists('WooCommerce')) {
        $args = array(
            'status' => 'publish',
            'limit' => -1, // Consider performance and execution time limits
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $products = wc_get_products($args);
        $xml = new SimpleXMLElement('<rss version="2.0"/>');
        $xml->addAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $channel = $xml->addChild('channel');

        // Dynamically set store information
        $channel->addChild('title', get_bloginfo('name'));
        $channel->addChild('link', get_bloginfo('url'));
        $channel->addChild('description', get_bloginfo('description'));

        foreach ($products as $product) {
            $item = $channel->addChild('item');
            $item->addChild('g:id', $product->get_id(), 'http://base.google.com/ns/1.0');
            $item->addChild('g:title', htmlspecialchars($product->get_name()), 'http://base.google.com/ns/1.0');

            // Description: Choose short or long description based on your needs
            $description = !empty($product->get_short_description()) ? $product->get_short_description() : $product->get_description();
            $item->addChild('g:description', htmlspecialchars(strip_tags($description)), 'http://base.google.com/ns/1.0');

            // Link
            $item->addChild('g:link', get_permalink($product->get_id()), 'http://base.google.com/ns/1.0');
            
            // Main Image
            $item->addChild('g:image_link', wp_get_attachment_url($product->get_image_id()), 'http://base.google.com/ns/1.0');

            // Additional Images (Gallery)
            $gallery_ids = $product->get_gallery_image_ids();
            foreach ($gallery_ids as $gallery_id) {
                $item->addChild('g:additional_image_link', wp_get_attachment_url($gallery_id), 'http://base.google.com/ns/1.0');
            }

            // Price and Sale Price
            if ($product->is_on_sale()) {
                $item->addChild('g:price', $product->get_regular_price() . ' ' . get_woocommerce_currency(), 'http://base.google.com/ns/1.0');
                $item->addChild('g:sale_price', $product->get_sale_price() . ' ' . get_woocommerce_currency(), 'http://base.google.com/ns/1.0');
            } else {
                $item->addChild('g:price', $product->get_price() . ' ' . get_woocommerce_currency(), 'http://base.google.com/ns/1.0');
            }

            // Product Type (Category)
            $categories = wp_get_post_terms($product->get_id(), 'product_cat');
            if (!empty($categories) && !is_wp_error($categories)) {
                $category_names = array_map(function($term) {
                    return $term->name;
                }, $categories);
                $item->addChild('g:product_type', htmlspecialchars(join(' > ', $category_names)), 'http://base.google.com/ns/1.0');
            }

            // SKU
            $item->addChild('g:sku', $product->get_sku(), 'http://base.google.com/ns/1.0');

            // Add more product attributes as needed
        }

        echo $xml->asXML();
        exit; // Ensure no further output is sent
    } else {
        error_log('WooCommerce is not active');
        echo '<error>WooCommerce is not active.</error>';
        exit;
    }
}

/**
 * Generates the custom Google product review feed.
 */
function smarty_generate_google_reviews_feed() {
    header('Content-Type: application/xml; charset=utf-8');

    $args = array(
        'post_type' => 'product',
        'numberposts' => -1, // Adjust based on performance
    );
    $products = get_posts($args);

    $xml = new SimpleXMLElement('<feed xmlns:g="http://base.google.com/ns/1.0"/>');

    foreach ($products as $product) {
        $reviews = get_comments(array('post_id' => $product->ID));
        foreach ($reviews as $review) {
            if ($review->comment_approved == '1') { // Only show approved reviews
                $entry = $xml->addChild('entry');
                $entry->addChild('g:id', htmlspecialchars(get_the_product_sku($product->ID)), 'http://base.google.com/ns/1.0');
                $entry->addChild('g:title', htmlspecialchars($product->post_title), 'http://base.google.com/ns/1.0');
                $entry->addChild('g:content', htmlspecialchars($review->comment_content), 'http://base.google.com/ns/1.0');
                $entry->addChild('g:reviewer', htmlspecialchars($review->comment_author), 'http://base.google.com/ns/1.0');
                $entry->addChild('g:review_date', date('Y-m-d', strtotime($review->comment_date)), 'http://base.google.com/ns/1.0');
                $entry->addChild('g:rating', get_comment_meta($review->comment_ID, 'rating', true), 'http://base.google.com/ns/1.0');
                // Add more fields as required by Google
            }
        }
    }

    echo $xml->asXML();
    exit;
}

/**
 * Helper function to get the product SKU.
 * Adjust if your SKU is stored differently.
 */
function get_the_product_sku($product_id) {
    return get_post_meta($product_id, '_sku', true);
}

/**
 * Activation hook to flush rewrite rules on plugin activation.
 */
function smarty_feed_generator_activate() {
    smarty_feed_generator_add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'smarty_feed_generator_activate');

/**
 * Deactivation hook to flush rewrite rules on plugin deactivation.
 */
function smarty_feed_generator_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'smarty_feed_generator_deactivate');
