<?php
/**
 * Plugin Name: Microsoft 365 Mailer
 * Description: A reliable, SMTP-free alternative for sending WordPress emails through Microsoft 365 using Microsoft Graph API and Application permissions.
 * Version: 1.2.2
 * Author: MD Fayazur Rahman
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==================================================
 * Constants
 * ==================================================
 */
define('M365_MAILER_PATH', plugin_dir_path(__FILE__));
define('M365_MAILER_URL', plugin_dir_url(__FILE__));

/**
 * ==================================================
 * Plugin action links (Plugin page)
 * ==================================================
 */

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift(
        $links,
        '<a href="' . admin_url('options-general.php?page=m365-mailer') . '">Settings</a>'
    );
    return $links;
});

/**
 * ==================================================
 * Helpers
 * ==================================================
 */
function m365_get_active_tab() {
    $tab = $_GET['tab'] ?? 'settings';
    return in_array($tab, ['settings', 'guide', 'logs'], true) ? $tab : 'settings';
}

/**
 * Preserve client secret unless explicitly changed
 */
add_filter('pre_update_option_m365_client_secret', function ($new, $old) {
    return empty($new) ? $old : $new;
}, 10, 2);

/**
 * ==================================================
 * Admin Menu & Settings Registration
 * ==================================================
 */
add_action('admin_menu', function () {
    add_options_page(
        'Microsoft 365 Mailer',
        'Microsoft 365 Mailer',
        'manage_options',
        'm365-mailer',
        'm365_render_admin_page'
    );
});

add_action('admin_init', function () {
    register_setting('m365_mailer', 'm365_client_id');
    register_setting('m365_mailer', 'm365_tenant_id');
    register_setting('m365_mailer', 'm365_client_secret');
    register_setting('m365_mailer', 'm365_from_email');
});

/**
 * ==================================================
 * Load core functionality
 * ==================================================
 */
require_once M365_MAILER_PATH . 'includes/graph-mailer.php';
require_once M365_MAILER_PATH . 'includes/settings-ui.php';
require_once M365_MAILER_PATH . 'includes/guide-ui.php';
require_once M365_MAILER_PATH . 'includes/logs.php';

/**
 * ==================================================
 * Admin Page Renderer (Routing only)
 * ==================================================
 */
function m365_render_admin_page() {

    if (!current_user_can('manage_options')) {
        return;
    }

    $active_tab = m365_get_active_tab();
    ?>
    <div class="wrap">
        <h1>Microsoft 365 Mailer</h1>

        <h2 class="nav-tab-wrapper">
            <a href="?page=m365-mailer&tab=settings"
               class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                Settings
            </a>

            <a href="?page=m365-mailer&tab=guide"
               class="nav-tab <?php echo $active_tab === 'guide' ? 'nav-tab-active' : ''; ?>">
                How to get the values
            </a>

            <a href="?page=m365-mailer&tab=logs"
               class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                Logs
            </a>
        </h2>

        <?php
        switch ($active_tab) {

            case 'guide':
                m365_render_guide_tab();
                break;

            case 'logs':
                m365_render_logs_tab();
                break;

            case 'settings':
            default:
                m365_render_settings_tab();
                break;
        }
        ?>
    </div>
    <?php
}

/**
 * ==================================================
 * Force token refresh when credentials change
 * ==================================================
 */
function m365_clear_graph_token() {
    delete_transient('m365_graph_token');
}

add_action('update_option_m365_client_id', 'm365_clear_graph_token', 10, 0);
add_action('update_option_m365_client_secret', 'm365_clear_graph_token', 10, 0);
add_action('update_option_m365_tenant_id', 'm365_clear_graph_token', 10, 0);
add_action('update_option_m365_from_email', 'm365_clear_graph_token', 10, 0);



/**
 * ==================================================
 * GitHub Plugin Auto Updates
 * ==================================================
 */
require_once M365_MAILER_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
    'https://github.com/fayazur-rahman/microsoft-365-mailer/',
    __FILE__,
    'microsoft-365-mailer'
);
