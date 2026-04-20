<?php
require_once '../../reuse/db-conn.php';
require_once '../../reuse/authHelper.php';
require_once '../../reuse/functions.php';

if (isLoggedIn()) {
    redirect("/PROJECT/public_site/index.php");
}

$error ='';
$success ='';

if ($_SERVER['REQUEST_METHOD'] =='POST'){

$username = post('username');
$email = post('regEmail');
$password=$_POST['password'] ?? '';
$confirm=$_POST['confirmPassword'] ?? '';


if (!notEmptyValue($username) || !notEmptyValue($email) || !notEmptyValue($password)){
$error ='Please fill in all the fields';
}elseif (!isLengthBetween($username, 3, 80)){
$error = 'Username must be between 3 and 80 characters';
}elseif (!validate_email($email)){ 
$error = 'Please enter a valid email address';
}elseif (strlen($password)<6){
$error = 'Password must be at least 6 characters';
}elseif ($password !== $confirm){
    $error='Passwords do not match';
}else{

$stmt = $pdo ->prepare ('SELECT user_id FROM users WHERE email = ? OR username = ? LIMIT 1');
$stmt ->execute([$email, $username]);
if ($stmt->fetch()){
    $error = 'Email or username already exists';
}else{
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, username, full_name) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $username, '']);
    $userId= $pdo->lastInsertId();
    loginUser([
    'user_id'=> $userId,
    'username' => $username,
    'email' => $email,
    'role'=> 'user',
    'is_banned'=>0,
   
    ]);
    redirect('/PROJECT/public_site/index.php');
}

}
}

?>



<!DOCTYPE html>
<html>
<head>
    <title>My Tech Store - Register</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
   
</head>
<body class="auth-page">
 
<div class="form-container">
    <h2>Register</h2>
 <?php if ($error): ?>
    <div class = "error-banner"><?=sanitize_string($error) ?></div>
    <?php endif; ?>

    <form action ="" id="registerForm" method="POST">
 
        <div class="form-group" method="POST">
            <input type="text" name = "username" id="username" placeholder="Username" value = "<?=  sanitize_string($_POST['username'] ?? '') ?>">
            <small class="error-message" id="usernameError"></small><br>
 
            <input type="email" name ="regEmail" id="regEmail" class ="email" placeholder="Email" value = "<?= sanitize_string($_POST['regEmail'] ?? '') ?>">
            <small class="error-message" id="emailError"></small><br>
   
            <input type="password" name="password" id="password" class = "password" placeholder="Password">
            <small class="error-message" id="passwordError"></small><br>

            <input type="password" name = "confirmPassword"id="confirmPassword" class = "confirmpass" placeholder="Confirm Password">
            <small class="error-message" id="confirmPasswordError"></small>
        </div>
 
        <button type="submit" value="Register"  id = "registerButton" class = "loginButton">Register</button>
        
    </form>
 
    <div class="switch-link">
        Already have an account?
        <a href="login.php">Login</a>
    </div>
</div>
 
<script src="../../js/script.js"></script>
</body>
</html>