<?php

if (!defined('ABSPATH')) {
    exit;
}

function mpe_register_settings_page(): void
{
    add_options_page(
        'Meta Pixel Events',
        'Meta Pixel Events',
        'manage_options',
        'mpe-meta-pixel-events',
        'mpe_render_settings_page'
    );
}

add_action('admin_menu', 'mpe_register_settings_page');

function mpe_register_settings(): void
{
    register_setting('mpe_settings', 'mpe_pixel_id', [
        'type' => 'string',
        'sanitize_callback' => function ($value) {
            $value = is_string($value) ? $value : '';
            return preg_replace('/[^0-9]/', '', $value);
        },
        'default' => '',
    ]);

    register_setting('mpe_settings', 'mpe_pixel_event_log_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => function ($value) {
            return (bool) $value;
        },
        'default' => false,
    ]);

    register_setting('mpe_settings', 'mpe_advanced_matching_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => function ($value) {
            return (bool) $value;
        },
        'default' => false,
    ]);

    register_setting('mpe_settings', 'mpe_updater_github_token', [
        'type' => 'string',
        'sanitize_callback' => function ($value) {
            return is_string($value) ? trim($value) : '';
        },
        'default' => '',
    ]);

    $event_defaults = [
        'mpe_event_add_to_cart' => true,
        'mpe_event_add_to_wishlist' => true,
        'mpe_event_view_content' => true,
        'mpe_event_initiate_checkout' => true,
        'mpe_event_add_payment_info' => true,
        'mpe_event_purchase' => true,
    ];

    foreach ($event_defaults as $key => $default) {
        register_setting('mpe_settings', $key, [
            'type' => 'boolean',
            'sanitize_callback' => function ($value) {
                return (bool) $value;
            },
            'default' => $default,
        ]);
    }

    register_setting('mpe_settings', 'mpe_capi_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => function ($value) {
            return (bool) $value;
        },
        'default' => false,
    ]);

    register_setting('mpe_settings', 'mpe_capi_access_token', [
        'type' => 'string',
        'sanitize_callback' => function ($value) {
            return is_string($value) ? trim($value) : '';
        },
        'default' => '',
    ]);

    register_setting('mpe_settings', 'mpe_capi_test_event_code', [
        'type' => 'string',
        'sanitize_callback' => function ($value) {
            return is_string($value) ? trim($value) : '';
        },
        'default' => '',
    ]);

    register_setting('mpe_settings', 'mpe_capi_event_view_content', [
        'type' => 'boolean',
        'sanitize_callback' => function ($value) {
            return (bool) $value;
        },
        'default' => false,
    ]);

    register_setting('mpe_settings', 'mpe_capi_event_initiate_checkout', [
        'type' => 'boolean',
        'sanitize_callback' => function ($value) {
            return (bool) $value;
        },
        'default' => false,
    ]);

    register_setting('mpe_settings', 'mpe_capi_event_purchase', [
        'type' => 'boolean',
        'sanitize_callback' => function ($value) {
            return (bool) $value;
        },
        'default' => true,
    ]);

    add_settings_section('mpe_settings_main', 'Configuration', '__return_false', 'mpe-meta-pixel-events');

    add_settings_field('mpe_pixel_id', 'Meta Pixel ID', 'mpe_render_field_pixel_id', 'mpe-meta-pixel-events', 'mpe_settings_main');
    add_settings_field('mpe_pixel_event_log_enabled', 'Event logging', 'mpe_render_field_logging', 'mpe-meta-pixel-events', 'mpe_settings_main');
    add_settings_field('mpe_advanced_matching_enabled', 'Advanced matching', 'mpe_render_field_advanced_matching', 'mpe-meta-pixel-events', 'mpe_settings_main');
    add_settings_field('mpe_events', 'Enabled events', 'mpe_render_field_events', 'mpe-meta-pixel-events', 'mpe_settings_main');

    add_settings_section('mpe_settings_updates', 'Plugin updates', '__return_false', 'mpe-meta-pixel-events');
    add_settings_field('mpe_updater_github_token', 'GitHub token', 'mpe_render_field_updater_github_token', 'mpe-meta-pixel-events', 'mpe_settings_updates');

    add_settings_section('mpe_settings_capi', 'Conversions API (Server-side)', '__return_false', 'mpe-meta-pixel-events');

    add_settings_field('mpe_capi_enabled', 'Enable CAPI', 'mpe_render_field_capi_enabled', 'mpe-meta-pixel-events', 'mpe_settings_capi');
    add_settings_field('mpe_capi_access_token', 'Access token', 'mpe_render_field_capi_token', 'mpe-meta-pixel-events', 'mpe_settings_capi');
    add_settings_field('mpe_capi_test_event_code', 'Test event code', 'mpe_render_field_capi_test_code', 'mpe-meta-pixel-events', 'mpe_settings_capi');
    add_settings_field('mpe_capi_events', 'Send server events', 'mpe_render_field_capi_events', 'mpe-meta-pixel-events', 'mpe_settings_capi');
}

