<?php
require_once '../../reuse/db-conn.php';
require_once '../../reuse/authHelper.php';
require_once '../../reuse/functions.php';


if (isLoggedIn()) {
    redirect('/PROJECT/public_site/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email    = post('loginEmail');
    $password = $_POST['loginPassword'] ?? ''; 

    
    if (!notEmptyValue($email) || !notEmptyValue($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email address.';
    } else {

       
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            
            $error = 'Incorrect email or password.';
        } elseif ($user['is_banned']) {
            $error = 'This account has been suspended.';
        } else {

            if ($user['role'] === 'admin') {
                $adminStmt = $pdo->prepare('SELECT permissions FROM users WHERE user_id = ? LIMIT 1');
                $adminStmt->execute([$user['user_id']]);
                $adminRow = $adminStmt->fetch();
                $user['permissions'] = $adminRow['permissions'] ?? '';
            }

            loginUser($user);
            redirect('/PROJECT/public_site/index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tech Store - Login</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="auth-page">

<div class="form-container">
    <h2>Login</h2>

    <?php if ($error): ?>
        <div class="error-banner"><?= sanitize_string($error) ?></div>
    <?php endif; ?>

    <form action="" id="loginForm" method="POST">

        <div class="form-group">
            <input type="email" id="loginEmail" name="loginEmail"
                   class="email" placeholder="Email"
                   value="<?= sanitize_string($_POST['loginEmail'] ?? '') ?>">
            <small class="error-message" id="loginEmailError"></small> <br><br>

            <input type="password" id="loginPassword" name="loginPassword"
                   class="password" placeholder="Password">
            <small class="error-message" id="loginPasswordError"></small>
        </div>

        <button type="submit" id="loginButton" class="loginButton">Login</button>
    </form>

    <div class="switch-link">
        Don't have an account?
        <a href="register.php">Register</a>
        <p>Or <a href="../../index.php">continue as guest</a></p>
    </div>
</div>

<script src="../../js/script.js"></script>
</body>
</html>