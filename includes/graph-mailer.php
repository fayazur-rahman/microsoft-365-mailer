<?php
/**
 * Microsoft Graph Mailer Core
 * Handles:
 * - OAuth token retrieval (client_credentials)
 * - Microsoft Graph sendMail
 * - wp_mail override
 * - Headers, attachments, HTML handling
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
        m365_log_event('fail', '-', 'Token Request Failed', $response->get_error_message());
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

    $error = $body['error_description'] ?? ($body['error'] ?? 'Invalid token response');

    m365_log_event(
        'fail',
        '-',
        'Authentication failed',
        $error
    );

    return false;
}

/**
 * ==================================================
 * AJAX: Send Test Email
 * ==================================================
 */
add_action('wp_ajax_m365_send_test_email', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    check_ajax_referer('m365_test_email_nonce', 'nonce');

    $to = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

    if (!is_email($to)) {
        wp_send_json_error([
            'message' => 'Invalid email address provided.'
        ]);
    }

    $subject = 'Microsoft 365 Mailer – Test Email';
    $message = '<h3>Success 🎉</h3><p>This is a test email sent via <strong>Microsoft 365 Mailer</strong>.</p>';

    $result = m365_send_mail($to, $subject, $message);

    if ($result === true) {
        wp_send_json_success([
            'message' => 'Test email sent successfully.'
        ]);
    }

    // Pull last log entry for detailed error
    $logs = get_option('m365_mail_logs', []);
    $last = end($logs);

    wp_send_json_error([
        'message' => $last['error'] ?? 'Unknown error occurred.'
    ]);
});


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
 * Prepare attachments (Graph limit: 4MB)
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
function m365_send_mail($to, $subject, $message, $headers = [], $attachments = []) {

    $access_token = m365_get_access_token();
    if (!$access_token) {
        m365_log_event('fail', $to, $subject, 'Access token unavailable');
        return false;
    }

    $from_email = get_option('m365_from_email');
    if (!$from_email) {
        m365_log_event('fail', $to, $subject, 'Sender email not configured');
        return false;
    }

    $recipients = is_array($to)
        ? $to
        : array_map('trim', explode(',', $to));

    $parsed_headers = m365_parse_headers($headers);
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
    $body        = wp_remote_retrieve_body($response);
    $error_msg   = 'Unknown error';

    if (!empty($body)) {
        $json = json_decode($body, true);
        if (isset($json['error']['message'])) {
            $error_msg = $json['error']['message'];
        } elseif (isset($json['error']['code'])) {
            $error_msg = $json['error']['code'];
        }
    }

    if ($status_code !== 202) {
        m365_log_event(
            'fail',
            $recipients,
            $subject,
            "Graph error ({$status_code}): {$error_msg}"
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

    $sent = m365_send_mail(
        $args['to'],
        $args['subject'],
        $args['message'],
        $args['headers'],
        $args['attachments']
    );

    if ($sent) {
        add_filter('wp_mail_succeeded', function () {
            return true;
        }, 10, 0);
    }

    return $args;
});

add_action('wp_ajax_m365_save_and_auth', function () {

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    check_ajax_referer('m365_save_auth_nonce', 'nonce');

    // Save options
    update_option('m365_client_id', sanitize_text_field($_POST['m365_client_id'] ?? ''));
    update_option('m365_tenant_id', sanitize_text_field($_POST['m365_tenant_id'] ?? ''));
    update_option('m365_from_email', sanitize_email($_POST['m365_from_email'] ?? ''));

    if (!empty($_POST['m365_client_secret'])) {
        update_option('m365_client_secret', sanitize_text_field($_POST['m365_client_secret']));
    }

    // Force token refresh
    delete_transient('m365_graph_token');

    // Try authenticating immediately
    $token = m365_get_access_token();

    if ($token) {
        m365_log_event('success', '-', 'Authentication successful');
        wp_send_json_success([
            'message' => 'Successfully authenticated with Microsoft 365.'
        ]);
    }

    // Pull detailed error from logs
    $logs = get_option('m365_mail_logs', []);
    $last = end($logs);

    wp_send_json_error([
        'message' => $last['error'] ?? 'Authentication failed.'
    ]);
});
