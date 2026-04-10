<?php

if (!defined('ABSPATH')) {
    exit;
}

function mpe_register_pixel_log_tools_page(): void
{
    add_management_page(
        'Pixel Event Log',
        'Pixel Event Log',
        'manage_options',
        'mpe-pixel-event-log',
        'mpe_render_pixel_event_log_page'
    );
}

add_action('admin_menu', 'mpe_register_pixel_log_tools_page');

function mpe_render_pixel_event_log_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $enabled = (bool) get_option('mpe_pixel_event_log_enabled', false);
    $log = get_option('mpe_pixel_event_log', []);
    if (!is_array($log)) {
        $log = [];
    }

    $settings_url = admin_url('options-general.php?page=mpe-meta-pixel-events');
    ?>
    <div class="wrap">
        <h1>Pixel Event Log</h1>

        <p>
            Pixel ID: <code><?php echo esc_html(mpe_get_pixel_id()); ?></code>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url($settings_url); ?>">Settings</a>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 16px 0;">
            <?php wp_nonce_field('mpe_pixel_event_log_toggle'); ?>
            <input type="hidden" name="action" value="mpe_pixel_event_log_toggle" />
            <label>
                <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?> />
                Enable event logging (stores last 200 events)
            </label>
            <?php submit_button('Save', 'primary', 'submit', false); ?>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: 16px 0;">
            <?php wp_nonce_field('mpe_pixel_event_log_clear'); ?>
            <input type="hidden" name="action" value="mpe_pixel_event_log_clear" />
            <?php submit_button('Clear log', 'secondary', 'submit', false); ?>
        </form>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 180px;">Time (UTC)</th>
                    <th style="width: 180px;">Event</th>
                    <th style="width: 140px;">Page</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($log)): ?>
                    <tr>
                        <td colspan="4">No events logged yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($log as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row['time'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['event'] ?? ''); ?></td>
                            <td><?php echo esc_html($row['page'] ?? ''); ?></td>
                            <td>
                                <?php
                                $details = $row;
                                unset($details['time'], $details['event'], $details['page']);
                                echo '<code style="white-space:pre-wrap;">' . esc_html(wp_json_encode($details, JSON_UNESCAPED_SLASHES)) . '</code>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function mpe_admin_post_pixel_event_log_toggle(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden');
    }

    check_admin_referer('mpe_pixel_event_log_toggle');

    update_option('mpe_pixel_event_log_enabled', isset($_POST['enabled']) && $_POST['enabled'] === '1');

    wp_safe_redirect(admin_url('tools.php?page=mpe-pixel-event-log'));
    exit;
}

add_action('admin_post_mpe_pixel_event_log_toggle', 'mpe_admin_post_pixel_event_log_toggle');

function mpe_admin_post_pixel_event_log_clear(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden');
    }

    check_admin_referer('mpe_pixel_event_log_clear');

    update_option('mpe_pixel_event_log', []);

    wp_safe_redirect(admin_url('tools.php?page=mpe-pixel-event-log'));
    exit;
}

add_action('admin_post_mpe_pixel_event_log_clear', 'mpe_admin_post_pixel_event_log_clear');

function mpe_ajax_pixel_event_log(): void
{
    if (!get_option('mpe_pixel_event_log_enabled', false)) {
        wp_send_json_success(['enabled' => false]);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'mpe_pixel_event_log')) {
        wp_send_json_error(['error' => 'bad_nonce'], 403);
    }

    $event = isset($_POST['event']) ? sanitize_text_field(wp_unslash($_POST['event'])) : '';
    $page = isset($_POST['page']) ? sanitize_text_field(wp_unslash($_POST['page'])) : '';
    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
    $params_raw = isset($_POST['params']) ? wp_unslash($_POST['params']) : '';

    $params = [];
    if (is_string($params_raw) && $params_raw !== '') {
        $decoded = json_decode($params_raw, true);
        if (is_array($decoded)) {
            $params = mpe_sanitize_pixel_log_params($decoded);
        }
    }

    mpe_store_pixel_log_row([
        'time' => gmdate('Y-m-d H:i:s'),
        'event' => $event,
        'page' => $page,
        'url' => $url,
        'params' => $params,
    ]);

    wp_send_json_success(['stored' => true]);
}

add_action('wp_ajax_mpe_pixel_event_log', 'mpe_ajax_pixel_event_log');
add_action('wp_ajax_nopriv_mpe_pixel_event_log', 'mpe_ajax_pixel_event_log');
