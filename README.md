<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/github/explore/80688e429a7d4ef2fca1e82350fe8e3517d3494d/topics/wordpress/wordpress.png" width="100" alt="WordPress Logo"></a></p>


# WordPress - Google Feed Generator for WooCommerce

[![Licence](https://img.shields.io/badge/LICENSE-GPL2.0+-blue)](./LICENSE)

- Developed by: [Martin Nestorov](https://github.com/mnestorov)
- Plugin URI: https://github.com/mnestorov/wp-google-feed-generator

## Overview

**WordPress - Google Feed Generator for WooCommerce** is a comprehensive WordPress plugin designed to dynamically generate and manage Google product and product review feeds for WooCommerce stores. This plugin automates the creation of feeds compliant with Google Merchant Center requirements, facilitating better product visibility and integration with Google Shopping.

## Features

- **Automatic Feed Generation:** Automatically generates Google product feeds, ensuring your WooCommerce store's products are readily available for Google Merchant Center.
- **Product Review Feeds:** In addition to product feeds, this plugin supports generating feeds for product reviews, enhancing product credibility and shopper confidence with user-generated content.
- **Custom Endpoints:** Implements custom rewrite rules to provide easy access to feeds via dedicated URLs, making it simple to submit these feeds to Google Merchant Center.
- **Real-time Updates:** Hooks into WooCommerce's product and review updates, ensuring that any additions, updates, or deletions are promptly reflected in the feeds.
- **Feed Caching:** Utilizes WordPress transients for caching feeds, improving response times and reducing server load for feed generation.
- **Scheduled Regeneration:** Leverages WP-Cron for scheduled feed updates, ensuring your feeds remain up-to-date without manual intervention.
- **Easy Activation and Deactivation:** Ensures a smooth setup and cleanup process through activation and deactivation hooks, managing rewrite rules and scheduled events accordingly.
- **Custom Labels:** Supports custom labels based on various product criteria (e.g., high rating, most ordered, older than X days).
- **Dynamic Bundle Tag:** Automatically sets the `Is Bundle` attribute based on the presence of the `bundle` tag in WooCommerce products.
- **Additional Attributes:** Includes additional attributes such as `g:is_bundle`, `g:google_product_category`, `g:condition`, `g:brand`, and `g:availability` in the product feed.
- **Google Product Category Cleaning:** Automatically cleans the Google product category string, removing any leading numbers and hyphens for better compatibility.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/wp-google-feed-generator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

After activating the plugin, it automatically generates product and review feeds accessible through custom endpoints:

- **Product Feed URL:** https://yourdomain.com/mn-google-feed
- **Review Feed URL:** https://yourdomain.com/mn-google-reviews-feed
- **CSV Feed Export:** https://yourdomain.com/mn-csv-export

These URLs can be submitted to [Google Merchant Center](https://www.google.com/retail/solutions/merchant-center/) for product data and review integration.

## Hooks and Customization

The plugin hooks into various WooCommerce and WordPress actions to detect changes in products and reviews, ensuring feeds are always current. Customization options are available through WordPress filters and actions, allowing developers to extend functionalities as needed.

## Requirements

- WordPress 4.7+ or higher.
- WooCommerce 5.1.0 or higher.
- PHP 7.2+

## Changelog

For a detailed list of changes and updates made to this project, please refer to our [Changelog](./CHANGELOG.md).

## Contributing

Contributions are welcome. Please follow the WordPress coding standards and submit pull requests for any enhancements.

---

## License

This project is released under the [GPL-2.0+ License](http://www.gnu.org/licenses/gpl-2.0.txt).
