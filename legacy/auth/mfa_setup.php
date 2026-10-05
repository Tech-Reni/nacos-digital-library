<?php

/**
 * auth/mfa_setup.php
 * Premium Security Settings & Multi-Factor Authentication Setup Flow
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mfa_helpers.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Only logged in users
check_auth();

$user_id = $_SESSION['user_id'];
$errors = [];
$success = "";

// Fetch current user MFA status
$stmt = $conn->prepare("SELECT mfa_enabled, mfa_secret, mfa_backup_codes, fullname, matric_number FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$mfa_enabled = $user['mfa_enabled'];
$current_secret = $user['mfa_secret'];

// Generate new secret if not already present
if (!$current_secret) {
    $current_secret = TOTP_Helper::generateSecret();
    $upd = $conn->prepare("UPDATE users SET mfa_secret = ? WHERE id = ?");
    $upd->bind_param("si", $current_secret, $user_id);
    $upd->execute();
    $upd->close();
}

$qr_url = TOTP_Helper::getQRCodeUrl($user['matric_number'], $current_secret);
// Using Google Charts API for QR code
$qr_image_url = "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($qr_url);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();

    if (isset($_POST['enable_mfa'])) {
        $otp = trim($_POST['otp']);
        if (TOTP_Helper::verifyOTP($current_secret, $otp)) {
            // Generate backup codes
            $backup_codes = TOTP_Helper::generateBackupCodes(8);
            $backup_codes_json = json_encode($backup_codes);

            $upd = $conn->prepare("UPDATE users SET mfa_enabled = 1, mfa_backup_codes = ? WHERE id = ?");
            $upd->bind_param("si", $backup_codes_json, $user_id);
            if ($upd->execute()) {
                $mfa_enabled = 1;
                $success = "Multi-Factor Authentication has been enabled successfully. Please preserve your security recovery codes.";
                log_audit($conn, "mfa_enabled_success", "users", $user_id);

                // Refresh user data to show backup codes
                $user['mfa_backup_codes'] = $backup_codes_json;
            }
            $upd->close();
        } else {
            $errors[] = "Invalid authentication code. Please check your authenticator app and try again.";
        }
    } elseif (isset($_POST['disable_mfa'])) {
        $upd = $conn->prepare("UPDATE users SET mfa_enabled = 0, mfa_backup_codes = NULL WHERE id = ?");
        $upd->bind_param("i", $user_id);
        if ($upd->execute()) {
            $mfa_enabled = 0;
            $success = "Multi-Factor Authentication has been disabled.";
            log_audit($conn, "mfa_disabled_success", "users", $user_id);
        }
        $upd->close();
    }
}

$backup_codes = json_decode($user['mfa_backup_codes'], true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Configure your NACOS App security settings including Multi-Factor Authentication (MFA) and account protection.">
    <title>Security Settings | NACOS App</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #0b8f3a;
            --dark-green: #066b2a;
            --primary-yellow: #ffcc00;
            --danger-red: #d93025;
            --danger-red-bg: #fce8e6;
            --text-dark: #111827;
            --text-secondary: #52606d;
            --bg: #f3f7fa;
            --surface: #ffffff;
            --border-subtle: 1px solid rgba(15, 23, 42, 0.08);

            --font-display: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Inter', sans-serif;
            --font-metrics: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg);
            font-family: var(--font-body);
            color: var(--text-dark);
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .security-card {
            max-width: 580px;
            margin: 48px auto;
            background: var(--surface);
            border-radius: 16px;
            border: var(--border-subtle);
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
            padding: 40px;
        }

        /* Status Banner Box */
        .status-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 32px;
            border: 1px solid transparent;
        }

        .status-banner.mfa-on {
            background-color: #f0fdf4;
            border-color: rgba(11, 143, 58, 0.15);
            color: var(--primary-green);
        }

        .status-banner.mfa-off {
            background-color: var(--danger-red-bg);
            border-color: rgba(217, 48, 37, 0.15);
            color: var(--danger-red);
        }

        .status-banner span {
            font-family: var(--font-metrics);
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-secondary);
        }

        .status-badge {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Enrollment step headers */
        .step-section {
            margin-bottom: 28px;
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .step-number {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-metrics);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .step-title {
            font-family: var(--font-display);
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .step-description {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 0 0 16px 36px;
        }

        /* Code entry interface */
        .verification-form {
            margin-left: 36px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .verification-form input[type="text"] {
            padding: 14px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.25em;
            text-align: center;
            font-family: var(--font-metrics);
            outline: none;
            transition: all 0.2s ease;
        }

        .verification-form input[type="text"]:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(11, 143, 58, 0.1);
        }

        .btn-action {
            font-family: var(--font-metrics);
            font-weight: 700;
            font-size: 0.9rem;
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-enable {
            background-color: var(--primary-green);
            color: #ffffff;
            width: 100%;
        }

        .btn-enable:hover {
            background-color: var(--dark-green);
        }

        .btn-disable {
            background-color: transparent;
            color: var(--danger-red);
            border: 1px solid rgba(217, 48, 37, 0.3);
            width: 100%;
        }

        .btn-disable:hover {
            background-color: var(--danger-red-bg);
            border-color: var(--danger-red);
        }

        /* Recovery Codes Section */
        .backup-section {
            border: 2px dashed rgba(255, 204, 0, 0.3);
            border-radius: 14px;
            padding: 24px;
            background: #fffdf2;
            margin-bottom: 28px;
        }

        .backup-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 14px;
        }

        .backup-pill {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-family: var(--font-metrics);
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-dark);
            letter-spacing: 0.05em;
        }

        @media (max-width: 600px) {
            .security-card {
                padding: 28px 20px;
                margin: 20px auto;
            }

            .backup-grid {
                grid-template-columns: 1fr;
            }

            .verification-form {
                margin-left: 0;
            }

            .step-description {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>

    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <main class="container">
        <div class="security-card">
            <h2 style="font-family: var(--font-display); font-weight: 800; font-size: 1.5rem; letter-spacing: -0.02em; margin: 0 0 6px;">Security Settings</h2>
            <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0 0 28px;">Protect your academic workspace with Multi-Factor Authentication.</p>

            <!-- MFA State Banner -->
            <div class="status-banner <?= $mfa_enabled ? 'mfa-on' : 'mfa-off' ?>">
                <span>Account Status</span>
                <div class="status-badge">
                    <i class="<?= $mfa_enabled ? 'ri-shield-check-fill' : 'ri-shield-flash-line' ?>"></i>
                    <?= $mfa_enabled ? 'MFA Protected' : 'MFA Deactivated' ?>
                </div>
            </div>

            <?php if (!$mfa_enabled): ?>
                <!-- Step 1: Scan Setup -->
                <div class="step-section">
                    <div class="step-header">
                        <div class="step-number">1</div>
                        <h3 class="step-title">Scan QR Security Code</h3>
                    </div>
                    <p class="step-description">Scan this secure token with your authenticator workspace app (Google Authenticator, Microsoft Authenticator, or Authy).</p>

                    <div style="text-align: center; margin: 20px 0 32px;">
                        <img src="<?= $qr_image_url ?>" alt="MFA Token" style="padding: 12px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                        <div style="margin-top: 12px;">
                            <span style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; font-family: var(--font-metrics); color: var(--text-secondary); display: block; margin-bottom: 4px;">Manual Code Config</span>
                            <code style="font-family: var(--font-metrics); font-weight: 700; font-size: 0.95rem; background: #f1f5f9; padding: 6px 12px; border-radius: 6px; border: var(--border-subtle); color: var(--text-dark); display: inline-block; letter-spacing: 0.05em;"><?= safe_output($current_secret) ?></code>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Verification Input -->
                <div class="step-section" style="margin-bottom: 0;">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <h3 class="step-title">Enter Authentication Code</h3>
                    </div>
                    <p class="step-description">Enter the dynamic 6-digit passcode generated by your authenticator app to enable security configurations.</p>

                    <form method="POST" class="verification-form">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="text" name="otp" placeholder="000000" maxlength="6" required autocomplete="off">
                        <button type="submit" name="enable_mfa" class="btn-action btn-enable">
                            <i class="ri-shield-keyhole-line"></i> Verify & Activate MFA
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Active security parameters & Recovery Codes Grid -->
                <div class="mfa-active">
                    <?php if (!empty($backup_codes)): ?>
                        <div class="backup-section">
                            <h3 style="font-family: var(--font-display); font-weight: 700; font-size: 0.95rem; margin: 0 0 6px; color: var(--text-dark); display: flex; align-items: center; gap: 8px;">
                                <i class="ri-shield-keyhole-line" style="color: #d97706;"></i> Recovery Backup Codes
                            </h3>
                            <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0 0 16px; line-height: 1.4;">Save these recovery backup codes safely. Each one can be utilized once if you ever lose your phone device.</p>

                            <div class="backup-grid">
                                <?php foreach ($backup_codes as $code): ?>
                                    <div class="backup-pill"><?= safe_output($code) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Disable Action Form -->
                    <form method="POST" id="disableMfaForm">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="disable_mfa" value="1">
                        <button type="button" onclick="confirmDeactivation()" class="btn-action btn-disable">
                            <i class="ri-shield-flash-line"></i> Deactivate Multi-Factor Auth
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Core Component Include -->
    <?php include_once __DIR__ . '/../includes/modal.php'; ?>
    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        // Custom Google-themed warning alert for MFA deactivation
        function confirmDeactivation() {
            AppModal.open({
                type: 'warning',
                title: 'Deactivate MFA?',
                subtitle: 'Security Warning',
                message: 'Deactivating Multi-Factor Authentication lowers your account protection. This will allow standard single-password login entries.',
                primaryText: 'Yes, Deactivate',
                secondaryText: 'Cancel',
                onConfirm: function() {
                    const form = document.getElementById('disableMfaForm');
                    // Add dummy field to mimic standard POST submit values
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'disable_mfa';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            });
        }

        // Initialize dynamic error/success modals on document load
        window.addEventListener('DOMContentLoaded', () => {
            <?php if (!empty($errors)): ?>
                const errorMessage = <?= json_encode(implode("\n", $errors)) ?>;
                AppModal.open({
                    type: 'error',
                    title: 'Verification Failed',
                    subtitle: 'Validation Errors Detected',
                    message: errorMessage,
                    showSecondary: false
                });
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                const successMessage = <?= json_encode($success) ?>;
                AppModal.open({
                    type: 'success',
                    title: 'Security Settings Updated',
                    subtitle: 'Configuration Success',
                    message: successMessage,
                    showSecondary: false
                });
            <?php endif; ?>
        });
    </script>
</body>

</html>