<?php
require_once __DIR__ . '/../includes/db.php';
session_start();

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $matric_number = strtoupper(trim($_POST['matric_number']));
    $password = $_POST['password'];

    $sql = "SELECT id, uuid, fullname, password_hash, role, failed_logins 
            FROM users WHERE matric_number = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $matric_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {

            $update = $conn->prepare("UPDATE users SET failed_logins=0, last_login=NOW() WHERE id=?");
            $update->bind_param("i", $user['id']);
            $update->execute();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['uuid'] = $user['uuid'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            header("Location: ../home.php");
            exit();
        } else {

            $conn->query("UPDATE users SET failed_logins = failed_logins + 1 WHERE matric_number='$matric_number'");
            $errors[] = "Invalid credentials.";
        }
    } else {
        $errors[] = "Invalid credentials.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to NACOS Digital Library — access course materials, books and resources.">
    <meta name="author" content="NACOS Digital Library">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0a74da">
    <meta property="og:title" content="NACOS Digital Library — Login">
    <meta property="og:description" content="Sign in to access the NACOS Digital Library. Browse and read protected resources for students.">
    <meta property="og:image" content="../assets/images/YCT_LOGO.png">
    <meta name="twitter:card" content="summary_large_image">
    <title>NACOS Digital Library | Login</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="icon" href="../assets/images/YCT_LOGO.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

    <div class="auth-wrapper">
        <div class="auth-box">

            <img src="../assets/images/NACOS_LOGO.png" alt="NACOS Logo" class="auth-logo">

            <h2>Welcome Back</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $e) echo "<p>$e</p>"; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">

                <input type="text" name="matric_number" placeholder="Matric Number" required>

                <div class="password-wrapper">
                    <input type="password" name="password" id="login_password" placeholder="Password" required>
                    <i class="ri-eye-line toggle-eye" data-target="login_password"></i>
                </div>

                <p class="forgot-link"><a href="forgot_password.php">Forgot Password ?</a></p>

                <button type="submit">Login</button>

            </form>

            <p class="switch-link">
                Don't have an account? <a href="signup.php">Sign Up</a>
            </p>

        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
</body>

</html>