<?php

if (!defined('ABSPATH')) {
    exit;
}

function mpe_get_pixel_id(): string
{
    $saved = get_option('mpe_pixel_id', '');
    $saved = is_string($saved) ? $saved : '';
    $saved = preg_replace('/[^0-9]/', '', $saved);

    if ($saved !== '') {
        return $saved;
    }

    return preg_replace('/[^0-9]/', '', (string) MPE_PIXEL_ID);
}

function mpe_get_client_ip_address(): string
{
    $ip = '';

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim((string) ($parts[0] ?? ''));
    }

    if ($ip === '' && !empty($_SERVER['REMOTE_ADDR'])) {
        $ip = trim((string) $_SERVER['REMOTE_ADDR']);
    }

    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }

    return '';
}

function mpe_hash_sha256($value): string
{
    $value = is_string($value) ? $value : '';
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    return hash('sha256', $value);
}

function mpe_get_event_source_url(): string
{
    $scheme = is_ssl() ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

    return esc_url_raw($scheme . '://' . $host . $uri);
}

function mpe_store_pixel_log_row(array $row): void
{
    $log = get_option('mpe_pixel_event_log', []);
    if (!is_array($log)) {
        $log = [];
    }

    array_unshift($log, $row);
    $log = array_slice($log, 0, 200);
    update_option('mpe_pixel_event_log', $log, false);
}

function mpe_sanitize_pixel_log_params($params): array
{
    if (!is_array($params)) {
        return [];
    }

    $allowed_keys = [
        'value',
        'currency',
        'content_ids',
        'content_type',
        'contents',
        'num_items',
        'order_id',
    ];

    $clean = [];
    foreach ($allowed_keys as $key) {
        if (!array_key_exists($key, $params)) {
            continue;
        }
        $clean[$key] = $params[$key];
    }

    return $clean;
}
