<?php

namespace WP_Application\CustomTable;

use WP_Queue\SMS;

class WP_Queue_Hooks
{
    public function __construct()
    {

        // When New Post
        # add_action('save_post', [$this, 'save_post'], 30, 2);

        // SMS When Completed WooCommerce Order
        # add_action('woocommerce_order_status_changed', array($this, 'woocommerce_order_status_changed'), 20, 3);
    }

    public function save_post($post_id, $post): void
    {
        if ($post->post_type == 'post' and $post->post_status == 'publish') {

            // Check Before Exist
            if (!WP_Queue::exist(0, $post_id, 'Test', 'moveTrash')) {

                $createJob = \WP_Application\CustomTable\WP_Queue::insert([
                    'user_id' => 0,
                    'object_id' => $post_id,
                    'job' => 'Test',
                    'method' => 'moveTrash',
                    'args' => array(
                        'post_title' => $post->post_title,
                    ),
                    // https://github.com/WordPress/WordPress/blob/d2bc3b6abc5c130936cef69744c065d0ed6bfd90/wp-includes/default-constants.php#L158
                    'run_at' => date('Y-m-d H:i:s', current_time('timestamp') + MINUTE_IN_SECONDS)
                ]);
            }
        }
    }

    public function woocommerce_order_status_changed($order_id, $from = '', $to = '')
    {
        if (!$order_id) {
            return;
        }

        // Get Order
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check Status Order
        if ($to != 'completed') {
            return;
        }

        // Get User ID
        $user_id = (int)$order->get_user_id();

        // Check Mobile
        $mobile = null;
        $billing_phone = SMS::sanitize_mobile($order->get_billing_phone());
        $validate_billing_phone = SMS::mobile_check($billing_phone);
        if ($validate_billing_phone['success'] === true) {

            $mobile = $billing_phone;
        } else {

            // Check By user_login
            if ($user_id > 0) {

                $user = get_userdata($user_id);
                $user_login = SMS::sanitize_mobile($user->user_login);
                $validate_user_login = SMS::mobile_check($user_login);
                if ($validate_user_login['success'] === true) {
                    $mobile = $user_login;
                }
            }
        }

        if (empty($mobile)) {
            return;
        }

        $first_name = trim($order->get_billing_first_name());
        $last_name = trim($order->get_billing_last_name());

        // Check Before Exist
        if (!WP_Queue::exist($user_id, $order_id, 'SMS', 'orderCompleted')) {

            $createJob = \WP_Application\CustomTable\WP_Queue::insert([
                'user_id' => $user_id,
                'object_id' => $order_id,
                'job' => 'SMS',
                'method' => 'orderCompleted',
                'args' => array(
                    'mobile' => $mobile,
                    'body' => array(
                        (string) $first_name,
                        (string) $last_name,
                        (string) $order_id
                    )
                ),

                // https://github.com/WordPress/WordPress/blob/d2bc3b6abc5c130936cef69744c065d0ed6bfd90/wp-includes/default-constants.php#L158
                'run_at' => date('Y-m-d H:i:s', current_time('timestamp') + (10 * MINUTE_IN_SECONDS))
            ]);
        }
    }

}

new WP_Queue_Hooks();