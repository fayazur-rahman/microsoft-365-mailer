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

    <form method="post" onsubmit="return false;" style="margin-top:20px;">
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

        <!-- Save & Authenticate -->
        <p>
            <button type="button"
                    class="button button-primary"
                    id="m365-save-auth">
                Save & Authenticate
            </button>

            <span id="m365-auth-spinner"
                  class="spinner"
                  style="float:none;"></span>
        </p>

        <div id="m365-auth-status" style="margin-top:10px;"></div>
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

        <span id="m365-test-spinner"
              class="spinner"
              style="float:none; margin-top:10px;"></span>

        <div id="m365-test-result" style="margin-top:12px;"></div>
    </div>

    <!-- ============================= -->
    <!-- JavaScript (AJAX + UI) -->
    <!-- ============================= -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        /* ------------------------------
         * Change Client Secret
         * ------------------------------ */
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

        /* ------------------------------
         * AJAX: Send Test Email
         * ------------------------------ */
        const testBtn   = document.getElementById('m365-send-test-btn');
        const testEmail = document.getElementById('m365-test-email');
        const testSpin  = document.getElementById('m365-test-spinner');
        const testRes   = document.getElementById('m365-test-result');

        if (testBtn) {
            testBtn.addEventListener('click', function () {

                testRes.innerHTML = '';
                testSpin.classList.add('is-active');
                testBtn.disabled = true;

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'm365_send_test_email',
                        nonce: '<?php echo wp_create_nonce('m365_test_email_nonce'); ?>',
                        email: testEmail.value
                    })
                })
                .then(res => res.json())
                .then(data => {

                    testSpin.classList.remove('is-active');
                    testBtn.disabled = false;

                    if (data.success) {
                        testRes.innerHTML =
                            '<div style="color:#0a7d00;font-weight:600;">✔ ' +
                            data.data.message + '</div>';
                    } else {
                        testRes.innerHTML =
                            '<div style="color:#b32d2e;font-weight:600;">✖ Failed</div>' +
                            '<div style="margin-top:6px;color:#555;">' +
                            data.data.message + '</div>';
                    }
                })
                .catch(() => {
                    testSpin.classList.remove('is-active');
                    testBtn.disabled = false;
                    testRes.innerHTML =
                        '<div style="color:#b32d2e;">Unexpected error occurred.</div>';
                });
            });
        }

        /* ------------------------------
         * AJAX: Save & Authenticate
         * ------------------------------ */
        const authBtn   = document.getElementById('m365-save-auth');
        const authSpin  = document.getElementById('m365-auth-spinner');
        const authStat  = document.getElementById('m365-auth-status');

        if (authBtn) {
            authBtn.addEventListener('click', function () {

                authStat.innerHTML = '';
                authSpin.classList.add('is-active');
                authBtn.disabled = true;

                const form = authBtn.closest('form');
                const data = new FormData(form);

                data.append('action', 'm365_save_and_auth');
                data.append('nonce', '<?php echo wp_create_nonce('m365_save_auth_nonce'); ?>');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                })
                .then(res => res.json())
                .then(data => {

                    authSpin.classList.remove('is-active');
                    authBtn.disabled = false;

                    authStat.innerHTML = data.success
                        ? '<p style="color:#0a7d00;font-weight:600;">✔ ' + data.data.message + '</p>'
                        : '<p style="color:#d63638;font-weight:600;">✖ ' + data.data.message + '</p>';
                })
                .catch(() => {
                    authSpin.classList.remove('is-active');
                    authBtn.disabled = false;
                    authStat.innerHTML =
                        '<p style="color:#d63638;">✖ Unexpected error occurred.</p>';
                });
            });
        }

    });
    </script>

    <?php
}
