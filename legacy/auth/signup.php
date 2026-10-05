<?php
require_once __DIR__ . '/../includes/db.php';

// secure_session_start() is called in db.php via helpers/session.php
secure_session_start();

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Phase 5: CSRF Verification
    csrf_verify();

    $fullname       = trim($_POST['fullname']);
    $matric_number  = strtoupper(trim($_POST['matric_number']));
    $department     = normalize_department($_POST['department'] ?? '');
    $level          = $_POST['level'];
    $programme      = normalize_programme($_POST['programme'] ?? '');
    $password       = $_POST['password'];
    $confirm_pw     = $_POST['confirm_password'];

    // Device fingerprint (injected by client-side JS). SHA-256 hex string expected.
    $device_fp = isset($_POST['device_fp']) ? trim($_POST['device_fp']) : '';

    if (strlen($fullname) < 3) {
        $errors[] = "Full name must be at least 3 characters.";
    }

    // Phase 2: Matric Number Validation
    if (!validate_matric_number($matric_number)) {
        $errors[] = "Invalid Matric Number format. Expected format: F/ND/24/1234567, P/HND/21/1234567, or C/HD/19/1234567";
    }

    if ($password !== $confirm_pw) {
        $errors[] = "Passwords do not match.";
    }

    // Phase 3: Password Complexity
    if (!is_password_strong($password)) {
        $errors[] = "Password must be 8-12 characters long and include uppercase, lowercase, number, and special character.";
    }

    // Validate device fingerprint format if present
    if ($device_fp !== '' && !preg_match('/^[0-9a-f]{64}$/i', $device_fp)) {
        $errors[] = "Unable to verify device fingerprint.";
    }

    if (empty($errors)) {

        // Soft device restriction: do not allow multiple accounts from same device_fp
        if ($device_fp !== '') {
            $check = $conn->prepare("SELECT id FROM users WHERE device_fp = ? LIMIT 1");
            if ($check) {
                $check->bind_param("s", $device_fp);
                $check->execute();
                $check->store_result();
                if ($check->num_rows > 0) {
                    $errors[] = "An account has already been created from this device.";
                }
                $check->close();
            }
        }

        if (empty($errors)) {
            $uuid = bin2hex(random_bytes(16));

            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users 
            (uuid, fullname, matric_number, department, level, programme, password_hash, device_fp) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param(
                    "ssssssss",
                    $uuid,
                    $fullname,
                    $matric_number,
                    $department,
                    $level,
                    $programme,
                    $password_hash,
                    $device_fp
                );

                if ($stmt->execute()) {
                    $success = "Account created successfully. <a href='" . $BASE_URL . "auth/login.php'>Login Now</a>";
                    // Phase 13: Audit Logging
                    log_audit($conn, "user_registration", "users", $conn->insert_id, ["matric_number" => $matric_number]);
                } else {
                    if ($stmt->errno == 1062) {
                        $errors[] = "Matric Number already exists.";
                    } else {
                        $errors[] = "Database error: " . $stmt->error;
                    }
                }

                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create an account on NACOS App to access student services, course materials and academic resources.">
    <meta name="author" content="NACOS App">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0a74da">
    <meta property="og:title" content="NACOS App — Sign Up">
    <meta property="og:description" content="Register for NACOS App to access student services and protected academic resources.">
    <meta property="og:image" content="<?= $BASE_URL ?>assets/images/YCT_LOGO.png">
    <meta name="twitter:card" content="summary_large_image">
    <title>NACOS App | Signup</title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>assets/css/auth.css">
    <link rel="icon" href="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

    <div class="auth-wrapper">
        <div class="auth-box">

            <img src="<?= $BASE_URL ?>assets/images/NACOS_LOGO.png" alt="NACOS Logo" class="auth-logo">

            <h2>Create Account</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $e) echo "<p>" . safe_output($e) . "</p>"; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success">
                    <?= $success // Link is safe as it's hardcoded ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= $BASE_URL ?>auth/signup.php" id="signupForm" autocomplete="on">
                <!-- Phase 5: CSRF Token -->
                <input type="hidden" id="signup_csrf_token" name="csrf_token" value="<?= csrf_token() ?>">

                <input type="text" id="signup_fullname" name="fullname" placeholder="Full Name" value="<?= isset($_POST['fullname']) ? safe_output($_POST['fullname']) : '' ?>" required autocomplete="name">

                <input type="text" id="signup_matric_number" name="matric_number" placeholder="Matric Number (e.g. F/ND/24/1234567)" value="<?= isset($_POST['matric_number']) ? safe_output($_POST['matric_number']) : '' ?>" required autocomplete="username">

                <select id="signup_department" name="department" required>
                    <option value="">Select Department</option>
                    <option value="computer-science" <?= isset($_POST['department']) && $_POST['department'] == 'computer-science' ? 'selected' : '' ?>>Computer Science</option>
                </select>

                <select id="signup_level" name="level" required>
                    <option value="">Select Level</option>
                    <option value="ND1" <?= isset($_POST['level']) && $_POST['level'] == 'ND1' ? 'selected' : '' ?>>ND1</option>
                    <option value="ND2" <?= isset($_POST['level']) && $_POST['level'] == 'ND2' ? 'selected' : '' ?>>ND2</option>
                    <option value="ND3" <?= isset($_POST['level']) && $_POST['level'] == 'ND3' ? 'selected' : '' ?>>ND3</option>
                    <option value="HND1" <?= isset($_POST['level']) && $_POST['level'] == 'HND1' ? 'selected' : '' ?>>HND1</option>
                    <option value="HND2" <?= isset($_POST['level']) && $_POST['level'] == 'HND2' ? 'selected' : '' ?>>HND2</option>
                    <option value="HND3" <?= isset($_POST['level']) && $_POST['level'] == 'HND3' ? 'selected' : '' ?>>HND3</option>
                </select>

                <select id="signup_programme" name="programme" required>
                    <option value="">Select Programme</option>
                    <option value="Full-time" <?= isset($_POST['programme']) && $_POST['programme'] == 'Full-time' ? 'selected' : '' ?>>Full-time</option>
                    <option value="Part-time" <?= isset($_POST['programme']) && $_POST['programme'] == 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                    <option value="CODFEL" <?= isset($_POST['programme']) && $_POST['programme'] == 'CODFEL' ? 'selected' : '' ?>>CODFEL</option>
                </select>

                <div class="password-wrapper">
                    <input type="password" name="password" id="signup_password" placeholder="Password" required autocomplete="new-password">
                    <i class="ri-eye-line toggle-eye" data-target="signup_password"></i>
                </div>

                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="signup_confirm_password" placeholder="Confirm Password" required autocomplete="new-password">
                    <i class="ri-eye-line toggle-eye" data-target="signup_confirm_password"></i>
                </div>

                <button type="submit">Sign Up</button>

            </form>

            <p class="switch-link">
                Already have an account? <a href="<?= $BASE_URL ?>auth/login.php">Login</a>
            </p>

        </div>
    </div>

    <script src="<?= $BASE_URL ?>assets/js/auth.js"></script>
</body>

</html>