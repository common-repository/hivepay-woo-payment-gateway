=== Plugin Name ===
Contributors: hivepayio
Donate link: https://hivepay.io/docs/
Tags: hivepay, woocommerce, payment gateway, hive, hive-engine
Requires at least: 5.1
Tested up to: 5.5.3
Requires PHP: 5.4
Stable tag: 5.5.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 
This plugin allows WooCommerce merchants to accept Hive and Hive-Engine Tokens.
 
== Description ==
 
This plugin allows WooCommerce merchants to accept Hive and Hive-Engine Tokens.

This plugin uses the HivePay.io API to create a shopping cart for users to complete their purchase. 
API URL is: https://api.hivepay.io

The information sent to the API consists of:
 - Your websites IPN notification URL
 - The return URL for the customer upon completion of payment
 - The cancel URL if the customer cancels the transaction
 - Your Hive username as provided in settings
 - The base currency in which your site uses
 - The order number of the transaction
 - The Item list to be purchased which includes: item name, amount, and quantity
 - Your email if provided to receive payment confirmation emails from HivePay.io
 - The currency list in which you wish to receive payment
 
== Installation ==
 
1. Upload `hivepay_woo` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce / Settings / Payments to setup HivePay
 

 
== Changelog ==
 
= 1.0 =
Initial Release
