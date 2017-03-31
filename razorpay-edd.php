<?php
/*
Plugin Name: Easy Digital Downloads - Razorpay Gateway
Description: Razorpay gateway for Easy Digital Downloads
Version: 1.0
Author: Razorpay
Author URI: http://razorpay.com
*/

if (!defined('ABSPATH')) exit;

require_once __DIR__.'/razorpay-sdk/Razorpay.php';
use Razorpay\Api\Api;

// registers the gateway
function razorpay_register_gateway($gateways)
{
    $gateways['razorpay'] = array(
        'admin_label'    => 'Razorpay',
        'checkout_label' => __('Razorpay', 'Razorpay')
    );

    return $gateways;
}

add_filter('edd_payment_gateways', 'razorpay_register_gateway');
add_action('admin_notices', 'razorpay_admin_notices');

add_action('edd_razorpay_cc_form', '__return_false');

function razorpay_admin_notices()
{
    if (! is_plugin_active('easy-digital-downloads/easy-digital-downloads.php')) {
        $message = '<b>' . __('Easy Digital Downloads Payment Gateway by Razorpay', 'edd-razorpay-gateway') . '</b> ' . __('add-on requires', 'edd-razorpay-gateway') . ' ' . '<a href="https://easydigitaldownloads.com" target="_new">' . __('Easy Digital Downloads', 'edd-razorpay-gateway') . '</a>' . ' ' . __('plugin. Please install and activate it.', 'edd-razorpay-gateway');
    }
    elseif (! function_exists('curl_init')) {
        $message = '<b>' . __('Easy Digital Downloads Payment Gateway by Razorpay', 'edd-razorpay-gateway') . '</b> ' . __('requires ', 'edd-razorpay-gateway') . __('PHP CURL.', 'edd-razorpay-gateway') . '</a>' . ' ' . __(' Please install/enable php_curl!', 'edd-razorpay-gateway');
    }

    echo isset($message) ? '<div id="notice" class="error"><p>' . $message.  '</p></div>' : '';
}

function razorpay_get_redirect_response()
{
    $redirect_response = $_POST;

    if (isset($redirect_response['gateway']) && $redirect_response['gateway'] === 'razorpay_gateway' && isset($redirect_response['merchant_order_id']))
    {
        razorpay_check_response($redirect_response, $redirect_response['merchant_order_id']);
    }
    else
    {
        return;
    }
}

add_action('init', 'razorpay_get_redirect_response');

function razorpay_check_response($response, $order_no)
{
    global $edd_options;

    $payment_gateways = EDD()->session->get('edd_purchase');

    $success = true;

    $error_message = 'Payment failed. Please try again.';

    if (!empty($response['razorpay_payment_id']))
    {
        // Verifying payment signature
        $attributes = array(
            'razorpay_payment_id' => $response['razorpay_payment_id'],
            'razorpay_order_id'   => EDD()->session->get('razorpay_order_id'),
            'razorpay_signature'  => $response['razorpay_signature']
        );

        $api = new Api($edd_options['key_id'], $edd_options['key_secret']);

        try
        {
            $api->utility->verifyPaymentSignature($attributes);
        }
        catch (SignatureVerificationError $e)
        {
            $success = false;

            $error = "PAYMENT_ERROR: Payment failed : " . $e->getMessage();
        }
    }

    if ($success === true)
    {
        $comments = __( 'Razorpay Transaction ID: ', 'edd-razorpay-gateway' ) . $response['razorpay_payment_id'] . "\n";
        $response_text = 'publish';
        $comments .= $response_text;
        $comments = html_entity_decode( $comments, ENT_QUOTES, 'UTF-8' );

        $notes = array(
            'ID'            => $order_no,
            'post_excerpt'  => $comments
        );

        wp_update_post($notes);
        edd_update_payment_status($order_no, 'publish');

        edd_insert_payment_note($order_no, $comments);

        edd_empty_cart();

        edd_send_to_success_page();
    }
    else
    {
        $comments = '';

        if (isset($response['razorpay_payment_id']))
        {
            $comments = __( 'Razorpay Transaction ID: ', 'edd-razorpay-gateway' ) . $response['razorpay_payment_id'] . "\n";
        }

        $comments .= $response_text . ' ' . $error;
        $comments = html_entity_decode( $comments, ENT_QUOTES, 'UTF-8' );

        $notes = array(
            'ID'            => $order_no,
            'post_excerpt'  => $comments
        );

        wp_update_post($notes);
        edd_update_payment_status($order_no, 'failed' );

        edd_insert_payment_note($order_no, $comments);

        edd_set_error('server_direct_validation', __($error_message, 'edd-razorpay-gateway'));

        edd_send_back_to_checkout();
    }
}

