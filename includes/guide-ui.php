<?php
/**
 * Guide UI for Microsoft 365 Mailer
 * Explains how to obtain required Microsoft Entra ID values
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==================================================
 * Render Guide Tab
 * ==================================================
 */
function m365_render_guide_tab() {
    ?>

    <div style="margin-top:20px; max-width:1000px;">

        <h2>How to get the required values</h2>

        <p>
            <strong>Microsoft 365 Mailer</strong> sends WordPress emails using
            <strong>Microsoft Graph API</strong>.  
            To make this work, you must create a Microsoft Entra ID
            <strong>App Registration</strong> and connect it to this plugin.
        </p>

        <div style="background:#fff3cd;border-left:4px solid #ffb900;padding:14px;margin:16px 0;">
            <strong>Important:</strong><br>
            You must sign in using a <strong>Microsoft 365 administrator (root) account</strong>.
            Regular user accounts cannot create or authorize app registrations.
        </div>

        <hr>

        <h3>Step 1: Sign in to Microsoft Entra ID</h3>
        <ol>
            <li>Open your browser and go to:</li>
            <li>
                <a href="https://entra.microsoft.com/#home" target="_blank">
                    https://entra.microsoft.com/#home
                </a>
            </li>
            <li>Sign in using your <strong>Microsoft 365 admin account</strong></li>
        </ol>

        <hr>

        <h3>Step 2: Create a new App Registration</h3>
        <ol>
            <li>From the left menu, click <strong>App registrations</strong></li>
            <li>Click <strong>New registration</strong></li>
        </ol>

        <p><strong>On the registration page:</strong></p>
        <ul>
            <li>
                <strong>Name:</strong><br>
                Enter a name such as <code>Microsoft365Mailer</code>
            </li>
            <li>
                <strong>Supported account types:</strong><br>
                Select:<br>
                <em>
                    Accounts in any organizational directory (Any Microsoft Entra ID tenant – Multitenant)
                    and personal Microsoft accounts (e.g. Skype, Xbox)
                </em>
            </li>
            <li>
                Leave all other options unchanged
            </li>
        </ul>

        <p>Click <strong>Register</strong>.</p>

        <hr>

        <h3>Step 3: Copy Application (client) ID and Directory (tenant) ID</h3>

        <p>
            After registration, you will be redirected to the
            <strong>Overview</strong> page of your app.
        </p>

        <ul>
            <li>
                Copy <strong>Application (client) ID</strong><br>
                → Paste it into the plugin field
                <strong>Application (client) ID</strong>
            </li>
            <li>
                Copy <strong>Directory (tenant) ID</strong><br>
                → Paste it into the plugin field
                <strong>Directory (tenant) ID</strong>
            </li>
        </ul>

        <hr>

        <h3>Step 4: Create a Client Secret</h3>
        <ol>
            <li>In the left menu, click <strong>Certificates &amp; secrets</strong></li>
            <li>Click <strong>New client secret</strong></li>
        </ol>

        <p><strong>Fill in:</strong></p>
        <ul>
            <li>
                <strong>Description:</strong> e.g. <code>Mailer</code>
            </li>
            <li>
                <strong>Expires:</strong> Choose <strong>365 days</strong> or longer
            </li>
        </ul>

        <p>Click <strong>Add</strong>.</p>

        <div style="background:#fdecea;border-left:4px solid #d63638;padding:14px;margin:16px 0;">
            <strong>Critical Warning:</strong><br><br>
            Microsoft will show the <strong>Client Secret Value only once</strong>.
            <br><br>
            ✅ Copy the <strong>Value</strong><br>
            ❌ Do NOT copy the <strong>Secret ID</strong>
            <br><br>
            Save this value securely. You will not be able to see it again.
        </div>

        <p>
            Paste this value into the plugin field
            <strong>Client Secret Value</strong>.
        </p>

        <hr>

        <h3>Step 5: Add Microsoft Graph Mail Permission</h3>
        <ol>
            <li>Go to <strong>API permissions</strong></li>
            <li>Click <strong>Add a permission</strong></li>
            <li>Select <strong>Microsoft Graph</strong></li>
            <li>Select <strong>Application permissions</strong></li>
            <li>Expand <strong>Mail</strong></li>
            <li>Select <strong>Mail.Send</strong></li>
            <li>Click <strong>Add permissions</strong></li>
        </ol>

        <hr>

        <h3>Step 6: Grant Admin Consent (Mandatory)</h3>
        <p>
            After adding permissions, you may see a red warning icon.
        </p>

        <ol>
            <li>Click <strong>Grant admin consent</strong></li>
            <li>Confirm by clicking <strong>Yes</strong></li>
        </ol>

        <p>
            When successful, the permission status will turn
            <strong>green</strong>.
        </p>

        <hr>

        <h3>Step 7: Configure Sender Email</h3>
        <p>
            In the plugin <strong>Settings</strong> tab, enter a
            <strong>Sender Email</strong> that:
        </p>

        <ul>
            <li>Exists in Microsoft 365</li>
            <li>Has an active mailbox (not just an alias)</li>
        </ul>

        <p>
            <em>
                Note: The sender display name comes from Microsoft 365 and
                cannot be changed from WordPress.
            </em>
        </p>

        <hr>

        <h3>Step 8: Send a Test Email</h3>
        <ol>
            <li>Go back to the <strong>Settings</strong> tab</li>
            <li>Enter your email address under <strong>Send Test Email</strong></li>
            <li>Click <strong>Send Test Email</strong></li>
        </ol>

        <p>
            If configured correctly, you will receive a
            <strong>HTML-formatted test email</strong> sent via Microsoft Graph.
        </p>

        <hr>

        <h3>You’re done 🎉</h3>
        <p>
            Once configured, <strong>all WordPress emails</strong> will be sent
            through Microsoft 365, including:
        </p>

        <ul>
            <li>Password reset emails</li>
            <li>Contact Form 7 submissions</li>
            <li>WooCommerce notifications</li>
            <li>Admin alerts</li>
        </ul>

        <p>
            No SMTP. No plugins. No legacy authentication.  
            Just Microsoft-recommended Graph API.
        </p>

    </div>

    <?php
}
