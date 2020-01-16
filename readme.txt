=== Razorpay for Easy Digital Downloads===
Contributors: razorpay
Tags: razorpay, payments, india, easy digital downloads, edd
Requires at least: 3.9.2
Tested up to: 5.3.2
Stable tag: 2.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows you to use Razorpay payment gateway with the Easy Digital Downloads plugin.

== Description ==

This is the official Razorpay payment gateway plugin for Easy Digital Downloads. Allows you to accept credit cards, debit cards, netbanking with the Easy Digital Downloads plugin. It uses a seamles integration, allowing the customer to pay on your website without being redirected away from your woocommerce website.


== Installation ==

1. Download the plugin from [the wordpress plugin server](https://downloads.wordpress.org/plugin/edd-razorpay.2.0.0.zip)
2. Ensure you have latest version of Easy Digital Downloads plugin installed
3. Unzip and upload contents of the plugin to your /wp-content/plugins/ directory
4. Activate the plugin through the 'Plugins' menu in WordPress

If you have downloaded the plugin from GitHub or elsewhere, make sure
that the directory is named `edd-razorpay`.

== Configuration ==

1. Visit the Easy Digital Downloads settings page, and click on the Checkout/Payment Gateways tab.
2. Click on Razorpay to edit the settings. If you do not see Razorpay in the list at the top of the screen make sure you have activated the plugin in the WordPress Plugin Manager.
3. Enable the Payment Method, name it Credit Card / Debit Card / Internet Banking (this will show up on the payment page your customer sees), add in your key id and key secret.
4. The Payment Action should be set to "Authorize and Capture". If you want to capture payments manually from the Dashboard after manual verification, set it to "Authorize".

== Changelog =

= 2.1.0 =
* Set to default auto-capture
* Added webhook support.

= 2.0.0 =
* Bug fixes for international currency
* Update latest sdk 2.5.0

== Support ==

Visit [razorpay.com/support](https://razorpay.com/support) for support requests.

== License ==

The Razorpay Easy Digital Downloads plugin is released under the GPLv2 license, same as that
of WordPress. See the LICENSE file for the complete LICENSE text.
