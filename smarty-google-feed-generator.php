<?php
/**
 * Plugin Name: SM - Google Feed Generator for WooCommerce
 * Plugin URI:  https://smartystudio.net/google-feed-generator
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

if (!function_exists('smarty_feed_generator_add_rewrite_rules')) {
    /**
     * Add rewrite rules for custom endpoints.
     */
    function smarty_feed_generator_add_rewrite_rules() {
        add_rewrite_rule('^smarty-google-feed/?', 'index.php?smarty_google_feed=1', 'top');                 // url: ?smarty-google-feed
        add_rewrite_rule('^smarty-google-reviews-feed/?', 'index.php?smarty_google_reviews_feed=1', 'top'); // url: ?smarty-google-reviews-feed
        add_rewrite_rule('^smarty-csv-export/?', 'index.php?smarty_csv_export=1', 'top');                   // url: ?smarty-csv-export
    }
    add_action('init', 'smarty_feed_generator_add_rewrite_rules');
}

if (!function_exists('smarty_feed_generator_query_vars')) {
    /**
     * Register query vars for custom endpoints.
     */
    function smarty_feed_generator_query_vars($vars) {
        $vars[] = 'smarty_google_feed';
        $vars[] = 'smarty_google_reviews_feed';
        $vars[] = 'smarty_csv_export';
        return $vars;
    }
    add_filter('query_vars', 'smarty_feed_generator_query_vars');
}

if (!function_exists('smarty_feed_generator_template_redirect')) {
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

        if (get_query_var('smarty_csv_export')) {
            smarty_generate_csv_export();
            exit;
        }
    }
    add_action('template_redirect', 'smarty_feed_generator_template_redirect');
}

