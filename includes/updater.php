<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitHub updater integration.
 *
 * Uses the Plugin Update Checker library (YahnisElsts/plugin-update-checker).
 * The library is optional: if it's not installed, this file does nothing.
 */
function mpe_boot_updater(): void
{
    $factory_candidates = [
        'YahnisElsts\\PluginUpdateChecker\\v6\\PucFactory',
        'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory',
    ];

    $factory_class = null;
    foreach ($factory_candidates as $candidate) {
        if (class_exists($candidate)) {
            $factory_class = $candidate;
            break;
        }
    }

    if (!$factory_class) {
        return;
    }

    // @phpstan-ignore-next-line
    $updateChecker = $factory_class::buildUpdateChecker(
        'https://github.com/ThroooutProjects/wp-meta-pixel-events/',
        MPE_PLUGIN_FILE,
        'meta-pixel-events'
    );

    if (is_object($updateChecker) && method_exists($updateChecker, 'setBranch')) {
        $updateChecker->setBranch('main');
    }

    // If you create GitHub Releases with a ZIP asset (recommended), use it.
    if (is_object($updateChecker) && method_exists($updateChecker, 'getVcsApi')) {
        $vcsApi = $updateChecker->getVcsApi();
        if (is_object($vcsApi) && method_exists($vcsApi, 'enableReleaseAssets')) {
            $vcsApi->enableReleaseAssets('/\.zip($|[?&#])/i');
        }
    }

    // Private repo support: Prefer a constant (wp-config.php) over a DB option.
    $token = '';
    if (defined('MPE_GITHUB_TOKEN')) {
        $token = (string) MPE_GITHUB_TOKEN;
    }
    if (!is_string($token) || trim($token) === '') {
        $token = get_option('mpe_updater_github_token', '');
    }
    $token = is_string($token) ? trim($token) : '';
    if ($token !== '' && is_object($updateChecker) && method_exists($updateChecker, 'setAuthentication')) {
        $updateChecker->setAuthentication($token);
    }

    // Optional: for private repos you can add a token.
    // if (is_object($updateChecker) && method_exists($updateChecker, 'setAuthentication')) {
    //     $updateChecker->setAuthentication('ghp_...');
    // }
}

add_action('plugins_loaded', 'mpe_boot_updater');
