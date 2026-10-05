<?php

/**
 * admin/settings.php
 * Master Admin Panel Global Parameters Configuration
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/layout.php';

check_role(['admin']);

$success = "";

// Save parameters inside state files to keep DB structures intact
$config_file = __DIR__ . '/../uploads/config.json';
$config = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : [
    'site_title' => 'NACOS App',
    'max_upload' => '10MB',
    'allowed_types' => 'PDF, JPEG, PNG',
    'session' => '2025/2026'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $config['site_title'] = trim($_POST['site_title'] ?? $config['site_title']);
    $config['max_upload'] = trim($_POST['max_upload'] ?? $config['max_upload']);
    $config['allowed_types'] = trim($_POST['allowed_types'] ?? $config['allowed_types']);
    $config['session'] = trim($_POST['session'] ?? $config['session']);

    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
    log_audit($conn, "settings_updated", "settings", 1);
    $success = "Configuration parameters successfully saved.";
}

function render_settings_content()
{
    global $config, $success;

    if (!empty($success)): ?>
        <div style="background: #dcfce7; color: #15803d; padding: 16px; border-radius: 10px; font-weight: 600; margin-bottom: 24px; font-size: 0.88rem; border: 1px solid #bcf0da;">
            <i class="ri-checkbox-circle-fill"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="card-container">
        <h2 style="font-family: var(--font-display); font-size: 1.05rem; font-weight: 700; margin: 0 0 24px;"><i class="ri-settings-4-line"></i> Global System Settings</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form-group">
                <label>Platform Name</label>
                <input type="text" name="site_title" value="<?= safe_output($config['site_title']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Maximum Document Size Allowed</label>
                <input type="text" name="max_upload" value="<?= safe_output($config['max_upload']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Allowed Storage Formats</label>
                <input type="text" name="allowed_types" value="<?= safe_output($config['allowed_types']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Active Academic Session</label>
                <input type="text" name="session" value="<?= safe_output($config['session']) ?>" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 16px;">Save System Config</button>
        </form>
    </div>
<?php
}

render_admin_layout('render_settings_content', 'settings', 'Global Config Parameters', ['Settings' => '']);
?>