<?php
require_once '../../reuse/db-conn.php';
require_once '../../reuse/authHelper.php';
require_once '../../reuse/functions.php';

if (isLoggedIn()) {
    redirect("/PROJECT/public_site/index.php");
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $email    = post('regEmail');
    $fullName = post('fullName');
    $address  = post('address');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirmPassword'] ?? '';

    if (!notEmptyValue($username) || !notEmptyValue($email) || !notEmptyValue($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!isLengthBetween($username, 3, 80)) {
        $error = 'Username must be between 3 and 80 characters.';
    } elseif (!validate_email($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = 'Email or username already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);

            // Handle profile picture upload
            $profilePicPath = 'guest.png';
            $uploadDir = __DIR__ . '/../../img/';
            if (!empty($_FILES['profilePic']['tmp_name']) && $_FILES['profilePic']['error'] === UPLOAD_ERR_OK) {
                $fileName = uniqid() . '_' . basename($_FILES['profilePic']['name']);
                if (move_uploaded_file($_FILES['profilePic']['tmp_name'], $uploadDir . $fileName)) {
                    $profilePicPath = $fileName;
                }
            }

            $stmt = $pdo->prepare('
                INSERT INTO users (email, password_hash, username, full_name, address, profile_picture_path)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$email, $hash, $username, $fullName, $address, $profilePicPath]);
            $userId = $pdo->lastInsertId();

            loginUser([
                'user_id'              => $userId,
                'username'             => $username,
                'full_name'            => $fullName,
                'email'                => $email,
                'role'                 => 'user',
                'is_banned'            => 0,
                'address'              => $address,
                'profile_picture_path' => $profilePicPath,
            ]);
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
    <title>Register — ZBuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body.auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-main);
            padding: 2rem 1rem;
        }

        .auth-card {
            width: 100%;
            max-width: 480px;
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
            margin-bottom: 0.5rem;
        }

        .auth-card .subtitle {
            font-size: 0.88rem;
            color: var(--text-light);
            margin-bottom: 1.75rem;
        }

        .section-label {
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-light);
            margin: 1.5rem 0 0.75rem;
            border-top: 1px solid var(--border);
            padding-top: 1.25rem;
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

        .optional-tag {
            font-size: 0.75rem;
            font-weight: 400;
            color: var(--text-light);
        }

        /* Profile picture upload */
        .avatar-upload {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .avatar-preview {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            background: var(--bg-main);
            flex-shrink: 0;
        }

        .avatar-upload-btn {
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            color: var(--text-dark);
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .avatar-upload-btn:hover {
            border-color: var(--primary);
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
            margin-top: 1.5rem;
        }

        .btn-auth:hover {
            background: var(--secondary);
            color: #fff;
        }

        .auth-switch {
            text-align: center;
            font-size: 0.88rem;
            color: var(--text-light);
            margin-top: 1.25rem;
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
    <h2>Create your account</h2>
    <p class="subtitle">Fields marked <span style="color:#dc2626">*</span> are required.</p>

    <?php if ($error): ?>
        <div class="error-banner"><?= sanitize_string($error) ?></div>
    <?php endif; ?>

    <form action="" id="registerForm" method="POST" enctype="multipart/form-data">

        <!-- PROFILE PICTURE -->
        <div class="section-label" style="border-top:none; padding-top:0; margin-top:0;">Profile Picture</div>
        <div class="avatar-upload">
            <img src="../../img/guest.png" alt="Preview" class="avatar-preview" id="avatarPreview">
            <div>
                <label for="profilePic" class="avatar-upload-btn">Choose photo</label>
                <input type="file" name="profilePic" id="profilePic"
                       accept="image/jpeg,image/png,image/webp" style="display:none;">
                <div style="font-size:0.78rem; color:var(--text-light); margin-top:0.35rem;">
                    JPG, PNG or WebP · max 5 MB
                </div>
            </div>
        </div>

        <!-- ACCOUNT DETAILS -->
        <div class="section-label">Account Details</div>

        <div class="mb-3">
            <label for="username" class="form-label">Username <span style="color:#dc2626">*</span></label>
            <input type="text" name="username" id="username"
                   class="form-control" placeholder="e.g. john_doe"
                   value="<?= sanitize_string($_POST['username'] ?? '') ?>">
            <small class="error-message" id="usernameError"></small>
        </div>

        <div class="mb-3">
            <label for="regEmail" class="form-label">Email address <span style="color:#dc2626">*</span></label>
            <input type="email" name="regEmail" id="regEmail"
                   class="form-control" placeholder="you@example.com"
                   value="<?= sanitize_string($_POST['regEmail'] ?? '') ?>">
            <small class="error-message" id="emailError"></small>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password <span style="color:#dc2626">*</span></label>
            <div class="password-wrap">
                <input type="password" name="password" id="password"
                       class="form-control" placeholder="At least 6 characters">
                <button type="button" class="show-password-btn" id="toggleRegPassword">👁</button>
            </div>
            <small class="error-message" id="passwordError"></small>
        </div>

        <div class="mb-3">
            <label for="confirmPassword" class="form-label">Confirm Password <span style="color:#dc2626">*</span></label>
            <input type="password" name="confirmPassword" id="confirmPassword"
                   class="form-control" placeholder="Repeat your password">
            <small class="error-message" id="confirmPasswordError"></small>
        </div>

        <!-- PERSONAL DETAILS -->
        <div class="section-label">Personal Details <span class="optional-tag">(optional — can be added later)</span></div>

        <div class="mb-3">
            <label for="fullName" class="form-label">Full Name <span class="optional-tag">optional</span></label>
            <input type="text" name="fullName" id="fullName"
                   class="form-control" placeholder="e.g. John Doe"
                   value="<?= sanitize_string($_POST['fullName'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="address" class="form-label">Address <span class="optional-tag">optional</span></label>
            <input type="text" name="address" id="address"
                   class="form-control" placeholder="e.g. 12 Main Street, Cape Town"
                   value="<?= sanitize_string($_POST['address'] ?? '') ?>">
        </div>

        <button type="submit" id="registerButton" class="btn-auth">Create Account</button>
    </form>

    <div class="auth-switch">
        Already have an account? <a href="login.php">Sign in</a>
    </div>
</div>

<script src="../../js/script.js"></script>
<script>
    // Avatar live preview
    const profilePicInput = document.getElementById('profilePic');
    const avatarPreview   = document.getElementById('avatarPreview');
    if (profilePicInput) {
        profilePicInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => avatarPreview.src = e.target.result;
                reader.readAsDataURL(file);
            }
        });
    }

    // Password toggle
    const toggleRegPassword = document.getElementById('toggleRegPassword');
    const regPasswordInput  = document.getElementById('password');
    if (toggleRegPassword) {
        toggleRegPassword.addEventListener('click', function() {
            const isPass = regPasswordInput.type === 'password';
            regPasswordInput.type = isPass ? 'text' : 'password';
            this.textContent = isPass ? '🙈' : '👁';
        });
    }
</script>
</body>
</html>