add_action('admin_init', 'mpe_register_settings');

function mpe_render_field_pixel_id(): void
{
    $value = (string) get_option('mpe_pixel_id', '');
    ?>
    <input type="text" name="mpe_pixel_id" value="<?php echo esc_attr($value); ?>" class="regular-text"
        inputmode="numeric" />
    <p class="description">Digits only. If left blank, defaults to the plugin’s built-in Pixel ID.</p>
    <?php
}

function mpe_render_field_logging(): void
{
    $enabled = (bool) get_option('mpe_pixel_event_log_enabled', false);
    ?>
    <label>
        <input type="checkbox" name="mpe_pixel_event_log_enabled" value="1" <?php checked($enabled); ?> />
        Enable Tools → Pixel Event Log
    </label>
    <?php
}

function mpe_render_field_advanced_matching(): void
{
    $enabled = (bool) get_option('mpe_advanced_matching_enabled', false);
    ?>
    <label>
        <input type="checkbox" name="mpe_advanced_matching_enabled" value="1" <?php checked($enabled); ?> />
        Enable advanced matching (send email/phone when available)
    </label>
    <p class="description">Only enable if your consent/privacy setup allows sending this customer data to Meta.</p>
    <?php
}

function mpe_render_field_updater_github_token(): void
{
    $value = (string) get_option('mpe_updater_github_token', '');
    ?>
    <input type="password" name="mpe_updater_github_token" value="<?php echo esc_attr($value); ?>" class="regular-text"
        autocomplete="off" />
    <p class="description">Only needed if the GitHub repository is private (GitHub API returns 404 without auth).</p>
    <?php
}

function mpe_render_field_events(): void
{
    $events = [
        'mpe_event_view_content' => 'ViewContent',
        'mpe_event_add_to_cart' => 'AddToCart',
        'mpe_event_add_to_wishlist' => 'AddToWishlist',
        'mpe_event_initiate_checkout' => 'InitiateCheckout',
        'mpe_event_add_payment_info' => 'AddPaymentInfo',
        'mpe_event_purchase' => 'Purchase',
    ];

    foreach ($events as $opt => $label) {
        $enabled = (bool) get_option($opt, true);
        ?>
        <label style="display:block; margin: 4px 0;">
            <input type="checkbox" name="<?php echo esc_attr($opt); ?>" value="1" <?php checked($enabled); ?> />
            <?php echo esc_html($label); ?>
        </label>
        <?php
    }

    ?>
    <p class="description">Disable events you don’t want to send to Meta Pixel.</p>
    <?php
}

function mpe_render_field_capi_enabled(): void
{
    $enabled = (bool) get_option('mpe_capi_enabled', false);
    ?>
    <label>
        <input type="checkbox" name="mpe_capi_enabled" value="1" <?php checked($enabled); ?> />
        Enable Meta Conversions API
    </label>
    <?php
}

function mpe_render_field_capi_token(): void
{
    $value = (string) get_option('mpe_capi_access_token', '');
    ?>
    <input type="password" name="mpe_capi_access_token" value="<?php echo esc_attr($value); ?>" class="regular-text"
        autocomplete="off" />
    <p class="description">Required to send server-side events.</p>
    <?php
}

function mpe_render_field_capi_test_code(): void
{
    $value = (string) get_option('mpe_capi_test_event_code', '');
    ?>
    <input type="text" name="mpe_capi_test_event_code" value="<?php echo esc_attr($value); ?>" class="regular-text" />
    <p class="description">Optional: use only while testing in Events Manager → Test events.</p>
    <?php
}

function mpe_render_field_capi_events(): void
{
    $events = [
        'mpe_capi_event_purchase' => 'Purchase (recommended)',
        'mpe_capi_event_view_content' => 'ViewContent (optional)',
        'mpe_capi_event_initiate_checkout' => 'InitiateCheckout (optional)',
    ];

    foreach ($events as $opt => $label) {
        $enabled = (bool) get_option($opt, ($opt === 'mpe_capi_event_purchase'));
        ?>
        <label style="display:block; margin: 4px 0;">
            <input type="checkbox" name="<?php echo esc_attr($opt); ?>" value="1" <?php checked($enabled); ?> />
            <?php echo esc_html($label); ?>
        </label>
        <?php
    }

    ?>
    <p class="description">Purchase is sent server-side on WooCommerce Thank You and uses an event_id for deduplication.</p>
    <?php
}

function mpe_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Meta Pixel Events</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('mpe_settings');
            do_settings_sections('mpe-meta-pixel-events');
            submit_button();
            ?>
        </form>
        <p>Current effective Pixel ID: <code><?php echo esc_html(mpe_get_pixel_id()); ?></code></p>
    </div>
    <?php
}
