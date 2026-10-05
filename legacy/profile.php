<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_guard.php';

check_auth();

$user_id = (int) $_SESSION['user_id'];
$errors = [];
$success = '';

$department_options = [
    'computer-science' => 'Computer Science',
    'mass-communication' => 'Mass Communication',
    'accountancy' => 'Accountancy',
];

$level_options = ['ND1', 'ND2', 'ND3', 'HND1', 'HND2', 'HND3'];
$programme_options = ['Full-time', 'Part-time', 'CODFEL'];

$profile = [
    'fullname' => '',
    'matric_number' => '',
    'department' => '',
    'level' => '',
    'programme' => '',
    'role' => '',
    'last_login' => null,
];

$stmt = $conn->prepare("SELECT fullname, matric_number, department, level, programme, role, last_login FROM users WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $profile = $result->fetch_assoc();
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fullname = trim((string) ($_POST['fullname'] ?? ''));
    $department = trim((string) ($_POST['department'] ?? ''));
    $level = trim((string) ($_POST['level'] ?? ''));
    $programme = trim((string) ($_POST['programme'] ?? ''));

    if (mb_strlen($fullname) < 3) {
        $errors[] = 'Full name must be at least 3 characters long.';
    }

    if (!array_key_exists($department, $department_options)) {
        $errors[] = 'Please select a valid department.';
    }

    if (!in_array($level, $level_options, true)) {
        $errors[] = 'Please select a valid level.';
    }

    if (!in_array($programme, $programme_options, true)) {
        $errors[] = 'Please select a valid programme.';
    }

    if (empty($errors)) {
        $update = $conn->prepare("UPDATE users SET fullname = ?, department = ?, level = ?, programme = ? WHERE id = ?");
        if ($update) {
            $update->bind_param("ssssi", $fullname, $department, $level, $programme, $user_id);
            if ($update->execute()) {
                $profile['fullname'] = $fullname;
                $profile['department'] = $department;
                $profile['level'] = $level;
                $profile['programme'] = $programme;
                $_SESSION['fullname'] = $fullname;
                $success = 'Your student profile has been updated successfully.';
                log_audit($conn, 'profile_update', 'users', $user_id, [
                    'department' => $department,
                    'level' => $level,
                    'programme' => $programme,
                ]);
            } else {
                $errors[] = 'Unable to save profile changes at the moment. Please try again.';
            }
            $update->close();
        }
    }
}

