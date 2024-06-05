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
     * 
     * This function is designed to generate a custom Google product feed for WooCommerce products. 
     * It uses WordPress and WooCommerce functions to build an XML feed that conforms to Google's specifications for product feeds.
     */
    function smarty_generate_google_feed() {
        // Set the content type to XML for the output
        header('Content-Type: application/xml; charset=utf-8');

        // Temporarily clear the cached feed for testing; remove this in production to utilize caching
        //delete_transient('smarty_google_feed');
    
        // Attempt to retrieve the cached version of the feed
        $cached_feed = get_transient('smarty_google_feed');
        if ($cached_feed !== false) {
            echo $cached_feed; // Output the cached feed and stop processing if it exists
            exit;
        }
    
        // Check if WooCommerce is active before proceeding
        if (class_exists('WooCommerce')) {
            // Define category IDs that should be excluded from the feed
            $uncategorized_term_id = get_term_by('slug', 'uncategorized', 'product_cat')->term_id;
            $upsell_term_id = get_term_by('slug', 'upsell', 'product_cat')->term_id;
            $checkout_upsell_term_id = get_term_by('slug', 'checkout-upsell', 'product_cat')->term_id;

            // Set up arguments for querying products, excluding certain categories
            $args = array(
                'status'       => 'publish',              // Only fetch published products
                'stock_status' => 'instock',              // Only fetch products that are in stock
                'limit'        => -1,                     // Fetch all products that match criteria
                'orderby'      => 'date',                 // Order by date
                'order'        => 'DESC',                 // In descending order
                'type'         => ['simple', 'variable'], // Fetch both simple and variable products
                'tax_query'    => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => [
                            $uncategorized_term_id, 
                            $upsell_term_id, 
                            $checkout_upsell_term_id
                        ],
                        'operator' => 'NOT IN',
                    ),
                ),
            );
    
            // Fetch products using WooCommerce function
            $products = wc_get_products($args);

            // Initialize the XML structure
            $xml = new SimpleXMLElement('<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0"/>');
    
            // Loop through each product to add details to the feed
            foreach ($products as $product) {
                if ($product->is_type('variable')) {
                    // Get all variations if product is variable
                    $variations = $product->get_children();

                    if (!empty($variations)) {
                        $first_variation_id = $variations[0];              // Process only the first variation for the feed
                        $variation = wc_get_product($first_variation_id);
                        $item = $xml->addChild('item');                    // Add item node for each product
                        $gNamespace = 'http://base.google.com/ns/1.0';     // Google namespace
        
                        // Add product details as child nodes
                        $item->addChild('g:id', $variation->get_id(), $gNamespace);   // Include product ID for identification
                        $item->addChild('g:sku', $variation->get_sku(), $gNamespace); // Include SKU for identification
                        $item->addChild('title', htmlspecialchars($product->get_name()), $gNamespace);
                        $item->addChild('link', get_permalink($product->get_id()), $gNamespace);
        
                        // Add description, using meta description if available or fallback to short description
                        $meta_description = get_post_meta($product->get_id(), 'veni-description', true);
                        $description = !empty($meta_description) ? $meta_description : $product->get_short_description();
                        $item->addChild('description', htmlspecialchars(strip_tags($description)), $gNamespace);

                       // Add image links
                        $image_id = $variation->get_image_id() ? $variation->get_image_id() : $product->get_image_id();
                        $item->addChild('image_link', wp_get_attachment_url($image_id), $gNamespace);

                        // Add additional images
                        $gallery_ids = $product->get_gallery_image_ids();
                        foreach ($gallery_ids as $gallery_id) {
                            $item->addChild('additional_image_link', wp_get_attachment_url($gallery_id), $gNamespace);
                        }
        
                        // Add price details
                        $item->addChild('price', htmlspecialchars($variation->get_regular_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                        if ($variation->is_on_sale()) {
                            $item->addChild('sale_price', htmlspecialchars($variation->get_sale_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                        }
        
                        // Add product categories
                        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
                        if (!empty($categories) && !is_wp_error($categories)) {
                            $category_names = array_map(function($term) { return $term->name; }, $categories);
                            $item->addChild('product_type', htmlspecialchars(join(' > ', $category_names)), $gNamespace);
                        }
                    }
                } else {
                    // Process simple products similarly
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
            exit; // Ensure the script stops here to prevent further output that could corrupt the feed
        } else {
            echo '<error>WooCommerce is not active.</error>';
            exit;
        }
    }
    add_action('smarty_generate_google_feed', 'smarty_generate_google_feed'); // the first one is event
}

if (!function_exists('smarty_generate_google_reviews_feed')) {
    /**
     * Generates the custom Google product review feed.
     * 
     * This function retrieves all the products and their reviews from a WooCommerce store and constructs
     * an XML feed that adheres to Google's specifications for review feeds.
     */
    function smarty_generate_google_reviews_feed() {
        // Set the content type to XML for the output
        header('Content-Type: application/xml; charset=utf-8');

        // Arguments for fetching all products; this query can be modified to exclude certain products or categories if needed
        $args = array(
            'post_type' => 'product',
            'numberposts' => -1, // Retrieve all products; adjust based on server performance or specific needs
        );

        // Retrieve products using WordPress get_posts function based on the defined arguments
        $products = get_posts($args);

        // Initialize the XML structure
        $xml = new SimpleXMLElement('<feed xmlns:g="http://base.google.com/ns/1.0"/>');

        // Iterate through each product to process its reviews
        foreach ($products as $product) {
            // Fetch all reviews for the current product. Only approved reviews are fetched
            $reviews = get_comments(array('post_id' => $product->ID));

            // Define the namespace URL for elements specific to Google feeds
            $gNamespace = 'http://base.google.com/ns/1.0';

            // Iterate through each review of the current product
            foreach ($reviews as $review) {
                if ($review->comment_approved == '1') { // Check if the review is approved before including it in the feed
                    // Create a new 'entry' element for each review
                    $entry = $xml->addChild('entry');

                    // Add various child elements required by Google, ensuring data is properly escaped to avoid XML errors
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

        // Output the final XML content
        echo $xml->asXML();
        exit; // Ensure the script stops here to prevent further output that could corrupt the feed
    }
    add_action('smarty_generate_google_reviews_feed', 'smarty_generate_google_reviews_feed'); // the first one is event
}

if (!function_exists('smarty_generate_csv_export')) {
    /**
     * Generates a CSV file export of products for Google Merchant Center or similar services.
     * 
     * This function handles generating a downloadable CSV file that includes details about products
     * filtered based on certain criteria such as stock status and product category.
     */
    function smarty_generate_csv_export() {
        // Set headers to force download and define the file name
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="woocommerce-products.csv"');
    
        // Open PHP output stream as a writable file
        $handle = fopen('php://output', 'w');
        
        // Check if the file handle is valid
        if ($handle === false) {
            wp_die('Failed to open output stream for CSV export'); // Kill the script and display message if handle is invalid
        }
    
        // Define the header row of the CSV file
        $headers = array(
            'ID',                       // WooCommerce product ID
            'ID2',                      // SKU, often used as an alternate identifier
            'Final URL',                // URL to the product page
            'Final Mobile URL',         // URL to the product page, mobile-specific if applicable
            'Image URL',                // Main image URL
            'Item Title',               // Title of the product
            'Item Description',         // Description of the product
            'Item Category',            // Categories the product belongs to
            'Price',                    // Regular price of the product
            'Sale Price',               // Sale price if the product is on discount
            'Google Product Category',  // Google's product category if needed for feeds
            'Is Bundle',                // Indicates if the product is a bundle
            'MPN',                      // Manufacturer Part Number
            'Availability',             // Stock status
            'Condition',                // Condition of the product, usually "new" for e-commerce
            'Brand'                     // Brand of the product
        );
    
        // Write the header row to the CSV file
        fputcsv($handle, $headers);

        // Define category IDs that should be excluded from the feed
        $uncategorized_term_id = get_term_by('slug', 'uncategorized', 'product_cat')->term_id;
        $upsell_term_id = get_term_by('slug', 'upsell', 'product_cat')->term_id;
        $checkout_upsell_term_id = get_term_by('slug', 'checkout-upsell', 'product_cat')->term_id;

        // Prepare arguments for querying products excluding specific categories
        $args = array(
            'status'        => 'publish',              // Only fetch published products
            'stock_status'  => 'instock',              // Only fetch products that are in stock
            'limit'         => -1,                     // Fetch all products that match criteria
            'orderby'       => 'date',                 // Order by date
            'order'         => 'DESC',                 // In descending order
            'type'          => ['simple', 'variable'], // Include both simple and variable products
            'tax_query'     => array(
                array(
                    'taxonomy'  => 'product_cat',
                    'field'     => 'term_id',
                    'terms'     => [
                        $uncategorized_term_id, 
                        $upsell_term_id, 
                        $checkout_upsell_term_id
                    ],
                    'operator'  => 'NOT IN',            // Exclude products from these categories
                ),
            ),
        );
        
        // Retrieve products using the defined arguments
        $products = wc_get_products($args);

        // Define patterns to exclude specific products based on their URL
        $exclude_patterns = [
            '-fb', 
            '-2', 
            '-copy', 
            '-digital-edition', 
            '-plan', 
            '-gift', 
            '-womens-month-sale-', 
            'upsale-', 
            'refreshed-', 
            'nourished-',
            'sheglow-',
            '-band-',
            '-black-up',
        ]; // TODO: #1 Make plugin settings page and add this in "exclude field"
        
        // Iterate through each product
        foreach ($products as $product) {
            // Retrieve the product URL
            $product_link = get_permalink($product->get_id());
    
            // Skip products whose URL contains any of the excluded patterns
            foreach ($exclude_patterns as $pattern) {
                if (strpos($product_link, $pattern) !== false) {
                    continue 2; // Continue to the next product
                }
            }
            
            // Prepare product data for the CSV
            $id = $product->get_id();
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price() ?: ''; // Fallback to empty string if no sale price
            $categories = wp_get_post_terms($id, 'product_cat', array('fields' => 'names'));
            $categories = !empty($categories) ? implode(', ', $categories) : '';
            $image_id = $product->get_image_id();
            $image_link = $image_id ? wp_get_attachment_url($image_id) : '';

            // Custom meta fields for title and description if set
            $meta_title = get_post_meta($id, 'veni-title', true); // TODO: #3 Need to be change as logic and custom field name
            $meta_description = get_post_meta($id, 'veni-description', true); // TODO: #3 Need to be change as logic and custom field name
            $name = !empty($meta_title) ? htmlspecialchars(strip_tags($meta_title)) : htmlspecialchars(strip_tags($product->get_name()));
            $description = !empty($meta_description) ? htmlspecialchars(strip_tags($meta_description)) : htmlspecialchars(strip_tags($product->get_short_description()));
            $description = preg_replace('/\s+/', ' ', $description); // Normalize whitespace in descriptions
            $availability = $product->is_in_stock() ? 'in stock' : 'out of stock';
            $google_product_category = 'Food, Beverages & Tobacco > Beverages > Tea & Infusions'; // TODO: #2 Make this to be set from the plugin settings page with dropdown/select field to set Google category
            $brand = get_bloginfo('name');
            
            // Check for variable type to handle variations
            if ($product->is_type('variable')) {
                // Handle variations separately if product is variable
                $variations = $product->get_children();
                if (!empty($variations)) {
                    $first_variation_id = reset($variations); // Get only the first variation
                    $variation = wc_get_product($first_variation_id);
                    $sku = $variation->get_sku();
                    $variation_image = wp_get_attachment_url($variation->get_image_id());
                    $variation_price = $variation->get_regular_price();
                    $variation_sale_price = $variation->get_sale_price() ?: '';
                    
                    // Prepare row for each variation
                    $row = array(
                        'ID'                      => $id,
                        'ID2'                     => $sku,
                        'Final URL'               => $product_link,
                        'Final Mobile URL'        => $product_link,
                        'Image URL'               => $variation_image,
                        'Item Title'              => $name,
                        'Item Description'        => $description,
                        'Item Category'           => $categories,
                        'Price'                   => $variation_price,
                        'Sale Price'              => $variation_sale_price,
                        'Google Product Category' => $google_product_category,
                        'Is Bundle'               => 'no',
                        'MPN'                     => $sku,
                        'Availability'            => $availability,
                        'Condition'               => 'New',
                        'Brand'                   => $brand,
                    );
                }
            } else {
                // Prepare row for a simple product
                $sku = $product->get_sku();
                $row = array(
                    'ID'                      => $id,
                    'ID2'                     => $sku,
                    'Final URL'               => $product_link,
                    'Final Mobile URL'        => $product_link,
                    'Image URL'               => $image_link,
                    'Item Title'              => $name,
                    'Item Description'        => $description,
                    'Item Category'           => $categories,
                    'Price'                   => $regular_price,
                    'Sale Price'              => $sale_price,
                    'Google Product Category' => $google_product_category,
                    'Is Bundle'               => 'no',
                    'MPN'                     => $sku,
                    'Availability'            => $availability,
                    'Condition'               => 'New',
                    'Brand'                   => $brand,
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
    /**
     * Invalidates the cached Google product feed and optionally regenerates it upon the deletion of a product.
     * 
     * This function ensures that when a product is deleted from WooCommerce, any cached version of the feed
     * does not continue to include data related to the deleted product.
     *
     * @param int $post_id The ID of the post (product) being deleted.
     */
    function smarty_invalidate_feed_cache_on_delete($post_id) {
        // Check if the post being deleted is a product
        if (get_post_type($post_id) === 'product') {
            // Invalidate the cache by deleting the stored transient that contains the feed data
            // This forces the system to regenerate the feed next time it's requested, ensuring that the deleted product is no longer included
            delete_transient('smarty_google_feed');
            
            // Regenerate the feed immediately to update the feed file
            // This step is optional but recommended to keep the feed up to date
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
     * 
     * This function is designed to refresh the product feed by querying the latest product data from WooCommerce,
     * constructing an XML feed, and saving it either to a transient for fast access or directly to a file.
     */
	function smarty_regenerate_feed() {
        // Fetch products from WooCommerce that are published and in stock
		$products = wc_get_products(array(
			'status'        => 'publish',
            'stock_status'  => 'instock',
			'limit'         => -1,          // No limit to ensure all qualifying products are included
			'orderby'       => 'date',      // Order by product date
			'order'         => 'DESC',      // Order in descending order
		));

        // Initialize XML structure with a root element and namespace attribute
		$xml = new SimpleXMLElement('<feed xmlns:g="http://base.google.com/ns/1.0"/>');
		
        // Iterate through each product to populate the feed
		foreach ($products as $product) {
            if ($product->is_type('variable')) {
                // Handle variable products, which can have multiple variations
                foreach ($product->get_children() as $child_id) {
                    $variation = wc_get_product($child_id);
                    $item = $xml->addChild('item');
                    $gNamespace = 'http://base.google.com/ns/1.0';

                    // Add basic product and variation details
                    $item->addChild('g:id', $variation->get_id(), $gNamespace);
                    $item->addChild('g:sku', $variation->get_sku(), $gNamespace);
                    $item->addChild('title', htmlspecialchars($product->get_name()), $gNamespace);
                    $item->addChild('link', get_permalink($product->get_id()), $gNamespace);
                    
                    // Description: Use main product description or fallback to the short description if empty
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

                    // Additional images: Loop through gallery if available
                    $gallery_ids = $product->get_gallery_image_ids();
                    foreach ($gallery_ids as $gallery_id) {
                        $item->addChild('additional_image_link', wp_get_attachment_url($gallery_id), $gNamespace);
                    }

                    // Pricing: Regular and sale prices
                    $item->addChild('price', htmlspecialchars($variation->get_regular_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                    if ($variation->is_on_sale()) {
                        $item->addChild('sale_price', htmlspecialchars($variation->get_sale_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                    }

                    // Categories: Compile a list from the product's categories
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

                // Description handling
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
                
                 // Main image and additional images
                $item->addChild('image_link', wp_get_attachment_url($product->get_image_id()), $gNamespace);
                $gallery_ids = $product->get_gallery_image_ids();
                foreach ($gallery_ids as $gallery_id) {
                    $item->addChild('additional_image_link', wp_get_attachment_url($gallery_id), $gNamespace);
                }
    
                // Pricing information
                $item->addChild('price', htmlspecialchars($product->get_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                if ($product->is_on_sale() && !empty($product->get_sale_price())) {
                    $item->addChild('sale_price', htmlspecialchars($product->get_sale_price() . ' ' . get_woocommerce_currency()), $gNamespace);
                }
    
                // Category information
                $categories = wp_get_post_terms($product->get_id(), 'product_cat');
                if (!empty($categories) && !is_wp_error($categories)) {
                    $category_names = array_map(function($term) { return $term->name; }, $categories);
                    $item->addChild('product_type', htmlspecialchars(join(' > ', $category_names)), $gNamespace);
                }
            }
        }

        // Save the generated XML content to a transient or a file for later use
		$feed_content = $xml->asXML();
		set_transient('smarty_google_feed', $feed_content, 12 * HOUR_IN_SECONDS);               // Cache the feed using WordPress transients 
		file_put_contents(WP_CONTENT_DIR . '/uploads/smarty_google_feed.xml', $feed_content);   // Optionally save the feed to a file in the WP uploads directory
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
        if (!class_exists('WooCommerce')) {
            wp_die('This plugin requires WooCommerce to be installed and active.');
        }

        // Add rewrite rules
        smarty_feed_generator_add_rewrite_rules();

        // Flush rewrite rules to ensure custom endpoints are registered
        flush_rewrite_rules();

        // Schedule Google Feed Event
        if (!wp_next_scheduled('smarty_generate_google_feed')) {
            wp_schedule_event(time(), 'twicedaily', 'smarty_generate_google_feed');
        }

        // Schedule Google Reviews Feed Event
        if (!wp_next_scheduled('smarty_generate_google_reviews_feed')) {
            wp_schedule_event(time(), 'daily', 'smarty_generate_google_reviews_feed');
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
        $google_feed_timestamp = wp_next_scheduled('smarty_generate_google_feed');
        if ($google_feed_timestamp) {
            wp_unschedule_event($google_feed_timestamp, 'smarty_generate_google_feed');
        }

        // Unscheduling the reviews feed event
        $review_feed_timestamp = wp_next_scheduled('smarty_generate_google_reviews_feed');
        if ($review_feed_timestamp) {
            wp_unschedule_event($review_feed_timestamp, 'smarty_generate_google_reviews_feed');
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
