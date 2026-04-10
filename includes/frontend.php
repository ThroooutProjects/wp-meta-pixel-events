<?php

if (!defined('ABSPATH')) {
    exit;
}

function mpe_enqueue_frontend_assets(): void
{
    $payload = mpe_build_pixel_payload();
    if (!$payload) {
        return;
    }

    $payload['ajaxUrl'] = admin_url('admin-ajax.php');
    $payload['nonce'] = wp_create_nonce('mpe_pixel_event_log');
    $payload['logEnabled'] = (bool) get_option('mpe_pixel_event_log_enabled', false);

    wp_register_script(
        'mpe-tracking',
        MPE_PLUGIN_URL . 'assets/js/mpe-tracking.js',
        ['jquery'],
        MPE_VERSION,
        true
    );

    wp_enqueue_script('mpe-tracking');

    // Expose as window.mpePixelData
    wp_add_inline_script('mpe-tracking', 'window.mpePixelData = ' . wp_json_encode($payload) . ';', 'before');
}

add_action('wp_enqueue_scripts', 'mpe_enqueue_frontend_assets');
