<?php

if (!defined('ABSPATH')) {
    exit;
}

function mpe_get_capi_access_token(): string
{
    $token = get_option('mpe_capi_access_token', '');
    return is_string($token) ? trim($token) : '';
}

function mpe_get_capi_test_event_code(): string
{
    $code = get_option('mpe_capi_test_event_code', '');
    return is_string($code) ? trim($code) : '';
}

function mpe_is_capi_enabled(): bool
{
    return (bool) get_option('mpe_capi_enabled', false) && mpe_get_capi_access_token() !== '';
}

function mpe_capi_should_send(string $event_key): bool
{
    if (!mpe_is_capi_enabled()) {
        return false;
    }

    // Keys are camelCase to match the JS payload keys.
    switch ($event_key) {
        case 'viewContent':
            return (bool) get_option('mpe_capi_event_view_content', false);
        case 'initiateCheckout':
            return (bool) get_option('mpe_capi_event_initiate_checkout', false);
        case 'purchase':
            return (bool) get_option('mpe_capi_event_purchase', true);
        default:
            return false;
    }
}

function mpe_send_capi_event(string $event_name, string $event_id, array $custom_data = [], array $user_data_overrides = []): void
{
    $pixel_id = mpe_get_pixel_id();
    $token = mpe_get_capi_access_token();

    if ($pixel_id === '' || $token === '') {
        return;
    }

    $ip = mpe_get_client_ip_address();
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

    $user_data = [];
    if ($ip !== '') {
        $user_data['client_ip_address'] = $ip;
    }
    if ($ua !== '') {
        $user_data['client_user_agent'] = $ua;
    }

    if (!empty($_COOKIE['_fbp'])) {
        $user_data['fbp'] = sanitize_text_field(wp_unslash($_COOKIE['_fbp']));
    }
    if (!empty($_COOKIE['_fbc'])) {
        $user_data['fbc'] = sanitize_text_field(wp_unslash($_COOKIE['_fbc']));
    }

    if (is_user_logged_in()) {
        $user_data['external_id'] = [mpe_hash_sha256((string) get_current_user_id())];
    }

    foreach ($user_data_overrides as $k => $v) {
        $user_data[$k] = $v;
    }

    $event = [
        'event_name' => $event_name,
        'event_time' => time(),
        'event_id' => $event_id,
        'action_source' => 'website',
        'event_source_url' => mpe_get_event_source_url(),
        'user_data' => $user_data,
    ];

    if (!empty($custom_data)) {
        $event['custom_data'] = $custom_data;
    }

    $payload = [
        'data' => [$event],
    ];

    $test_code = mpe_get_capi_test_event_code();
    if ($test_code !== '') {
        $payload['test_event_code'] = $test_code;
    }

    $endpoint = sprintf('https://graph.facebook.com/v20.0/%s/events', rawurlencode($pixel_id));
    $endpoint = add_query_arg('access_token', $token, $endpoint);

    $response = wp_remote_post($endpoint, [
        'timeout' => 5,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        mpe_store_pixel_log_row([
            'time' => gmdate('Y-m-d H:i:s'),
            'event' => 'CAPI:' . $event_name,
            'page' => 'server',
            'url' => mpe_get_event_source_url(),
            'params' => [
                'error' => $response->get_error_message(),
            ],
        ]);
        return;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        mpe_store_pixel_log_row([
            'time' => gmdate('Y-m-d H:i:s'),
            'event' => 'CAPI:' . $event_name,
            'page' => 'server',
            'url' => mpe_get_event_source_url(),
            'params' => [
                'http' => $code,
                'body' => wp_remote_retrieve_body($response),
            ],
        ]);
    }
}

function mpe_capi_send_purchase_on_thankyou($order_id): void
{
    if (!function_exists('wc_get_order')) {
        return;
    }

    if (!mpe_capi_should_send('purchase')) {
        return;
    }

    $order_id = absint($order_id);
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    if ($order->get_meta('_mpe_capi_purchase_sent') === '1') {
        return;
    }

    $event_id = (string) $order->get_meta('_mpe_purchase_event_id');
    if ($event_id === '') {
        $event_id = 'mpe_p_' . $order->get_id() . '_' . wp_generate_uuid4();
        $order->update_meta_data('_mpe_purchase_event_id', $event_id);
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

    $custom_data = [
        'value' => (float) $order->get_total(),
        'currency' => (string) $order->get_currency(),
        'contents' => $items,
        'content_type' => 'product',
        'num_items' => (int) $order->get_item_count(),
        'order_id' => (string) $order->get_order_number(),
    ];

    $user_data = [];

    $email = $order->get_billing_email();
    if ($email) {
        $hashed = mpe_hash_sha256((string) $email);
        if ($hashed !== '') {
            $user_data['em'] = [$hashed];
        }
    }

    $phone = $order->get_billing_phone();
    if ($phone) {
        $phone = preg_replace('/[^0-9+]/', '', (string) $phone);
        $hashed = mpe_hash_sha256($phone);
        if ($hashed !== '') {
            $user_data['ph'] = [$hashed];
        }
    }

    mpe_send_capi_event('Purchase', $event_id, $custom_data, $user_data);

    $order->update_meta_data('_mpe_capi_purchase_sent', '1');
    $order->save();
}

add_action('woocommerce_thankyou', 'mpe_capi_send_purchase_on_thankyou', 10, 1);
