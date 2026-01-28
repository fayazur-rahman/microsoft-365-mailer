<?php
/**
 * Microsoft Graph Mailer Core
 * Handles:
 * - OAuth token retrieval (client_credentials)
 * - Microsoft Graph sendMail
 * - wp_mail override
 * - Headers, attachments, HTML handling
 * - AJAX auth & sender validation
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==================================================
 * Access Token (cached)
 * ==================================================
 */
function m365_get_access_token() {

    $cached = get_transient('m365_graph_token');
    if ($cached) {
        return $cached;
    }

    $tenant_id     = get_option('m365_tenant_id');
    $client_id     = get_option('m365_client_id');
    $client_secret = get_option('m365_client_secret');

    if (!$tenant_id || !$client_id || !$client_secret) {
        return false;
    }

    $response = wp_remote_post(
        "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token",
        [
            'timeout' => 20,
            'body' => [
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ],
        ]
    );

    if (is_wp_error($response)) {
        m365_log_event('fail', '-', 'Authentication failed', $response->get_error_message());
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($body['access_token'])) {
        set_transient(
            'm365_graph_token',
            $body['access_token'],
            intval($body['expires_in']) - 60
        );
        return $body['access_token'];
    }

    m365_log_event(
        'fail',
        '-',
        'Authentication failed',
        $body['error_description'] ?? 'Invalid token response'
    );

    return false;
}

/**
 * ==================================================
 * Prepare HTML body
 * ==================================================
 */
function m365_prepare_html($message) {

    if ($message !== strip_tags($message)) {
        return wpautop($message);
    }

    return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6">'
        . nl2br(esc_html($message)) .
        '</div>';
}

/**
 * ==================================================
 * Parse wp_mail headers
 * ==================================================
 */
function m365_parse_headers($headers) {

    $parsed = [
        'replyTo' => [],
        'cc'      => [],
        'bcc'     => [],
    ];

    if (empty($headers)) {
        return $parsed;
    }

    if (!is_array($headers)) {
        $headers = explode("\n", $headers);
    }

    foreach ($headers as $header) {

        if (stripos($header, 'Reply-To:') === 0) {
            $email = trim(substr($header, 9));
            $parsed['replyTo'][] = ['emailAddress' => ['address' => $email]];
        }

        if (stripos($header, 'Cc:') === 0) {
            $email = trim(substr($header, 3));
            $parsed['cc'][] = ['emailAddress' => ['address' => $email]];
        }

        if (stripos($header, 'Bcc:') === 0) {
            $email = trim(substr($header, 4));
            $parsed['bcc'][] = ['emailAddress' => ['address' => $email]];
        }
    }

    return $parsed;
}

/**
 * ==================================================
 * Prepare attachments (Graph limit ~4MB)
 * ==================================================
 */
function m365_prepare_attachments($attachments) {

    $graph_attachments = [];

    foreach ((array)$attachments as $file) {

        if (!file_exists($file)) {
            continue;
        }

        if (filesize($file) > 4 * 1024 * 1024) {
            continue;
        }

        $graph_attachments[] = [
            '@odata.type'  => '#microsoft.graph.fileAttachment',
            'name'         => basename($file),
            'contentType'  => mime_content_type($file),
            'contentBytes' => base64_encode(file_get_contents($file)),
        ];
    }

    return $graph_attachments;
}

/**
 * ==================================================
 * Send Mail via Microsoft Graph
 * ==================================================
 */
function m365_send_mail($to, $subject, $message, $headers = [], $attachments = [], $force_sender = null) {

    $access_token = m365_get_access_token();
    if (!$access_token) {
        m365_log_event('fail', $to, $subject, 'Access token unavailable');
        return false;
    }

    $from_email = $force_sender ?: get_option('m365_from_email');
    if (!$from_email) {
        m365_log_event('fail', $to, $subject, 'Sender email not configured');
        return false;
    }

    $recipients = is_array($to)
        ? $to
        : array_map('trim', explode(',', $to));

    $parsed_headers    = m365_parse_headers($headers);
    $graph_attachments = m365_prepare_attachments($attachments);

    $payload = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content'     => m365_prepare_html($message),
            ],
            'toRecipients' => array_map(function ($email) {
                return ['emailAddress' => ['address' => $email]];
            }, $recipients),
        ],
        'saveToSentItems' => true,
    ];

    if (!empty($parsed_headers['replyTo'])) {
        $payload['message']['replyTo'] = $parsed_headers['replyTo'];
    }

    if (!empty($parsed_headers['cc'])) {
        $payload['message']['ccRecipients'] = $parsed_headers['cc'];
    }

    if (!empty($parsed_headers['bcc'])) {
        $payload['message']['bccRecipients'] = $parsed_headers['bcc'];
    }

    if (!empty($graph_attachments)) {
        $payload['message']['attachments'] = $graph_attachments;
    }

    $response = wp_remote_post(
        "https://graph.microsoft.com/v1.0/users/{$from_email}/sendMail",
        [
            'timeout' => 20,
            'headers' => [
                'Authorization' => "Bearer {$access_token}",
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($payload),
        ]
    );

    if (is_wp_error($response)) {
        m365_log_event('fail', $recipients, $subject, $response->get_error_message());
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body        = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code !== 202) {
        m365_log_event(
            'fail',
            $recipients,
            $subject,
            $body['error']['message'] ?? 'Graph rejected request'
        );
        return false;
    }

    m365_log_event('success', $recipients, $subject);
    return true;
}

/**
 * ==================================================
 * wp_mail Override (CF7 / Woo safe)
 * ==================================================
 */
add_filter('wp_mail', function ($args) {

    m365_send_mail(
        $args['to'],
        $args['subject'],
        $args['message'],
        $args['headers'],
        $args['attachments']
    );

    return $args;
});

