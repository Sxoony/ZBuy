<?php

if (session_status() === PHP_SESSION_NONE){
    session_start();
}

//Helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUser():?array{
    if (!isLoggedIn()) return null;
    return [
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'full_name' => $_SESSION['full_name'] ?? $_SESSION['username'],
    'email'=> $_SESSION['email'],
    'role' => $_SESSION['role'],
    'is_banned' => $_SESSION['is_banned'],
    'permissions'=> $_SESSION['permissions'] ?? '',
    'address' => $_SESSION['address'] ?? '',
    'profile_picture_path' => $_SESSION['profile_picture_path'] ?? '',
    ];
}

function currentUserId():?int{
return $_SESSION['user_id'] ?? null;
}

function hasPermission(string $permission): bool {
    if (!isset($_SESSION['permissions'])) return false;
    $perms = array_map('trim', explode(',', $_SESSION['permissions']));
    return in_array($permission, $perms, true);
}

//Log In/Out

function loginUser(array $user):void{
    session_regenerate_id(true);
    $_SESSION['user_id']= $user['user_id'];
    $_SESSION['username']= $user['username'];
    $_SESSION['full_name']= $user['full_name'] ?? $user['username'];
    $_SESSION['email']= $user['email'];
    $_SESSION['role']= $user['role'];
    $_SESSION['is_banned']= $user['is_banned'];
    $_SESSION['address']= $user['address'] ?? '';
    $_SESSION['profile_picture_path']= $user['profile_picture_path'] ?? '';

}

function logoutUser(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: /PROJECT/public_site/home/auth/login.php');
    exit;
}

//Access Control

function requireLogin():void{
if (!isLoggedIn()){
    redirect('/PROJECT/public_site/home/auth/login.php');
    exit;
}

if ($_SESSION['is_banned']){
    logoutUser();
}

}

function requireAdmin (string $permission =''):void{
    requireLogin();

    if ($_SESSION['role'] !== 'admin'){
        http_response_code(403);
        die('Access denied.');
    }

    if ($permission !=='' && !hasPermission($permission)){
        http_response_code(403);
        die('Access denied. Missing required permission. ');
    }
}



?>