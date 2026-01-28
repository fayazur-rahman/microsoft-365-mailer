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

    $is_authenticated   = (bool) get_option('m365_is_authenticated');
    $sender_validated   = (bool) get_option('m365_sender_validated');
    $saved_sender_email = get_option('m365_from_email');
    ?>

    <form method="post" onsubmit="return false;" style="margin-top:20px;">
        <?php settings_fields('m365_mailer'); ?>

        <table class="form-table">

            <!-- Application (client) ID -->
            <tr>
                <th scope="row">Application (client) ID</th>
                <td>
                    <input type="text" class="regular-text"
                           name="m365_client_id"
                           value="<?php echo esc_attr(get_option('m365_client_id')); ?>"
                           required>

                    <p class="description">
                        Found in <strong>Microsoft Entra ID → App registrations → Overview</strong>.
                        <a href="?page=m365-mailer&tab=guide">Read the setup guide</a>.
                    </p>
                </td>
            </tr>

            <!-- Directory (tenant) ID -->
            <tr>
                <th scope="row">Directory (tenant) ID</th>
                <td>
                    <input type="text" class="regular-text"
                           name="m365_tenant_id"
                           value="<?php echo esc_attr(get_option('m365_tenant_id')); ?>"
                           required>
                </td>
            </tr>

            <!-- Client Secret -->
            <tr>
                <th scope="row">Client Secret Value</th>
                <td>
                    <?php if (m365_has_client_secret()) : ?>
                        <input type="password" class="regular-text"
                               value="****************" disabled>
                        <button type="button" class="button" id="m365-change-secret">
                            Change
                        </button>
                    <?php else : ?>
                        <input type="password" class="regular-text"
                               name="m365_client_secret"
                               required autocomplete="new-password">
                    <?php endif; ?>
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
            <span class="spinner" id="m365-auth-spinner"></span>
        </p>

        <div id="m365-auth-status">
            <?php if ($is_authenticated) : ?>
                <p style="color:#0a7d00;font-weight:600;">
                    ✔ Successfully authenticated with Microsoft 365.
                </p>
            <?php endif; ?>
        </div>
    </form>

    <!-- ============================= -->
    <!-- Sender Email (persistent) -->
    <!-- ============================= -->
    <?php if ($is_authenticated) : ?>
        <div id="m365-sender-box" style="margin-top:30px;">
            <h2>Sender Email</h2>

            <input type="email"
                   id="m365-sender-email"
                   class="regular-text"
                   value="<?php echo esc_attr($saved_sender_email); ?>"
                   placeholder="sender@domain.com">

            <p>
                <button type="button"
                        class="button button-secondary"
                        id="m365-validate-sender">
                    Save & Validate Sender
                </button>
                <span class="spinner" id="m365-sender-spinner"></span>
            </p>

            <div id="m365-sender-status">
                <?php if ($sender_validated) : ?>
                    <p style="color:#0a7d00;font-weight:600;">
                        ✔ Sender email validated successfully.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================= -->
    <!-- Test Email (persistent) -->
    <!-- ============================= -->
    <?php if ($is_authenticated && $sender_validated) : ?>
        <div id="m365-test-box" style="margin-top:30px;">
            <h2>Send Test Email</h2>

            <input type="email"
                   id="m365-test-email"
                   class="regular-text"
                   placeholder="recipient@example.com">

            <p>
                <button type="button"
                        class="button"
                        id="m365-send-test">
                    Send Test Email
                </button>
                <span class="spinner" id="m365-test-spinner"></span>
            </p>

            <div id="m365-test-result"></div>
        </div>
    <?php endif; ?>

    <!-- ============================= -->
    <!-- JavaScript (AJAX only) -->
    <!-- ============================= -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        /* Change Client Secret */
        const changeBtn = document.getElementById('m365-change-secret');
        if (changeBtn) {
            changeBtn.addEventListener('click', function () {
                changeBtn.parentNode.innerHTML = `
                    <input type="password" class="regular-text"
                           name="m365_client_secret"
                           required autocomplete="new-password">
                    <p class="description">
                        Enter a new client secret value and authenticate again.
                    </p>
                `;
            });
        }

        /* Save & Authenticate */
        const authBtn  = document.getElementById('m365-save-auth');
        const authSpin = document.getElementById('m365-auth-spinner');
        const authStat = document.getElementById('m365-auth-status');

        if (authBtn) {
            authBtn.addEventListener('click', function () {

                authSpin.classList.add('is-active');
                authBtn.disabled = true;
                authStat.innerHTML = '';

                const data = new FormData(authBtn.closest('form'));
                data.append('action', 'm365_save_and_auth');
                data.append('nonce', '<?php echo wp_create_nonce('m365_save_auth_nonce'); ?>');

                fetch(ajaxurl, { method: 'POST', body: data })
                .then(r => r.json())
                .then(r => {
                    authSpin.classList.remove('is-active');
                    authBtn.disabled = false;

                    authStat.innerHTML = r.success
                        ? '<p style="color:#0a7d00;font-weight:600;">✔ ' + r.data.message + '</p>'
                        : '<p style="color:#d63638;font-weight:600;">✖ ' + r.data.message + '</p>';

                    if (r.success) {
                        location.reload(); // reload to reflect persistent state
                    }
                });
            });
        }

        /* Validate Sender */
        const senderBtn  = document.getElementById('m365-validate-sender');
        const senderSpin = document.getElementById('m365-sender-spinner');
        const senderInp  = document.getElementById('m365-sender-email');
        const senderRes  = document.getElementById('m365-sender-status');

        if (senderBtn) {
            senderBtn.addEventListener('click', function () {

                senderSpin.classList.add('is-active');
                senderBtn.disabled = true;
                senderRes.innerHTML = '';

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'm365_validate_sender',
                        nonce: '<?php echo wp_create_nonce('m365_validate_sender_nonce'); ?>',
                        sender: senderInp.value
                    })
                })
                .then(r => r.json())
                .then(r => {
                    senderSpin.classList.remove('is-active');
                    senderBtn.disabled = false;

                    senderRes.innerHTML = r.success
                        ? '<p style="color:#0a7d00;font-weight:600;">✔ ' + r.data.message + '</p>'
                        : '<p style="color:#d63638;font-weight:600;">✖ ' + r.data.message + '</p>';

                    if (r.success) {
                        location.reload();
                    }
                });
            });
        }

        /* Test Email */
        const testBtn  = document.getElementById('m365-send-test');
        const testSpin = document.getElementById('m365-test-spinner');
        const testInp  = document.getElementById('m365-test-email');
        const testRes  = document.getElementById('m365-test-result');

        if (testBtn) {
            testBtn.addEventListener('click', function () {

                testSpin.classList.add('is-active');
                testBtn.disabled = true;
                testRes.innerHTML = '';

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'm365_send_test_email',
                        nonce: '<?php echo wp_create_nonce('m365_test_email_nonce'); ?>',
                        email: testInp.value
                    })
                })
                .then(r => r.json())
                .then(r => {
                    testSpin.classList.remove('is-active');
                    testBtn.disabled = false;

                    testRes.innerHTML = r.success
                        ? '<p style="color:#0a7d00;font-weight:600;">✔ ' + r.data.message + '</p>'
                        : '<p style="color:#d63638;font-weight:600;">✖ ' + r.data.message + '</p>';
                });
            });
        }

    });
    </script>

    <?php
}
