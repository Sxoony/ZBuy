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
    <title>Login — ZBuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body.auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-main);
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 2.5rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        }

        .auth-card .brand {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .auth-card h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }

        .auth-card .form-control {
            background: var(--bg-main);
            border: 1px solid var(--border);
            color: var(--text-dark);
            border-radius: 8px;
            padding: 0.65rem 0.9rem;
        }

        .auth-card .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
            background: var(--bg-card);
        }

        .auth-card .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.35rem;
        }

        .btn-auth {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.65rem;
            font-weight: 600;
            font-size: 0.95rem;
            width: 100%;
            transition: background 0.2s;
        }

        .btn-auth:hover {
            background: var(--secondary);
            color: #fff;
        }

        .auth-divider {
            text-align: center;
            color: var(--text-light);
            font-size: 0.85rem;
            margin: 1.25rem 0;
        }

        .auth-switch {
            text-align: center;
            font-size: 0.88rem;
            color: var(--text-light);
        }

        .auth-switch a {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
        }

        .auth-switch a:hover {
            color: var(--secondary);
        }

        .error-banner {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
            border-radius: 8px;
            padding: 0.6rem 0.9rem;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .error-message {
            color: #dc2626;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: block;
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap .show-password-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: var(--text-light);
            padding: 0;
        }
    </style>
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="brand">ZBuy Marketplace</div>
    <h2>Welcome back</h2>

    <?php if ($error): ?>
        <div class="error-banner"><?= sanitize_string($error) ?></div>
    <?php endif; ?>

    <form action="" id="loginForm" method="POST">
        <div class="mb-3">
            <label for="loginEmail" class="form-label">Email address</label>
            <input type="email" id="loginEmail" name="loginEmail"
                   class="form-control"
                   placeholder="you@example.com"
                   value="<?= sanitize_string($_POST['loginEmail'] ?? '') ?>">
            <small class="error-message" id="loginEmailError"></small>
        </div>

        <div class="mb-4">
            <label for="loginPassword" class="form-label">Password</label>
            <div class="password-wrap">
                <input type="password" id="loginPassword" name="loginPassword"
                       class="form-control" placeholder="••••••••">
                <button type="button" class="show-password-btn" id="toggleLoginPassword">👁</button>
            </div>
            <small class="error-message" id="loginPasswordError"></small>
        </div>

        <button type="submit" id="loginButton" class="btn-auth">Sign in</button>
    </form>

    <div class="auth-divider">or</div>

    <div class="auth-switch">
        Don't have an account? <a href="register.php">Create one</a>
    </div>
    <div class="auth-switch mt-2">
        <a href="../../index.php">Continue as guest</a>
    </div>
</div>

<script src="../../js/script.js"></script>
<script>
    const toggleLoginPassword = document.getElementById('toggleLoginPassword');
    const loginPasswordInput  = document.getElementById('loginPassword');
    if (toggleLoginPassword) {
        toggleLoginPassword.addEventListener('click', function() {
            const isPass = loginPasswordInput.type === 'password';
            loginPasswordInput.type = isPass ? 'text' : 'password';
            this.textContent = isPass ? '🙈' : '👁';
        });
    }
</script>
</body>
</html>