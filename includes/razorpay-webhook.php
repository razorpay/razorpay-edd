<?php

use Razorpay\Api\Api;
use Razorpay\Api\Errors;

class RZP_EDD_Webhook
{
    /**
     * API client instance to communicate with Razorpay API
     * @var Razorpay\Api\Api
     */
    protected $api;

    /**
     * Event constants
     */
    const ORDER_PAID  = 'order.paid';

    function __construct()
    {
        $this->api = getRazorpayApiInstance();
    }

    /**
     * Process a Razorpay Webhook. We exit in the following cases:
     * - Successful processed
     * - Exception while fetching the payment
     *
     * It passes on the webhook in the following cases:
     * - invoice_id set in payment.authorized
     * - Invalid JSON
     * - Signature mismatch
     * - Secret isn't setup
     * - Event not recognized
     */
    public function process()
    {
        $post = file_get_contents('php://input');

        $data = json_decode($post, true);

        if (json_last_error() !== 0)
        {
            return;
        }

        $enabled = getSetting('enable_webhook');

        if (isset($enabled) === true and
            (empty($data['event']) === false))
        {
            if (isset($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']) === true)
            {
                $razorpayWebhookSecret = getSetting('webhook_secret');

                //
                // If the webhook secret isn't set on wordpress, return
                //
                if (empty($razorpayWebhookSecret) === true)
                {
                    return;
                }

                try
                {
                    $this->api->utility->verifyWebhookSignature($post,
                                                                $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'],
                                                                $razorpayWebhookSecret);
                }
                catch (Errors\SignatureVerificationError $e)
                {
                    $log = array(
                        'message'   => $e->getMessage(),
                        'data'      => $data,
                        'event'     => 'razorpay.edd.signature.verify_failed'
                    );

                    error_log(json_encode($log));
                    return;
                }

                switch ($data['event'])
                {
                    case self::ORDER_PAID:
                        return $this->orderPaid($data);

                    default:
                        return;
                }
            }
        }
    }

    /**
     * Order Paid webhook
     *
     * @param array $data
     */
    protected function orderPaid(array $data)
    {
        // We don't process subscription/invoice payments here
        if (isset($data['payload']['payment']['entity']['invoice_id']) === true)
        {
            return;
        }

        //
        // Order entity should be sent as part of the webhook payload
        //
        $orderId = $data['payload']['order']['entity']['notes']['edd_order_id'];

        $order = new EDD_Payment($orderId);

        // If it is already marked as paid or failed, ignore the event
        if ($order->status === 'publish' or $order->status === 'failed')
        {
            return;
        }

        $success = false;
        $error = "";
        $errorMessage = 'The payment has failed.';

        $razorpayPaymentId = $data['payload']['payment']['entity']['id'];

        $payment = $this->getPaymentEntity($razorpayPaymentId, $data);

        $amount = $this->getOrderAmountAsInteger($order);

        if($payment['amount'] === $amount)
        {
            $success = true;
        }
        else
        {
            $error = 'EDD_ERROR: Payment to Razorpay Failed. Amount mismatch.';
        }

        updateOrder($success, $razorpayPaymentId, $orderId, $error, $errorMessage);

        // Graceful exit since payment is now processed.
        exit;
    }

    protected function getPaymentEntity($razorpayPaymentId, $data)
    {
        try
        {
            $payment = $this->api->payment->fetch($razorpayPaymentId);
        }
        catch (Exception $e)
        {
            $log = array(
                'message'         => $e->getMessage(),
                'payment_id'      => $razorpayPaymentId,
                'event'           => $data['event']
            );

            error_log(json_encode($log));

            exit;
        }

        return $payment;
    }

    /**
     * Returns the order amount, rounded as integer
     * @param EDD_Order $order EDD Order instance
     * @return int Order Amount
     */
    public function getOrderAmountAsInteger($order)
    {
        return (int) round($order->total * 100);
    }
}