if (!function_exists('smarty_generate_google_feed')) {
    /**
     * Generates the custom Google product feed.
     */
    function smarty_generate_google_feed() {
        header('Content-Type: application/xml; charset=utf-8');

        // Force clear the transient for testing purposes
        delete_transient('smarty_google_feed');  // Remove this line in production once confirmed working
    
        // Check for a cached version first
        $cached_feed = get_transient('smarty_google_feed');
        if ($cached_feed !== false) {
            echo $cached_feed;
            exit;
        }
    
        if (class_exists('WooCommerce')) {
            $args = array(
                'status' => 'publish',
                'stock_status' => 'instock', // Only in-stock products
                'limit' => -1, // Consider performance and execution time limits
                'orderby' => 'date',
                'order' => 'DESC',
                'type' => ['simple', 'variable'], // Include both simple and variable products
            );
    
            $products = wc_get_products($args);
            $xml = new SimpleXMLElement('<feed xmlns:g="http://base.google.com/ns/1.0"/>');
    
            foreach ($products as $product) {
                if ($product->is_type('variable')) {
                     $variations = $product->get_children();
                    if (!empty($variations)) {
                        $first_variation_id = $variations[0]; // Get the first variation
                        $variation = wc_get_product($first_variation_id);
                        $item = $xml->addChild('item');
                        $gNamespace = 'http://base.google.com/ns/1.0';
        
                        // Include both product ID and SKU for identification
                        $item->addChild('g:id', $variation->get_id(), $gNamespace);
                        $item->addChild('g:sku', $variation->get_sku(), $gNamespace);
                        $item->addChild('title', htmlspecialchars($product->get_name()), $gNamespace);
                        $item->addChild('link', get_permalink($product->get_id()), $gNamespace);
        
                        // Handling description with fallback to short description
                        $meta_description = get_post_meta($product->get_id(), 'veni-description', true);
                        $description = !empty($meta_description) ? $meta_description : $product->get_short_description();
                        $item->addChild('description', htmlspecialchars(strip_tags($description)), $gNamespace);

                        // Variation specific image
                        $image_id = $variation->get_image_id() ? $variation->get_image_id() : $product->get_image_id();
                        $item->addChild('image_link', wp_get_attachment_url($image_id), $gNamespace);

                        // Additional Images from the parent product
                        $gallery_ids = $product->get_gallery_image_ids();
                        
                        foreach ($gallery_ids as $gallery_id) {
                            $item->addChild('additional_image_link', wp_get_attachment_url($gallery_id), $gNamespace);
                        }
        
                        // Price
                        $item->addChild('price', htmlspecialchars($variation->get_regular_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                        if ($variation->is_on_sale()) {
                            $item->addChild('sale_price', htmlspecialchars($variation->get_sale_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                        }
        
                        // Categories
                        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
                        if (!empty($categories) && !is_wp_error($categories)) {
                            $category_names = array_map(function($term) { return $term->name; }, $categories);
                            $item->addChild('product_type', htmlspecialchars(join(' > ', $category_names)), $gNamespace);
                        }
                    }
                } else {
                    // Simple products are handled similarly
                    $item = $xml->addChild('item');
                    $gNamespace = 'http://base.google.com/ns/1.0';
                    $item->addChild('g:id', $product->get_id(), $gNamespace);
                    $item->addChild('g:sku', $product->get_sku(), $gNamespace);
                    $item->addChild('title', htmlspecialchars($product->get_name()), $gNamespace);
                    $item->addChild('link', get_permalink($product->get_id()), $gNamespace);
                    $description = $product->get_description();
                    $description = empty($description) ? $product->get_short_description() : $description;
                    $item->addChild('description', htmlspecialchars(strip_tags($description)), $gNamespace);
                    $item->addChild('image_link', wp_get_attachment_url($product->get_image_id()), $gNamespace);
                    $item->addChild('price', htmlspecialchars($product->get_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                    if ($product->is_on_sale()) {
                        $item->addChild('sale_price', htmlspecialchars($product->get_sale_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                    }
                    $categories = wp_get_post_terms($product->get_id(), 'product_cat');
                    if (!empty($categories) && !is_wp_error($categories)) {
                        $category_names = array_map(function($term) { return $term->name; }, $categories);
                        $item->addChild('product_type', htmlspecialchars(join(' > ', $category_names)), $gNamespace);
                    }
                }
            }
    
            // Save and output the XML
            $feed_content = $xml->asXML();
            set_transient('smarty_google_feed', $feed_content, 12 * HOUR_IN_SECONDS);
            echo $feed_content;
            exit;
        } else {
            echo '<error>WooCommerce is not active.</error>';
            exit;
        }
    }
}

if (!function_exists('smarty_generate_google_reviews_feed')) {
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
            $gNamespace = 'http://base.google.com/ns/1.0';

            foreach ($reviews as $review) {
                if ($review->comment_approved == '1') { // Only show approved reviews
                    $entry = $xml->addChild('entry');
                    $entry->addChild('g:id', htmlspecialchars(get_the_product_sku($product->ID)), $gNamespace);
                    $entry->addChild('g:title', htmlspecialchars($product->post_title), $gNamespace);
                    $entry->addChild('g:content', htmlspecialchars($review->comment_content), $gNamespace);
                    $entry->addChild('g:reviewer', htmlspecialchars($review->comment_author), $gNamespace);
                    $entry->addChild('g:review_date', date('Y-m-d', strtotime($review->comment_date)), $gNamespace);
                    $entry->addChild('g:rating', get_comment_meta($review->comment_ID, 'rating', true), $gNamespace);
                    // Add more fields as required by Google
                }
            }
        }

        echo $xml->asXML();
        exit;
    }
}

if (!function_exists('smarty_generate_csv_export')) {
    function smarty_generate_csv_export() {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="woocommerce-products.csv"');
    
        // Open output stream directly for download
        $handle = fopen('php://output', 'w');
        if ($handle === false) {
            wp_die('Failed to open output stream for CSV export');
        }
    
        // Define the header row of the CSV
        $headers = array(
            'ID', // Product ID
            'ID2', // SKU
            'Final URL',
            'Final Mobile URL',
            'Image URL',
            'Item Title',
            'Item Description',
            'Item Category',
            'Price',
            'Sale Price',
            'Google Product Category',
            'Is Bundle',
            'MPN',
            'Availability',
            'Condition',
            'Brand'
        );
    
        fputcsv($handle, $headers);
    
        $args = array(
            'status' => 'publish',
            'stock_status' => 'instock', // Only in-stock products
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'type' => array('simple', 'variable'),
            'return' => 'objects',
        );
    
        $products = wc_get_products($args);
        $exclude_patterns = ['-fb', '-2', '-copy', '-digital-edition', '-plan', '-gift'];
    
        foreach ($products as $product) {
            $product_link = get_permalink($product->get_id());
    
            // Skip product if URL contains any excluded patterns
            foreach ($exclude_patterns as $pattern) {
                if (strpos($product_link, $pattern) !== false) {
                    continue 2; // Skip this product
                }
            }
    
            $id = $product->get_id();
            $name = $product->get_name();
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price() ?: '';
            $categories = wp_get_post_terms($id, 'product_cat', array('fields' => 'names'));
            $categories = !empty($categories) ? implode(', ', $categories) : '';
            $image_id = $product->get_image_id();
            $image_link = $image_id ? wp_get_attachment_url($image_id) : '';

            // Fetch the custom field for the description
            $meta_description = get_post_meta($id, 'veni-description', true);
            $description = !empty($meta_description) ? htmlspecialchars(strip_tags($meta_description)) : htmlspecialchars(strip_tags($product->get_short_description()));
            $description = preg_replace('/\s+/', ' ', $description);
            
            $availability = $product->is_in_stock() ? 'in stock' : 'out of stock';
            $google_product_category = 'Health & Beauty, Health Care, Fitness & Nutrition, Vitamins & Supplements';
            $brand = get_bloginfo('name');
    
            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                if (!empty($variations)) {
                    $first_variation_id = reset($variations); // Get only the first variation
                    $variation = wc_get_product($first_variation_id);
                    $sku = $variation->get_sku();
                    $variation_image = wp_get_attachment_url($variation->get_image_id());
                    $variation_price = $variation->get_regular_price();
                    $variation_sale_price = $variation->get_sale_price() ?: '';
    
                    $row = array(
                        'ID' => $id,
                        'ID2' => $sku,
                        'Final URL' => $product_link,
                        'Final Mobile URL' => $product_link,
                        'Image URL' => $variation_image,
                        'Item Title' => $name,
                        'Item Description' => $description,
                        'Item Category' => $categories,
                        'Price' => $variation_price,
                        'Sale Price' => $variation_sale_price,
                        'Google Product Category' => $google_product_category,
                        'Is Bundle' => 'no',
                        'MPN' => $sku,
                        'Availability' => $availability,
                        'Condition' => 'New',
                        'Brand' => $brand,
                    );
                }
            } else {
                $sku = $product->get_sku();
                $row = array(
                    'ID' => $id,
                    'ID2' => $sku,
                    'Final URL' => $product_link,
                    'Final Mobile URL' => $product_link,
                    'Image URL' => $image_link,
                    'Item Title' => $name,
                    'Item Description' => $description,
                    'Item Category' => $categories,
                    'Price' => $regular_price,
                    'Sale Price' => $sale_price,
                    'Google Product Category' => $google_product_category,
                    'Is Bundle' => 'no',
                    'MPN' => $sku,
                    'Availability' => $availability,
                    'Condition' => 'New',
                    'Brand' => $brand,
                );
            }
    
            // Only output the row if the SKU is set (some products may not have variations correctly set)
            if (!empty($sku)) {
                fputcsv($handle, $row);
            }
        }
    
        fclose($handle);
        exit;
    }
}

if (!function_exists('get_the_product_sku')) {
    /**
     * Helper function to get the product SKU.
     * Adjust if your SKU is stored differently.
     */
    function get_the_product_sku($product_id) {
        return get_post_meta($product_id, '_sku', true);
    }
}

if (!function_exists('smarty_invalidate_feed_cache')) {
    /**
     * Invalidate cache or regenerate feed when a product is created, updated, or deleted.
     */
    function smarty_invalidate_feed_cache($product_id) {
        // Check if the post is a 'product'
        if (get_post_type($product_id) === 'product') {
            // Invalidate cache
			delete_transient('smarty_google_feed');
			// Regenerate the feed
			smarty_regenerate_feed();
        }
    }
    add_action('woocommerce_new_product', 'smarty_invalidate_feed_cache');
    add_action('woocommerce_update_product', 'smarty_invalidate_feed_cache');
}

if (!function_exists('smarty_invalidate_feed_cache_on_delete')) {
    function smarty_invalidate_feed_cache_on_delete($post_id) {
        if (get_post_type($post_id) === 'product') {
            // Invalidate cache
            delete_transient('smarty_google_feed');
            // Optionally, regenerate the feed file
            smarty_regenerate_feed();
        }
    }
    add_action('before_delete_post', 'smarty_invalidate_feed_cache_on_delete');
}

if (!function_exists('smarty_invalidate_review_feed_cache')) {
    /**
     * Invalidate cache or regenerate review feed when reviews are added, updated, or deleted.
     */
    function smarty_invalidate_review_feed_cache($comment_id, $comment_approved = '') {
        $comment = get_comment($comment_id);
        $post_id = $comment->comment_post_ID;
        
        // Check if the comment is for a 'product' and approved
        if (get_post_type($post_id) === 'product' && ($comment_approved == 1 || $comment_approved == 'approve')) {
            // Invalidate cache
            delete_transient('smarty_google_reviews_feed');
            // Optionally, regenerate the feed file
            // smarty_regenerate_google_reviews_feed();
        }
    }
    add_action('comment_post', 'smarty_invalidate_review_feed_cache', 10, 2);
    add_action('edit_comment', 'smarty_invalidate_review_feed_cache');
    add_action('deleted_comment', 'smarty_invalidate_review_feed_cache');
    add_action('wp_set_comment_status', 'smarty_invalidate_review_feed_cache');
}

if (!function_exists('smarty_regenerate_feed')) {
    /**
     * Regenerates the feed and saves it to a transient or a file.
     */
	function smarty_regenerate_feed() {
		$products = wc_get_products(array(
			'status' => 'publish',
            'stock_status' => 'instock',
			'limit' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
		));

		$xml = new SimpleXMLElement('<feed xmlns:g="http://base.google.com/ns/1.0"/>');
		// Add feed details and loop through products to add them to the feed...

		foreach ($products as $product) {
            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $child_id) {
                    $variation = wc_get_product($child_id);
                    $item = $xml->addChild('item');
                    $gNamespace = 'http://base.google.com/ns/1.0';

                    // Include both product ID and SKU for identification
                    $item->addChild('g:id', $variation->get_id(), $gNamespace);
                    $item->addChild('g:sku', $variation->get_sku(), $gNamespace);
                    $item->addChild('title', htmlspecialchars($product->get_name()), $gNamespace);
                    $item->addChild('link', get_permalink($product->get_id()), $gNamespace);
                    
                    // Handle description
                    $description = $product->get_description();
                    
                    if (empty($description)) {
                        // Fallback to short description if main description is empty
                        $description = $product->get_short_description();
                    }

                    if (!empty($description)) {
                        // Ensure that HTML tags are removed and properly encoded
                        $item->addChild('description', htmlspecialchars(strip_tags($description)), $gNamespace);
                    } else {
                        $item->addChild('description', 'No description available', $gNamespace);
                    }

                    $item->addChild('image_link', wp_get_attachment_url($product->get_image_id()), $gNamespace);

                    // Variation specific image, if different from the main product image
                    $image_id = $variation->get_image_id() ? $variation->get_image_id() : $product->get_image_id();
                    $variationImageURL = wp_get_attachment_url($variation->get_image_id());
                    $mainImageURL = wp_get_attachment_url($product->get_image_id());

                    if ($variationImageURL !== $mainImageURL) {
                        $item->addChild('image_link', wp_get_attachment_url($image_id), $gNamespace);
                    }

                    // Additional Images from the parent product
                    $gallery_ids = $product->get_gallery_image_ids();
                    
                    foreach ($gallery_ids as $gallery_id) {
                        $item->addChild('additional_image_link', wp_get_attachment_url($gallery_id), $gNamespace);
                    }

                    // Price
                    $item->addChild('price', htmlspecialchars($variation->get_regular_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                    
                    // Handling sale price for variations
                    if ($variation->is_on_sale()) {
                        $item->addChild('sale_price', htmlspecialchars($variation->get_sale_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                    }

                    // Category handling remains similar
                    $categories = wp_get_post_terms($product->get_id(), 'product_cat');
                    
                    if (!empty($categories) && !is_wp_error($categories)) {
                        $category_names = array_map(function($term) { return $term->name; }, $categories);
                        $item->addChild('product_type', htmlspecialchars(join(' > ', $category_names)), $gNamespace);
                    }
                }
            } else {
                // Handle simple products
                $item = $xml->addChild('item');
                $gNamespace = 'http://base.google.com/ns/1.0';
                $item->addChild('g:id', $product->get_id(), $gNamespace);
                $item->addChild('g:sku', $product->get_sku(), $gNamespace);
                $item->addChild('title', htmlspecialchars($product->get_name()), $gNamespace);
                $item->addChild('link', get_permalink($product->get_id()), $gNamespace);

                // Handle description
                $description = $product->get_description();
                
                if (empty($description)) {
                    // Fallback to short description if main description is empty
                    $description = $product->get_short_description();
                }

                if (!empty($description)) {
                    // Ensure that HTML tags are removed and properly encoded
                    $item->addChild('description', htmlspecialchars(strip_tags($description)), $gNamespace);
                } else {
                    $item->addChild('description', 'No description available', $gNamespace);
                }
                
                // Main product image
                $item->addChild('image_link', wp_get_attachment_url($product->get_image_id()), $gNamespace);

                // Additional Images
                $gallery_ids = $product->get_gallery_image_ids();
                foreach ($gallery_ids as $gallery_id) {
                    $item->addChild('additional_image_link', wp_get_attachment_url($gallery_id), $gNamespace);
                }
    
                // Price
                $item->addChild('price', htmlspecialchars($product->get_price() . ' ' . get_woocommerce_currency()), $gNamespace);
    
                // Sale Price (if applicable)
                if ($product->is_on_sale() && !empty($product->get_sale_price())) {
                    $item->addChild('sale_price', htmlspecialchars($product->get_sale_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                }
    
                // Product Type (Category)
                $categories = wp_get_post_terms($product->get_id(), 'product_cat');
                if (!empty($categories) && !is_wp_error($categories)) {
                    $category_names = array_map(function($term) { return $term->name; }, $categories);
                    $item->addChild('product_type', htmlspecialchars(join(' > ', $category_names)), $gNamespace);
                }
            }
        }

		$feed_content = $xml->asXML();

		// Save the generated feed to a transient or a file
		set_transient('smarty_google_feed', $feed_content, 12 * HOUR_IN_SECONDS);
		// or
		file_put_contents(WP_CONTENT_DIR . '/uploads/smarty_google_feed.xml', $feed_content);
	}
}

if (!function_exists('smarty_handle_product_change')) {
    /**
     * Hook into product changes.
     */
    function smarty_handle_product_change($post_id) {
        if (get_post_type($post_id) == 'product') {
            smarty_regenerate_feed(); // Regenerate the feed
	    }
    }
	add_action('save_post_product', 'smarty_handle_product_change');
	add_action('deleted_post', 'smarty_handle_product_change');
}

if (!function_exists('smarty_feed_generator_activate')) {
    /**
     * Activation hook to flush rewrite rules and schedule feed regeneration on plugin activation.
     */
    function smarty_feed_generator_activate() {
        // Add rewrite rules
        smarty_feed_generator_add_rewrite_rules();

        // Flush rewrite rules to ensure custom endpoints are registered
        flush_rewrite_rules();

        // Schedule the feed regeneration event if it's not already scheduled
        if (!wp_next_scheduled('smarty_generate_google_feed_event')) {
            wp_schedule_event(time(), 'twicedaily', 'smarty_generate_google_feed_event');
        }
    }
    register_activation_hook(__FILE__, 'smarty_feed_generator_activate');
}

if (!function_exists('smarty_feed_generator_deactivate')) {
    /**
     * Deactivation hook to flush rewrite rules, clear scheduled feed regeneration,
     * and remove the generated .xml file on plugin deactivation.
     */
    function smarty_feed_generator_deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Clear scheduled feed regeneration event
        $timestamp = wp_next_scheduled('smarty_generate_google_feed_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'smarty_generate_google_feed_event');
        }

        // Path to the generated XML file
        $feed_file_path = WP_CONTENT_DIR . '/uploads/smarty_google_feed.xml';

        // Check if the file exists and delete it
        if (file_exists($feed_file_path)) {
            unlink($feed_file_path);
        }
    }
    register_deactivation_hook(__FILE__, 'smarty_feed_generator_deactivate');
}

if (!function_exists('smarty_convert_and_update_product_image')) {
    /**
     * Converts an image from WEBP to PNG, updates the product image, and regenerates the feed.
     * @param WC_Product $product Product object.
     */
    function smarty_convert_and_update_product_image($product) {
        $image_id = $product->get_image_id();
        if ($image_id) {
            $file_path = get_attached_file($image_id);
            if ($file_path && preg_match('/\.webp$/', $file_path)) {
                $new_file_path = preg_replace('/\.webp$/', '.png', $file_path);
                if (smarty_convert_webp_to_png($file_path, $new_file_path)) {
                    // Update the attachment file type post meta
                    wp_update_attachment_metadata($image_id, wp_generate_attachment_metadata($image_id, $new_file_path));
                    update_post_meta($image_id, '_wp_attached_file', $new_file_path);

                    // Regenerate thumbnails
                    if (function_exists('wp_update_attachment_metadata')) {
                        wp_update_attachment_metadata($image_id, wp_generate_attachment_metadata($image_id, $new_file_path));
                    }

                    // Optionally, delete the original WEBP file
                    @unlink($file_path);

                    // Invalidate feed cache and regenerate
                    smarty_invalidate_feed_cache($product->get_id());
                }
            }
        }
    }
}

if (!function_exists('smarty_convert_webp_to_png')) {
    /**
     * Converts a WEBP image file to PNG.
     * @param string $source The source file path.
     * @param string $destination The destination file path.
     * @return bool True on success, false on failure.
     */
    function smarty_convert_webp_to_png($source, $destination) {
        if (!function_exists('imagecreatefromwebp')) {
            error_log('GD Library is not installed or does not support WEBP.');
            return false;
        }

        $image = imagecreatefromwebp($source);
        if (!$image) return false;

        $result = imagepng($image, $destination);
        imagedestroy($image);

        return $result;
    }
    add_action('woocommerce_admin_process_product_object', 'smarty_convert_and_update_product_image', 10, 1);
}
