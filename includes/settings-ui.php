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

            <!-- Application (client) ID -->
            <tr>
                <th scope="row">Application (client) ID</th>
                <td>
                    <input type="text" class="regular-text"
                           name="m365_client_id"
                           value="<?php echo esc_attr(get_option('m365_client_id')); ?>"
                           required>

                    <p class="description">
                        Found in <strong> <a href="https://entra.microsoft.com/#home">Microsoft Entra ID</a> → App registrations → [User-created] App → Overview</strong>.<br>
                        This identifies your registered application in Microsoft.
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
                               disabled>
                        <button type="button" class="button" id="m365-change-secret">
                            Change
                        </button>

                        <p class="description">
                            Client secret is already saved.<br>
                            Click <strong>Change</strong> only if you generated a new secret in Microsoft Entra ID.
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

        </table>

        <!-- Save & Authenticate -->
        <p>
            <button type="button"
                    class="button button-primary"
                    id="m365-save-auth">
                Save & Authenticate
            </button>

            <span class="spinner"
                  id="m365-auth-spinner"
                  style="float:none; vertical-align:middle;"></span>
        </p>

        <div id="m365-auth-status"></div>
    </form>


    <!-- ================================================= -->
    <!-- Sender + Test Email (MERGED SECTION) -->
    <!-- ================================================= -->
    <div id="m365-mail-box"
         style="margin-top:30px; <?php echo $is_authed ? '' : 'display:none;'; ?>">

        <h2>Sender Email</h2>

        <p class="description">
            The sender email must be an existing Microsoft 365 mailbox
            with an active inbox and permission to send mail.
        </p>

        <p>
            <input type="email"
                   id="m365-sender-email"
                   class="regular-text"
                   placeholder="sender@yourdomain.com"
                   value="<?php echo $sender; ?>">
        </p>

        <p>
            <button type="button"
                    class="button button-secondary"
                    id="m365-validate-sender">
                Save & Validate Sender
            </button>

            <span class="spinner"
                  id="m365-sender-spinner"
                  style="float:none; vertical-align:middle;"></span>
        </p>

        <div id="m365-sender-status"></div>

        <hr>

        <h2>Test Email</h2>
        <p class="description">
            You must successfully validate a sender email before sending test emails.
        </p>

        <p>
            <input type="email"
                   id="m365-test-email"
                   class="regular-text"
                   value="greenosoft.seo@gmail.com">
        </p>

        <p>
            <button type="button"
                    class="button"
                    id="m365-send-test"
                    <?php echo $sender_ok ? '' : 'disabled'; ?>>
                Send Test Email
            </button>

            <span class="spinner"
                  id="m365-test-spinner"
                  style="float:none; vertical-align:middle;"></span>
        </p>

        <?php if (!$sender_ok): ?>
            <p class="description" style="color:#666;">
                Test email is disabled until a valid sender email is saved and verified.
            </p>
        <?php endif; ?>

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

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const changeBtn = document.getElementById('m365-change-secret');

            if (changeBtn) {
                changeBtn.addEventListener('click', function () {

                    const td = changeBtn.closest('td');

                    td.innerHTML = `
                        <input type="password"
                            class="regular-text"
                            name="m365_client_secret"
                            required
                            autocomplete="new-password">

                        <p class="description">
                            Paste the <strong>new Client Secret Value</strong> generated in
                            Microsoft Entra ID.<br>
                            This value is shown only once.
                        </p>
                    `;
                });
            }

        });
        </script>


<?php } ?>
