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

                <label for="darkmodeCheckbox" >Dark Mode</label><br><br>
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
            <button type="button">Save Settings</button>

      
    </div>
<!-- Add a way to view transactions. Edit profile, change password, delete account. Make the standard settings a modal.-->
    <script src="../../js/script.js" defer></script>

</body>

</html>