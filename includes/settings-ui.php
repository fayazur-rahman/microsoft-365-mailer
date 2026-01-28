<?php
/**
 * Settings UI for Microsoft 365 Mailer
 */

if (!defined('ABSPATH')) {
    exit;
}

function m365_has_client_secret() {
    return !empty(get_option('m365_client_secret'));
}

function m365_render_settings_tab() {

    $is_authed  = (bool) get_option('m365_is_authenticated');
    $sender     = esc_attr(get_option('m365_from_email'));
    $sender_ok  = (bool) get_option('m365_sender_validated');
    ?>

    <form method="post" onsubmit="return false;" style="margin-top:20px;">
        <?php settings_fields('m365_mailer'); ?>

        <table class="form-table">

            <tr>
                <th>Application (client) ID</th>
                <td>
                    <input type="text" class="regular-text"
                           name="m365_client_id"
                           value="<?php echo esc_attr(get_option('m365_client_id')); ?>" required>
                </td>
            </tr>

            <tr>
                <th>Directory (tenant) ID</th>
                <td>
                    <input type="text" class="regular-text"
                           name="m365_tenant_id"
                           value="<?php echo esc_attr(get_option('m365_tenant_id')); ?>" required>
                </td>
            </tr>

            <tr>
                <th>Client Secret Value</th>
                <td>
                    <?php if (m365_has_client_secret()) : ?>
                        <input type="password" class="regular-text" value="****************" disabled>
                        <button type="button" class="button" id="m365-change-secret">Change</button>
                    <?php else : ?>
                        <input type="password" class="regular-text"
                               name="m365_client_secret" required autocomplete="new-password">
                    <?php endif; ?>
                </td>
            </tr>

        </table>

        <p>
            <button type="button" class="button button-primary" id="m365-save-auth">
                Save & Authenticate
            </button>
            <span class="spinner" id="m365-auth-spinner"
                  style="float:none; vertical-align:middle;"></span>
        </p>

        <div id="m365-auth-status"></div>
    </form>

    <!-- ================================================= -->
    <!-- Sender + Test Email (MERGED SECTION) -->
    <!-- ================================================= -->
    <div id="m365-mail-box"
         style="margin-top:30px; <?php echo $is_authed ? '' : 'display:none;'; ?>">

        <h2>Sender & Test Email</h2>

        <p>
            <input type="email"
                   id="m365-sender-email"
                   class="regular-text"
                   placeholder="sender@domain.com"
                   value="<?php echo $sender; ?>">
        </p>

        <p>
            <button type="button"
                    class="button button-secondary"
                    id="m365-validate-sender">
                Save & Validate Sender
            </button>
            <span class="spinner" id="m365-sender-spinner"
                  style="float:none; vertical-align:middle;"></span>
        </p>

        <div id="m365-sender-status"></div>

        <hr>

        <p>
            <input type="email"
                   id="m365-test-email"
                   class="regular-text"
                   placeholder="recipient@example.com">
        </p>

        <p>
            <button type="button"
                    class="button"
                    id="m365-send-test"
                    <?php echo $sender_ok ? '' : 'disabled'; ?>>
                Send Test Email
            </button>
            <span class="spinner" id="m365-test-spinner"
                  style="float:none; vertical-align:middle;"></span>
        </p>

        <div id="m365-test-result"></div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const authBtn   = document.getElementById('m365-save-auth');
        const authSpin  = document.getElementById('m365-auth-spinner');
        const authStat  = document.getElementById('m365-auth-status');
        const mailBox   = document.getElementById('m365-mail-box');

        const senderBtn = document.getElementById('m365-validate-sender');
        const senderInp = document.getElementById('m365-sender-email');
        const senderSpin= document.getElementById('m365-sender-spinner');
        const senderRes = document.getElementById('m365-sender-status');

        const testBtn   = document.getElementById('m365-send-test');
        const testInp   = document.getElementById('m365-test-email');
        const testSpin  = document.getElementById('m365-test-spinner');
        const testRes   = document.getElementById('m365-test-result');

        /* Save & Authenticate */
        authBtn.onclick = () => {
            authSpin.classList.add('is-active');
            authBtn.disabled = true;
            authStat.innerHTML = '';

            const data = new FormData(authBtn.closest('form'));
            data.append('action', 'm365_save_and_auth');
            data.append('nonce', '<?php echo wp_create_nonce('m365_save_auth_nonce'); ?>');

            fetch(ajaxurl, { method:'POST', body:data })
            .then(r => r.json())
            .then(r => {
                authSpin.classList.remove('is-active');
                authBtn.disabled = false;

                if (r.success) {
                    authStat.innerHTML = '<p style="color:green;">✔ '+r.data.message+'</p>';
                    mailBox.style.display = 'block';
                } else {
                    authStat.innerHTML = '<p style="color:red;">✖ '+r.data.message+'</p>';
                    mailBox.style.display = 'none';
                    testBtn.disabled = true;
                }
            });
        };

        /* Validate Sender */
        senderBtn.onclick = () => {
            senderSpin.classList.add('is-active');
            senderBtn.disabled = true;
            senderRes.innerHTML = '';

            fetch(ajaxurl, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({
                    action:'m365_validate_sender',
                    nonce:'<?php echo wp_create_nonce('m365_validate_sender_nonce'); ?>',
                    sender:senderInp.value
                })
            })
            .then(r => r.json())
            .then(r => {
                senderSpin.classList.remove('is-active');
                senderBtn.disabled = false;

                if (r.success) {
                    senderRes.innerHTML = '<p style="color:green;">✔ '+r.data.message+'</p>';
                    testBtn.disabled = false;
                } else {
                    senderRes.innerHTML = '<p style="color:red;">✖ '+r.data.message+'</p>';
                    testBtn.disabled = true;
                }
            });
        };

        /* Send Test Email */
        testBtn.onclick = () => {
            testSpin.classList.add('is-active');
            testBtn.disabled = true;
            testRes.innerHTML = '';

            fetch(ajaxurl, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({
                    action:'m365_send_test_email',
                    nonce:'<?php echo wp_create_nonce('m365_test_email_nonce'); ?>',
                    email:testInp.value
                })
            })
            .then(r => r.json())
            .then(r => {
                testSpin.classList.remove('is-active');
                testBtn.disabled = false;

                testRes.innerHTML = r.success
                    ? '<p style="color:green;">✔ '+r.data.message+'</p>'
                    : '<p style="color:red;">✖ '+r.data.message+'</p>';
            });
        };
    });
    </script>

<?php } ?>
