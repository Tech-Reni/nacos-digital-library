<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';

session_start();

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullname       = trim($_POST['fullname']);
    $matric_number  = strtoupper(trim($_POST['matric_number']));
    $department     = trim($_POST['department']);
    $level          = $_POST['level'];
    $programme      = $_POST['programme'];
    $password       = $_POST['password'];
    $confirm_pw     = $_POST['confirm_password'];

    if (strlen($fullname) < 3) {
        $errors[] = "Full name must be at least 3 characters.";
    }

    if ($password !== $confirm_pw) {
        $errors[] = "Passwords do not match.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if (empty($errors)) {

        $uuid = bin2hex(random_bytes(16));

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO users 
        (uuid, fullname, matric_number, department, level, programme, password_hash) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param(
                "sssssss",
                $uuid,
                $fullname,
                $matric_number,
                $department,
                $level,
                $programme,
                $password_hash
            );

            if ($stmt->execute()) {
                $success = "Account created successfully. <a href='login.php'>Login Now</a>";
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
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create an account on NACOS Digital Library to access course materials, books and student resources.">
    <meta name="author" content="NACOS Digital Library">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0a74da">
    <meta property="og:title" content="NACOS Digital Library — Sign Up">
    <meta property="og:description" content="Register for the NACOS Digital Library to browse and read protected resources for students.">
    <meta property="og:image" content="../assets/images/YCT_LOGO.png">
    <meta name="twitter:card" content="summary_large_image">
    <title>NACOS Digital Library | Signup</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="icon" href="../assets/images/YCT_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

    <div class="auth-wrapper">
        <div class="auth-box">

            <img src="../assets/images/NACOS_LOGO.png" alt="NACOS Logo" class="auth-logo">

            <h2>Create Account</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $e) echo "<p>$e</p>"; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="signupForm">

                <input type="text" name="fullname" placeholder="Full Name" required>

                <input type="text" name="matric_number" placeholder="Matric Number" required>

                <select name="department" required>
                    <option value="">Select Department</option>
                    <option value="computer-science">Computer Science</option>
                    <option value="mass-communication">Mass Communication</option>
                    <option value="accountancy">Accountancy</option>
                </select>

                <select name="level" required>
                    <option value="">Select Level</option>
                    <option value="ND1">ND1</option>
                    <option value="ND2">ND2</option>
                    <option value="ND3">ND3</option>
                    <option value="HND1">HND1</option>
                    <option value="HND2">HND2</option>
                    <option value="HND3">HND3</option>
                </select>

                <select name="programme" required>
                    <option value="">Select Programme</option>
                    <option value="Full-time">Full-time</option>
                    <option value="Part-time">Part-time</option>
                    <option value="CODFEL">CODFEL</option>
                </select>

                <div class="password-wrapper">
                    <input type="password" name="password" id="password" placeholder="Password" required>
                    <i class="ri-eye-line toggle-eye" data-target="password"></i>
                </div>

                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                    <i class="ri-eye-line toggle-eye" data-target="confirm_password"></i>
                </div>

                <button type="submit">Sign Up</button>

            </form>

            <p class="switch-link">
                Already have an account? <a href="login.php">Login</a>
            </p>

        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
</body>

</html>