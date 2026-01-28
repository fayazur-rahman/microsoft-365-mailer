<?php
/**
 * Logs system for Microsoft 365 Mailer
 * - Stores recent mail events
 * - Displays logs in admin UI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==================================================
 * Log Writer
 * ==================================================
 * Used by graph-mailer.php
 */
function m365_log_event($status, $to, $subject, $error = '') {

    $logs = get_option('m365_mail_logs', []);

    if (!is_array($logs)) {
        $logs = [];
    }

    $logs[] = [
        'time'    => current_time('mysql'),
        'status'  => $status === 'success' ? 'success' : 'fail',
        'to'      => is_array($to) ? implode(', ', $to) : (string) $to,
        'subject' => (string) $subject,
        'error'   => (string) $error,
    ];

    // Keep only the latest 50 logs
    if (count($logs) > 50) {
        $logs = array_slice($logs, -50);
    }

    update_option('m365_mail_logs', $logs, false);
}

/**
 * ==================================================
 * Render Logs Tab
 * ==================================================
 */
function m365_render_logs_tab() {

    if (!current_user_can('manage_options')) {
        return;
    }

    $logs = array_reverse(get_option('m365_mail_logs', []));
    ?>

    <div style="margin-top:20px; max-width:1000px;">

        <div style="display:flex;align-items:center;justify-content:space-between;">
            <h2 style="margin:0;">Email Logs</h2>

            <button type="button"
                    class="button button-secondary"
                    id="m365-clear-logs">
                Clear Logs
            </button>
        </div>


        <p>
            This log shows the most recent email delivery attempts made via
            <strong>Microsoft 365 Mailer</strong>.
        </p>

        <?php if (empty($logs)) : ?>

            <div class="notice notice-info">
                <p>No email logs yet.</p>
            </div>

        <?php else : ?>

            <table class="widefat striped">
                <thead>
                <tr>
                    <th style="width:140px;">Time</th>
                    <th style="width:80px;">Status</th>
                    <th>Recipient(s)</th>
                    <th>Subject</th>
                    <th>Error (if any)</th>
                </tr>
                </thead>
                <tbody>

                <?php foreach ($logs as $log) : ?>

                    <tr>
                        <td><?php echo esc_html($log['time']); ?></td>
                        <td>
                            <?php if ($log['status'] === 'success') : ?>
                                <span style="color:green;font-weight:600;">✔ Success</span>
                            <?php else : ?>
                                <span style="color:#b32d2e;font-weight:600;">✖ Failed</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log['to']); ?></td>
                        <td><?php echo esc_html($log['subject']); ?></td>
                        <td>
                            <?php
                            if (!empty($log['error'])) {
                                echo esc_html($log['error']);
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

            <p style="margin-top:10px;color:#666;">
                Showing the last <?php echo count($logs); ?> entries (maximum 50).
            </p>

        <?php endif; ?>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const clearBtn = document.getElementById('m365-clear-logs');

        if (!clearBtn) return;

        clearBtn.addEventListener('click', function () {

            if (!confirm('Are you sure you want to clear all email logs? This action cannot be undone.')) {
                return;
            }

            clearBtn.disabled = true;
            clearBtn.textContent = 'Clearing...';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'm365_clear_logs',
                    nonce: '<?php echo wp_create_nonce('m365_clear_logs_nonce'); ?>'
                })
            })
            .then(r => r.json())
            .then(r => {
                if (r.success) {
                    location.reload();
                } else {
                    alert(r.data.message || 'Failed to clear logs.');
                    clearBtn.disabled = false;
                    clearBtn.textContent = 'Clear Logs';
                }
            })
            .catch(() => {
                alert('Unexpected error occurred.');
                clearBtn.disabled = false;
                clearBtn.textContent = 'Clear Logs';
            });
        });

    });
    </script>


    <?php
}
