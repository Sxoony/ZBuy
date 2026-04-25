<?php
require_once '../../reuse/db-conn.php';
require_once '../../reuse/authHelper.php';
require_once '../../reuse/functions.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings Screen</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="form-container">
        <h2>Settings</h2>
       
            <!-- To be added: Dark Mode, Languages, Log Out, Notification Preferences-->
            <div class="form-group">
                <button class="menu-row" id = "darkmodeBtn">
                <label class="toggle-label">
    Dark Mode
    <input type="checkbox" id="darkModeToggle">
    <span class="toggle-track">
        <span class="toggle-thumb"></span>
    </span>
</label>
                </button>

                <button class="menu-row"  >
                    <img src="../../img/language-multilingual.png" alt="" class="accountIcons">
                    <span class="label">Languages</span>
                </button>

                    <button class="menu-row">
                        <img src="../../img/notificationbell.png" alt="" class="accountIcons">
                        <span class="label">Notification Preferences</span>
                        </button>

                    <button type="button" id = "logoutBtn"><a href="logout.php">Logout</a></button>
            </div>
            <button type="button" name="saveSettings">Save Settings</button>

      
    </div>
<!-- Add a way to view transactions. Edit profile, change password, delete account. Make the standard settings a modal.-->

<dialog id="settingsModal">
    <form method="POST" action="/public_site/home/settings.php"
          id="settingsForm" enctype="multipart/form-data">

        <button type="button" class="close-btn" id="closeSettings">&times;</button>
        <h2>Edit Profile</h2>

        <label for="settingsUsername">Username</label>
        <input type="text" name="settingsUsername" id="settingsUsername"
               value="<?= sanitize_string($_SESSION['username']) ?>">

        <label for="settingsFullName">Full Name</label>
        <input type="text" name="settingsFullName" id="settingsFullName"
               value="<?= sanitize_string($_SESSION['full_name'] ?? '') ?>">

        <label for="settingsAddress">Address</label>
        <input type="text" name="settingsAddress" id="settingsAddress"
               value="<?= sanitize_string($_SESSION['address'] ?? '') ?>">

        <label for="settingsPassword">New Password <span class="optional-tag">leave blank to keep current</span></label>
        <div class="password-wrap">
            <input type="password" name="settingsPassword" id="settingsPassword"
                   placeholder="New password">
            <button type="button" class="show-password-btn" id="togglePassword">👁</button>
        </div>

        <label for="settingsProfilePic">Profile Picture</label>
        <input type="file" name="settingsProfilePic" id="settingsProfilePic"
               accept="image/jpeg,image/png,image/webp">

        <small class="error-message" id="settingsError"></small>

        <button type="submit" name="saveSettings">Save Changes</button>
    </form>
</dialog>
    <script src="../../js/script.js" defer></script>

</body>

</html>