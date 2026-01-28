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

    <!-- ============================= -->
    <!-- AJAX Test Email Section -->
    <!-- ============================= -->

    <h2>Send Test Email</h2>

    <div id="m365-test-email-box" style="max-width:420px;">

        <input type="email"
               id="m365-test-email"
               class="regular-text"
               placeholder="recipient@example.com">

        <button type="button"
                class="button button-secondary"
                id="m365-send-test-btn"
                style="margin-top:10px;">
            Send Test Email
        </button>

        <span class="spinner"
              id="m365-test-spinner"
              style="float:none; margin-top:10px; display:none;"></span>

        <div id="m365-test-result" style="margin-top:12px;"></div>
    </div>

    <!-- ============================= -->
    <!-- Change Secret JS -->
    <!-- ============================= -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        /* Change Client Secret */
        const changeBtn = document.getElementById('m365-change-secret');
        if (changeBtn) {
            changeBtn.addEventListener('click', function () {
                changeBtn.parentNode.innerHTML = `
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
        }

        /* AJAX Test Email */
        const btn     = document.getElementById('m365-send-test-btn');
        const email   = document.getElementById('m365-test-email');
        const spinner = document.getElementById('m365-test-spinner');
        const result  = document.getElementById('m365-test-result');

        if (!btn) return;

        btn.addEventListener('click', function () {

            result.innerHTML = '';
            spinner.style.display = 'inline-block';
            btn.disabled = true;

            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'm365_send_test_email',
                    nonce: '<?php echo wp_create_nonce('m365_test_email_nonce'); ?>',
                    email: email.value
                })
            })
            .then(response => response.json())
            .then(data => {

                spinner.style.display = 'none';
                btn.disabled = false;

                if (data.success) {
                    result.innerHTML =
                        '<div style="color:#0a7d00;font-weight:600;">✔ ' +
                        data.data.message +
                        '</div>';
                } else {
                    result.innerHTML =
                        '<div style="color:#b32d2e;font-weight:600;">✖ Failed</div>' +
                        '<div style="margin-top:6px;color:#555;">' +
                        data.data.message +
                        '</div>';
                }
            })
            .catch(() => {
                spinner.style.display = 'none';
                btn.disabled = false;
                result.innerHTML =
                    '<div style="color:#b32d2e;">Unexpected error occurred.</div>';
            });
        });

    });
    </script>

    <?php
}