/**
 * ==================================================
 * AJAX: Save & Authenticate (no sender here)
 * ==================================================
 */
add_action('wp_ajax_m365_save_and_auth', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    check_ajax_referer('m365_save_auth_nonce', 'nonce');

    update_option('m365_client_id', sanitize_text_field($_POST['m365_client_id'] ?? ''));
    update_option('m365_tenant_id', sanitize_text_field($_POST['m365_tenant_id'] ?? ''));

    if (!empty($_POST['m365_client_secret'])) {
        update_option('m365_client_secret', sanitize_text_field($_POST['m365_client_secret']));
    }

    delete_transient('m365_graph_token');

    if (m365_get_access_token()) {
        update_option('m365_is_authenticated', true);
        m365_log_event('success', '-', 'Authentication successful');
        wp_send_json_success(['message' => 'Successfully authenticated with Microsoft 365.']);
    }

    // Auth failed → invalidate state
    update_option('m365_is_authenticated', false);
    update_option('m365_sender_validated', false);

    $logs = get_option('m365_mail_logs', []);
    $last = end($logs);

    wp_send_json_error(['message' => $last['error'] ?? 'Authentication failed.']);
});

/**
 * ==================================================
 * AJAX: Clear Email Logs
 * ==================================================
 */
add_action('wp_ajax_m365_clear_logs', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    check_ajax_referer('m365_clear_logs_nonce', 'nonce');

    update_option('m365_mail_logs', []);

    m365_log_event('success', '-', 'Email logs cleared');

    wp_send_json_success(['message' => 'Logs cleared successfully.']);
});


/**
 * ==================================================
 * AJAX: Validate Sender Email
 * ==================================================
 */
add_action('wp_ajax_m365_validate_sender', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    check_ajax_referer('m365_validate_sender_nonce', 'nonce');

    $sender = sanitize_email($_POST['sender'] ?? '');

    if (!is_email($sender)) {
        wp_send_json_error(['message' => 'Invalid sender email address.']);
    }

    $result = m365_send_mail(
        $sender,
        'Sender validation',
        '<p>Sender validation successful.</p>',
        [],
        [],
        $sender
    );

    if ($result === true) {
        update_option('m365_from_email', $sender);
        update_option('m365_sender_validated', true);
        m365_log_event('success', $sender, 'Sender validated');
        wp_send_json_success(['message' => 'Sender email validated successfully.']);
    }

    update_option('m365_sender_validated', false);

    $logs = get_option('m365_mail_logs', []);
    $last = end($logs);

    wp_send_json_error(['message' => $last['error'] ?? 'Sender validation failed.']);
});

/**
 * ==================================================
 * AJAX: Send Test Email (Styled HTML)
 * ==================================================
 */
add_action('wp_ajax_m365_send_test_email', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    check_ajax_referer('m365_test_email_nonce', 'nonce');

    $to = sanitize_email($_POST['email'] ?? '');

    if (!is_email($to)) {
        wp_send_json_error([
            'message' => 'Invalid recipient email address.'
        ]);
    }

    // Sender MUST already be validated & saved
    $from = get_option('m365_from_email');
    if (!$from) {
        wp_send_json_error([
            'message' => 'Sender email is not configured or validated.'
        ]);
    }

    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $timestamp = current_time('Y-m-d H:i:s');

    $subject = sprintf(
        'Microsoft 365 Mailer: Test Email – %s – HTML Version',
        $site_name
    );

    $message = '
    <div style="background:#f6f7f8;padding:40px 0;">
        <div style="
            max-width:600px;
            margin:0 auto;
            background:#ffffff;
            border-radius:6px;
            box-shadow:0 1px 4px rgba(0,0,0,0.08);
            font-family:Arial,Helvetica,sans-serif;
        ">

            <div style="
                text-align:center;
                padding:30px 20px;
                border-bottom:1px solid #eaeaea;
            ">
                <h1 style="
                    margin:0;
                    font-size:26px;
                    color:#111;
                    letter-spacing:0.5px;
                ">
                    Microsoft 365 Mailer
                </h1>
            </div>

            <div style="padding:30px 35px;color:#333;font-size:14px;line-height:1.7;">

                <p style="font-size:16px;margin-top:0;">
                    🎉 <strong>Congrats, test email was sent successfully!</strong>
                </p>

                <p>
                    Thank you for using <strong>Microsoft 365 Mailer</strong> Plugin — the ultimate Microsoft Graph–powered mailer plugin designed to ensure your WordPress emails are delivered reliably without SMTP.
                </p>

                <p>
                    Microsoft 365 Mailer is a <strong>free and open-source</strong> plugin, built with enterprise-grade security in mind and without vendor lock-in. You stay in full control of your Microsoft 365 infrastructure.
                </p>

                <p style="margin-top:30px;">
                    Regards,<br>
                    <strong>Md Fayazur Rahman</strong><br>
                    Contact: fayazur7@gmail.com,<br>
                    Microsoft 365 Mailer
                </p>

                <p style="
                    margin-top:40px;
                    font-size:12px;
                    color:#777;
                    border-top:1px solid #eee;
                    padding-top:15px;
                ">
                    This email was sent from <strong>' . esc_html($site_name) . '</strong> at ' . esc_html($timestamp) . '
                </p>
            </div>
        </div>
    </div>
    ';

    $sent = m365_send_mail($to, $subject, $message);

    if ($sent === true) {
        wp_send_json_success([
            'message' => 'Test email sent successfully.'
        ]);
    }

    // Pull last log entry for detailed error
    $logs = get_option('m365_mail_logs', []);
    $last = end($logs);

    wp_send_json_error([
        'message' => $last['error'] ?? 'Failed to send test email.'
    ]);
});

