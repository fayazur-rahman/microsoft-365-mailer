<?php
/**
 * Settings UI for Microsoft 365 Mailer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper: check if client secret exists
 */
function m365_has_client_secret() {
    return !empty(get_option('m365_client_secret'));
}

/**
 * ==================================================
 * Render Settings Tab
 * ==================================================
 */
function m365_render_settings_tab() {
    ?>

    <form method="post" action="options.php" style="margin-top:20px;">
        <?php settings_fields('m365_mailer'); ?>

        <table class="form-table">

            <!-- Application (client) ID -->
            <tr>
                <th scope="row">Application (client) ID</th>
                <td>
                    <input type="text"
                           class="regular-text"
                           name="m365_client_id"
                           value="<?php echo esc_attr(get_option('m365_client_id')); ?>"
                           required>

                    <p class="description">
                        Found in <strong>Microsoft Entra ID → App registrations → Overview</strong>.<br>
                        Not sure where to get this?
                        <a href="?page=m365-mailer&tab=guide">Read the setup guide</a>.
                    </p>
                </td>
            </tr>

            <!-- Directory (tenant) ID -->
            <tr>
                <th scope="row">Directory (tenant) ID</th>
                <td>
                    <input type="text"
                           class="regular-text"
                           name="m365_tenant_id"
                           value="<?php echo esc_attr(get_option('m365_tenant_id')); ?>"
                           required>

                    <p class="description">
                        Identifies your Microsoft 365 tenant.<br>
                        Available on the same page as the Application (client) ID.
                    </p>
                </td>
            </tr>

            <!-- Client Secret -->
            <tr>
                <th scope="row">Client Secret Value</th>
                <td>

                    <?php if (m365_has_client_secret()) : ?>

                        <input type="password"
                               class="regular-text"
                               value="****************"
                               disabled
                               style="background:#f0f0f1;">

                        <button type="button"
                                class="button"
                                id="m365-change-secret">
                            Change
                        </button>

                        <p class="description">
                            Client secret is already saved.
                            Click <strong>Change</strong> only if you generated a new one.
                        </p>

                    <?php else : ?>

                        <input type="password"
                               class="regular-text"
                               name="m365_client_secret"
                               required
                               autocomplete="new-password">

                        <p class="description">
                            Paste the <strong>Client Secret Value</strong> (not the Secret ID).<br>
                            This value is shown only once in Microsoft Entra ID.
                        </p>

                    <?php endif; ?>

                </td>
            </tr>

            <!-- Sender Email -->
            <tr>
                <th scope="row">Sender Email</th>
                <td>
                    <input type="email"
                           class="regular-text"
                           name="m365_from_email"
                           value="<?php echo esc_attr(get_option('m365_from_email')); ?>"
                           placeholder="admin@domain.com"
                           required>

                    <p class="description">
                        Must be an existing Microsoft 365 mailbox with an active inbox.<br>
                        <em>The sender name is controlled by Microsoft 365 and cannot be changed here.</em>
                    </p>
                </td>
            </tr>

        </table>

        <?php submit_button('Save Changes'); ?>
    </form>

    <hr>

    <!-- Test Email -->
    <h2>Send Test Email</h2>

    <form method="post">
        <?php wp_nonce_field('m365_test_email'); ?>

        <input type="email"
               name="m365_test_email_to"
               class="regular-text"
               placeholder="recipient@example.com"
               required>

        <?php submit_button('Send Test Email', 'secondary', 'm365_send_test'); ?>
    </form>

    <!-- Change Secret JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('m365-change-secret');
        if (!btn) return;

        btn.addEventListener('click', function () {
            btn.parentNode.innerHTML = `
                <input type="password"
                       class="regular-text"
                       name="m365_client_secret"
                       required
                       autocomplete="new-password">
                <p class="description">
                    Enter a new client secret value and save changes.
                </p>
            `;
        });
    });
    </script>

    <?php
}

/**
 * ==================================================
 * Handle Test Email Submit
 * ==================================================
 */
add_action('admin_init', function () {

    if (!isset($_POST['m365_send_test'])) {
        return;
    }

    check_admin_referer('m365_test_email');

    $to = sanitize_email($_POST['m365_test_email_to']);

    if (empty($to)) {
        return;
    }

    m365_send_mail(
        $to,
        'Microsoft 365 Mailer – Test Email',
        '<h2>Success :-) </h2><p>This is a <strong>HTML test email</strong> sent via Microsoft 365 Mailer.</p>'
    );

    add_action('admin_notices', function () {
        echo '<div class="notice notice-success"><p>Test email sent successfully.</p></div>';
    });
});