$display_name = $profile['fullname'] ?: 'Student';
$display_initial = mb_strtoupper(mb_substr($display_name, 0, 1, 'UTF-8'), 'UTF-8') ?: 'S';
$display_department = $department_options[$profile['department']] ?? ucfirst(str_replace('-', ' ', $profile['department']));
$display_level = $profile['level'] ?: 'Not set';
$display_programme = $profile['programme'] ?: 'Not set';
$display_last_login = $profile['last_login'] ? date('M d, Y · h:i A', strtotime($profile['last_login'])) : 'Not available yet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage your NACOS App student profile, update academic details, and keep your account information current.">
    <title>My Profile | NACOS App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/main.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --profile-bg: #f4f8fb;
            --profile-surface: #ffffff;
            --profile-border: rgba(15, 23, 42, 0.08);
            --profile-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            --profile-green: #0b8f3a;
            --profile-green-dark: #066b2a;
            --soft-green: rgba(11, 143, 58, 0.08);
        }

        body {
            background: var(--profile-bg);
            font-family: 'Inter', sans-serif;
        }

        .profile-shell {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 24px 40px;
        }

        .profile-hero {
            display: grid;
            grid-template-columns: 1.5fr 0.9fr;
            gap: 24px;
            align-items: stretch;
        }

        .profile-banner,
        .profile-summary,
        .profile-card {
            background: var(--profile-surface);
            border-radius: 24px;
            box-shadow: var(--profile-shadow);
            border: 1px solid var(--profile-border);
        }

        .profile-banner {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #066b2a 0%, #0b8f3a 55%, #1fb04e 100%);
            padding: 34px;
            color: #ffffff;
            min-height: 220px;
        }

        .profile-banner::after {
            content: '';
            position: absolute;
            inset: auto -60px -60px auto;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,0.16);
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .profile-banner h1 {
            margin: 14px 0 10px;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1.1;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .profile-banner p {
            margin: 0;
            max-width: 620px;
            color: rgba(255,255,255,0.9);
            font-size: 1rem;
        }

        .profile-meta-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.18);
            font-size: 0.84rem;
            font-weight: 600;
        }

        .profile-summary {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            justify-content: center;
        }

        .summary-identity {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .summary-identity h2 {
            margin: 0;
            font-size: 1.05rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .summary-identity p {
            margin: 4px 0 0;
            color: var(--text-secondary);
            font-size: 0.88rem;
        }

        .summary-list {
            display: grid;
            gap: 12px;
        }

        .summary-item {
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8fbfd;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .summary-item span {
            display: block;
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }

        .summary-item strong {
            font-size: 0.92rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 0.95fr 1.25fr;
            gap: 24px;
            margin-top: 24px;
        }

        .profile-card {
            padding: 24px;
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .section-head h2 {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.1rem;
        }

        .chip {
            padding: 6px 10px;
            background: var(--soft-green);
            color: var(--profile-green);
            font-size: 0.76rem;
            font-weight: 700;
            border-radius: 999px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 16px;
            margin-bottom: 18px;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.success {
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
            border: 1px solid rgba(34, 197, 94, 0.25);
        }

        .alert.error {
            background: rgba(248, 113, 113, 0.14);
            color: #b91c1c;
            border: 1px solid rgba(248, 113, 113, 0.24);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 0.82rem;
            color: var(--text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control,
        .form-select {
            width: 100%;
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: #f8fbfd;
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 0.94rem;
            color: var(--text-dark);
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: rgba(11, 143, 58, 0.5);
            box-shadow: 0 0 0 4px rgba(11, 143, 58, 0.12);
            background: #ffffff;
        }

        .input-note {
            margin: 2px 0 0;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-primary,
        .btn-secondary {
            border-radius: 14px;
            padding: 12px 18px;
            font-weight: 700;
            font-family: inherit;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--profile-green);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: var(--profile-green-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #eaf4ed;
            color: var(--profile-green);
        }

        .btn-secondary:hover {
            background: #d8ebde;
            color: var(--profile-green-dark);
        }

        .status-mini {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.12);
        }

        @media (max-width: 900px) {
            .profile-hero,
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .profile-shell {
                padding: 0 16px 32px;
            }

            .profile-banner,
            .profile-summary,
            .profile-card {
                border-radius: 20px;
            }

            .profile-banner {
                padding: 24px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .profile-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-primary,
            .btn-secondary {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/header.php'; ?>

    <main class="profile-shell">
        <section class="profile-hero">
            <div class="profile-banner">
                <span class="eyebrow"><i class="ri-user-3-fill"></i> Student Account</span>
                <h1>Keep your academic details current.</h1>
                <p>Review your profile, update your department and level, and keep your NACOS App experience aligned with your academic record.</p>
                <div class="profile-meta-pills">
                    <span class="meta-pill"><i class="ri-shield-check-line"></i> Secure Profile</span>
                    <span class="meta-pill"><i class="ri-book-open-line"></i> Personalized Library Access</span>
                </div>
            </div>

            <aside class="profile-summary">
                <div class="summary-identity">
                    <div>
                        <h2 id="previewName"><?= safe_output($display_name) ?></h2>
                        <p id="previewMeta"><?= safe_output($display_department) ?> • <?= safe_output($display_level) ?></p>
                    </div>
                </div>

                <div class="summary-list">
                    <div class="summary-item">
                        <span>Matric Number</span>
                        <strong><?= safe_output($profile['matric_number']) ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Programme</span>
                        <strong><?= safe_output($display_programme) ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Last Login</span>
                        <strong><?= safe_output($display_last_login) ?></strong>
                    </div>
                </div>
            </aside>
        </section>

        <section class="profile-grid">
            <aside class="profile-card">
                <div class="section-head">
                    <h2>Account Snapshot</h2>
                    <span class="chip">Live</span>
                </div>

                <div class="status-mini" aria-live="polite">
                    <span class="status-dot"></span>
                    Your profile status is active.
                </div>

                <div class="summary-list" style="margin-top: 16px;">
                    <div class="summary-item">
                        <span>Role</span>
                        <strong><?= safe_output(ucfirst($profile['role'])) ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Department</span>
                        <strong><?= safe_output($display_department) ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Level</span>
                        <strong><?= safe_output($display_level) ?></strong>
                    </div>
                    <div class="summary-item">
                        <span>Academic Mode</span>
                        <strong><?= safe_output($display_programme) ?></strong>
                    </div>
                </div>
            </aside>

            <section class="profile-card">
                <div class="section-head">
                    <h2>Edit Student Details</h2>
                    <span class="chip">Profile Editor</span>
                </div>

                <?php if ($success !== ''): ?>
                    <div class="alert success">
                        <i class="ri-checkbox-circle-fill"></i>
                        <span><?= safe_output($success) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert error">
                        <i class="ri-error-warning-fill"></i>
                        <span><?= safe_output(implode(' ', $errors)) ?></span>
                    </div>
                <?php endif; ?>

                <form id="profileForm" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="fullname">Full Name</label>
                            <input class="form-control" type="text" id="fullname" name="fullname" value="<?= safe_output($profile['fullname']) ?>" placeholder="Enter your full name" required>
                            <p class="input-note">This name appears in your library header and account summary.</p>
                        </div>

                        <div class="form-group">
                            <label for="matric_number">Matric Number</label>
                            <input class="form-control" type="text" id="matric_number" name="matric_number" value="<?= safe_output($profile['matric_number']) ?>" readonly aria-readonly="true">
                            <p class="input-note">Official ID is locked for verification and record integrity.</p>
                        </div>

                        <div class="form-group">
                            <label for="department">Department</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select department</option>
                                <?php foreach ($department_options as $value => $label): ?>
                                    <option value="<?= safe_output($value) ?>" <?= $profile['department'] === $value ? 'selected' : '' ?>><?= safe_output($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="level">Level</label>
                            <select class="form-select" id="level" name="level" required>
                                <option value="">Select level</option>
                                <?php foreach ($level_options as $value): ?>
                                    <option value="<?= safe_output($value) ?>" <?= $profile['level'] === $value ? 'selected' : '' ?>><?= safe_output($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="programme">Programme / Study Mode</label>
                            <select class="form-select" id="programme" name="programme" required>
                                <option value="">Select study mode</option>
                                <?php foreach ($programme_options as $value): ?>
                                    <option value="<?= safe_output($value) ?>" <?= $profile['programme'] === $value ? 'selected' : '' ?>><?= safe_output($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <button class="btn-primary" type="submit"><i class="ri-save-line"></i> Save Changes</button>
                        <a class="btn-secondary" href="<?= $BASE_URL ?>home.php"><i class="ri-home-4-line"></i> Back to Dashboard</a>
                    </div>
                </form>
            </section>
        </section>
    </main>

    <script>
        (function () {
            var form = document.getElementById('profileForm');
            if (!form) return;

            var nameInput = document.getElementById('fullname');
            var deptSelect = document.getElementById('department');
            var levelSelect = document.getElementById('level');
            var programmeSelect = document.getElementById('programme');
            var previewName = document.getElementById('previewName');
            var previewMeta = document.getElementById('previewMeta');
            var avatarBadge = document.getElementById('profileInitialBadge');

            function updatePreview() {
                var name = (nameInput.value || 'Student').trim();
                var department = deptSelect.options[deptSelect.selectedIndex]?.text || 'Department';
                var level = levelSelect.options[levelSelect.selectedIndex]?.text || 'Level';
                var programme = programmeSelect.options[programmeSelect.selectedIndex]?.text || 'Programme';

                if (previewName) previewName.textContent = name || 'Student';
                if (previewMeta) previewMeta.textContent = department + ' • ' + level + ' • ' + programme;
                if (avatarBadge) avatarBadge.textContent = (name.charAt(0) || 'S').toUpperCase();
            }

            ['input', 'change'].forEach(function (eventName) {
                form.addEventListener(eventName, updatePreview);
            });

            updatePreview();
        })();
    </script>
</body>
</html>