function razorpay_get_customer_data($purchase_data)
{
    $language   = strtoupper(substr(get_bloginfo('language') , 0, 2));
    $firstname  = $purchase_data['user_info']['first_name'];
    $lastname   = $purchase_data['user_info']['last_name'];
    $email      = isset($purchase_data['user_email']) ? $purchase_data['user_email'] : $purchase_data['user_info']['email'];

    if (empty($firstname) || empty($lastname))
    {
        $name = $firstname.$lastname;
        list($firstname, $lastname) = preg_match('/\s/', $name) ? explode(' ', $name, 2) : array($name, $name);
    }

    $customer_data = array(
        'name'      => $firstname . ' '. $lastname,
        'email'     => $email,
        'currency'  => edd_get_currency(),
        'use_utf8'  => 1,
        'lang'      => $language
    );

    return $customer_data;
}

function razorpay_process_payment($purchase_data)
{
    global $edd_options;

    $payment_code       = 'razorpay';
    $customer_data      = razorpay_get_customer_data($purchase_data);
    $purchase_summary   = edd_get_purchase_summary($purchase_data);

    // Config data
    $config_data = array(
        'return_url'            => get_permalink($edd_options['success_page']),
        'return_method'         => 'POST',
        'error_return_url'      => edd_get_checkout_uri() . '?payment-mode=' . $purchase_data['post_data']['edd-gateway'],
        'error_return_method'   => 'POST'
    );

    /**********************************
    * set up the payment details      *
    **********************************/

    $payment = array(
        'price'         => $purchase_data['price'],
        'date'          => $purchase_data['date'],
        'user_email'    => $purchase_data['user_email'],
        'purchase_key'  => $purchase_data['purchase_key'],
        'currency'      => $edd_options['currency'],
        'downloads'     => $purchase_data['downloads'],
        'cart_details'  => $purchase_data['cart_details'],
        'user_info'     => $purchase_data['user_info'],
        'status'        => 'pending'
    );

    $order_no = edd_insert_payment($payment);

    $purchase_data = array(
        'key'            => $edd_options['key_id'],
        'amount'         => $payment['price'] * 100,
        'currency'       => $payment['currency'],
        'description'    => $purchase_summary,
        'prefill'        => array(
            'name'           => $customer_data['name'],
            'email'          => $payment['user_info']['email']
        ),
        'notes'          => array(
        'merchant_order' => $order_no
        )
    );

    // Have to get razorpay order id by using orders API
    $api = new Api($edd_options['key_id'], $edd_options['key_secret']);

    $data = get_order_creation_data($purchase_data); // we aren't using classes here

    $razorpay_order = $api->order->create($data);

    $purchase_data['order_id'] = $razorpay_order['id'];

    EDD()->session->set('razorpay_order_id', $razorpay_order['id']);

    $errors = edd_get_errors();

    if (!$errors)
    {
        $json = json_encode($purchase_data);

        $button_html = file_get_contents(__DIR__.'/js/checkout.phtml');

        $keys = array('#json#', '#error_return_url#', '#return_url#', '#merchant_order#');
        $values = array($json, $config_data['error_return_url'], $config_data['return_url'], $order_no);

        $html = str_replace($keys, $values, $button_html);

        echo $html;
        exit;
    }
}
add_action('edd_gateway_razorpay', 'razorpay_process_payment');

function get_order_creation_data($purchase_data)
{           
    $data = array(
        'receipt'         => $purchase_data['notes']['merchant_order'],
        'amount'          => $purchase_data['amount'],
        'currency'        => $purchase_data['currency'],
        'payment_capture' => 1
    );

    return $data;
}

function razorpay_add_settings($settings)
{
    $razorpay_settings = array(
        array(
            'id'   => 'razorpay_settings',
            'name' => '<strong>' . __('Razorpay', 'razorpay') . '</strong>',
            'desc' => __('Configure the Razorpay settings', 'razorpay'),
            'type' => 'header'
        ),
        array(
            'id'   => 'title',
            'name' => __('Title:', 'razorpay'),
            'desc' => __('This controls the title which the user sees during checkout.', 'razorpay'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'description',
            'name' => __('Description', 'razorpay'),
            'type' => 'textarea',
            'desc' => __('This controls the description which the user sees during checkout.', 'razorpay'),
        ),
        array(
            'id'   => 'key_id',
            'name' => __('Key ID', 'razorpay'),
            'desc' => __('The key Id and key secret can be generated from "API Keys" section of Razorpay Dashboard. Use test or live for test or live mode.', 'razorpay'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'key_secret',
            'name' => __('Key Secret', 'razorpay'),
            'desc' => __('The key Id and key secret can be generated from "API Keys" section of Razorpay Dashboard. Use test or live for test or live mode.', 'razorpay'),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'override_merchant_name',
            'name' => __('Merchant Name', 'razorpay'),
            'desc' => __('Merchant name to be displayed on Razorpay screen.', 'razorpay'),
            'type' => 'text',
            'size' => 'regular'
        )
    );

    return array_merge($settings, $razorpay_settings);
}

add_filter('edd_settings_gateways', 'razorpay_add_settings');
