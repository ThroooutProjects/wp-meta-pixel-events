<?php

if (!defined('ABSPATH')) {
    exit;
}

function mpe_build_pixel_payload(): ?array
{
    if (!class_exists('WooCommerce')) {
        return null;
    }
    if (!function_exists('wc_get_product')) {
        return null;
    }

    $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';

    $payload = [
        'currency' => $currency,
        'events' => [
            'viewContent' => (bool) get_option('mpe_event_view_content', true),
            'initiateCheckout' => (bool) get_option('mpe_event_initiate_checkout', true),
            'purchase' => (bool) get_option('mpe_event_purchase', true),
            'addToCart' => (bool) get_option('mpe_event_add_to_cart', true),
            'addToWishlist' => (bool) get_option('mpe_event_add_to_wishlist', true),
            'addPaymentInfo' => (bool) get_option('mpe_event_add_payment_info', true),
        ],
        'eventIds' => [],
    ];

    // ViewContent (single product)
    if (function_exists('is_product') && is_product()) {
        $payload['page'] = 'product';
        $product = wc_get_product(get_the_ID());
        if ($product) {
            $payload['product'] = [
                'id' => (string) $product->get_id(),
                'value' => (float) wc_get_price_to_display($product),
            ];

            $payload['eventIds']['viewContent'] = 'mpe_vc_' . $product->get_id() . '_' . wp_generate_uuid4();

            if ($payload['events']['viewContent'] && mpe_capi_should_send('viewContent')) {
                mpe_send_capi_event('ViewContent', $payload['eventIds']['viewContent'], [
                    'content_ids' => [(string) $product->get_id()],
                    'content_type' => 'product',
                    'value' => (float) wc_get_price_to_display($product),
                    'currency' => $currency,
                ]);
            }
        }

        return $payload;
    }

    // InitiateCheckout (checkout page, excluding order received)
    if (function_exists('is_checkout') && is_checkout() && !(function_exists('is_order_received_page') && is_order_received_page())) {
        $payload['page'] = 'checkout';
        if (function_exists('WC') && WC()->cart) {
            $payload['checkout'] = [
                'value' => (float) WC()->cart->get_total('edit'),
                'num_items' => (int) WC()->cart->get_cart_contents_count(),
            ];

            $payload['eventIds']['initiateCheckout'] = 'mpe_ic_' . wp_generate_uuid4();

            if ($payload['events']['initiateCheckout'] && mpe_capi_should_send('initiateCheckout')) {
                mpe_send_capi_event('InitiateCheckout', $payload['eventIds']['initiateCheckout'], [
                    'value' => (float) WC()->cart->get_total('edit'),
                    'currency' => $currency,
                    'num_items' => (int) WC()->cart->get_cart_contents_count(),
                ]);
            }
        }

        return $payload;
    }

    // Purchase (thank you / order received)
    if (function_exists('is_order_received_page') && is_order_received_page()) {
        $payload['page'] = 'purchase';

        $order_id = absint(get_query_var('order-received'));
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order) {
                $purchase_event_id = (string) $order->get_meta('_mpe_purchase_event_id');
                if ($purchase_event_id === '') {
                    $purchase_event_id = 'mpe_p_' . $order->get_id() . '_' . wp_generate_uuid4();
                    $order->update_meta_data('_mpe_purchase_event_id', $purchase_event_id);
                    $order->save();
                }

                $items = [];
                foreach ($order->get_items() as $item) {
                    $product = $item->get_product();
                    if (!$product) {
                        continue;
                    }
                    $items[] = [
                        'id' => (string) $product->get_id(),
                        'quantity' => (int) $item->get_quantity(),
                    ];
                }

                $payload['purchase'] = [
                    'value' => (float) $order->get_total(),
                    'currency' => (string) $order->get_currency(),
                    'order_id' => (string) $order->get_order_number(),
                    'contents' => $items,
                    'content_type' => 'product',
                    'num_items' => (int) $order->get_item_count(),
                    'event_id' => $purchase_event_id,
                ];

                $payload['currency'] = (string) $order->get_currency();
            }
        }

        return $payload;
    }

    // Default: return the base payload so click-based events (AddToCart/Wishlist)
    // work across shop/category/home pages as well.
    return $payload;
}
