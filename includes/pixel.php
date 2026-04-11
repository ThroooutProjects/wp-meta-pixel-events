<?php

if (!defined('ABSPATH')) {
    exit;
}

function mpe_load_meta_pixel(): void
{
    $pixel_id = mpe_get_pixel_id();
    if ($pixel_id === '') {
        return;
    }

    $advanced = [];
    if ((bool) get_option('mpe_advanced_matching_enabled', false)) {
        // Logged-in user email (common, low-effort match key)
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if ($user && !empty($user->user_email)) {
                $advanced['em'] = (string) $user->user_email;
            }
            $billing_phone = get_user_meta(get_current_user_id(), 'billing_phone', true);
            if (is_string($billing_phone) && $billing_phone !== '') {
                $advanced['ph'] = preg_replace('/[^0-9+]/', '', $billing_phone);
            }
        }

        // WooCommerce customer session (may be available on checkout)
        if (function_exists('WC') && WC() && isset(WC()->customer) && WC()->customer) {
            $wc_email = WC()->customer->get_billing_email();
            if (is_string($wc_email) && $wc_email !== '') {
                $advanced['em'] = $wc_email;
            }
            $wc_phone = WC()->customer->get_billing_phone();
            if (is_string($wc_phone) && $wc_phone !== '') {
                $advanced['ph'] = preg_replace('/[^0-9+]/', '', $wc_phone);
            }
        }

        // Normalize: drop empties
        foreach (['em', 'ph'] as $k) {
            if (!isset($advanced[$k])) {
                continue;
            }
            $advanced[$k] = trim((string) $advanced[$k]);
            if ($advanced[$k] === '') {
                unset($advanced[$k]);
            }
        }
    }
    ?>
    <script>
        !function (f, b, e, v, n, t, s) {
            if (f.fbq) return; n = f.fbq = function () {
                n.callMethod ?
                    n.callMethod.apply(n, arguments) : n.queue.push(arguments)
            };
            if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0';
            n.queue = []; t = b.createElement(e); t.async = !0;
            t.src = v; s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s)
        }(window, document, 'script',
            'https://connect.facebook.net/en_US/fbevents.js');

        <?php if (!empty($advanced)): ?>
            fbq('init', '<?php echo esc_js($pixel_id); ?>', <?php echo wp_json_encode($advanced); ?>);
        <?php else: ?>
            fbq('init', '<?php echo esc_js($pixel_id); ?>');
        <?php endif; ?>
        fbq('track', 'PageView');
    </script>
    <noscript>
        <img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=<?php echo rawurlencode($pixel_id); ?>&ev=PageView&noscript=1" />
    </noscript>
    <?php
}

add_action('wp_head', 'mpe_load_meta_pixel');
