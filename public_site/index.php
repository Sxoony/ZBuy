<?php
require_once 'reuse/db-conn.php';
require_once 'reuse/authHelper.php';
require_once 'reuse/functions.php';
$error = '';
$modal_error  = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = post('email');
    $password = $_POST['modal_password'] ?? '';

    if (!notEmptyValue($email) || !notEmptyValue($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!validate_email($email)) {
        $error = 'Invalid email address.';
    } else {
        
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'That email is already registered.';
        } else {

            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, username, full_name) VALUES (?, ?, ?, ?)');
            $stmt->execute([$email, $hash, 'temp', '']);

            $userId = $pdo->lastInsertId();

           

            
 $stmt2 = $pdo->prepare('UPDATE users SET username = ? WHERE user_id = ?');
            $stmt2->execute(['user' . $userId, $userId]);
            loginUser([
                'user_id'  => $userId,
                'username' => 'user' . $userId,
                'email'    => $email,
                'role'     => 'user',
                'is_banned'=> 0,
            ]);
            redirect('/PROJECT/public_site/index.php'); 
        }
    }


   
   
}
 $stmt = $pdo->prepare('SELECT * FROM listings ');
$stmt->execute();
$listings = $stmt->fetchAll();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - My Tech Store</title>

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body >

    <!-- SIDEBAR -->
    <div class="sidebar" id="sideBar">

        <h2>Marketplace</h2>

        <span class="searchContainer">
            <img src="img/search.png" alt="Search Icon" class="homeIcons">

            <input type="search" name="search" id="searchBar" placeholder="Search">
        </span>
        <ul> 
           <button class="label" id ="sideBarBrowseBtn">Browse</button><br><br>
            <button class="label" id ="sideBarBuyeBtn">Buy</button><br><br>
            <button class="label" id ="sideBarSellBtn">Sell</button><br><br>
            <button class="label" id ="sideBarMessagesBtn">Messages</button><br><br>
        </ul>
    </div>


    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- NAVBAR -->
        <nav class="headerNav">

            <a href="index.php">Home</a>
            <a href="profile/profile-view.php">Account</a>
            <a href="home/auth/settings.php">Settings</a>
            <img src="img/notificationbell.png" alt="placeholder.jpg" class="homeIcons" id="notificationIcon">
 
                <img src="img/settings.png" alt="Settings" class="homeIcons" class="viewAccount" id="viewAccount" >
   

        </nav>


        <!-- PRODUCT SECTION -->
        <div class="product-section" id="product-section">

            <?php
            foreach ($listings as $listing) {
 echo '<div class="product-card">';
                echo '<img src="img/' . sanitize_string($listing['media_path'] ?? 'placeholder.png') . '" alt="Product Image">';
                echo '<h3> <a href="listings/listing-view.php?listing_id=' . (int)$listing['listing_id'] . '">' . sanitize_string($listing['title']) . '</a> <span style=color:' . ($listing['status'] == 'sold' ? 'red' : 'green') .  '>(' . htmlspecialchars(sanitize_string($listing['status'])) . ')</span></h3>'; 
                echo '<p>$' . sanitize_string($listing['price']) . '</p>';
                echo '</div>';
            }
?>
            

        

    </div>


    <!-- ACCOUNT MODAL -->
    <dialog class="accountModal" id="accountModal">

        <div class="profile-container" id="profile-container">

            <form class="profileCard" method="POST">

                <img src="img/confusedcat.png" alt="Guest Profile" class="accountIcons">

                <div class="profileName">
                    <?php if (isLoggedIn()): ?>
                    <?= sanitize_string($_SESSION['username']) ?>
                    <?php else: ?>
                    Guest
                    <?php endif; ?>

                </div>
                <a href="home/auth/settings.php" class="viewAccount">
                    Account Settings
                </a>


            </form>

            </button>

            <button id="logoutBtn">
                <?php if (isLoggedIn()): ?>
                <a href="home/auth/logout.php">Logout</a>
                <?php else: ?>
                <a href="home/auth/login.php">Login</a>
                <?php endif; ?>
            </button>

            <br><br>

            <button id="registerUser">
                Register
            </button>

        </div>

    </dialog>


    <!-- REGISTER MODAL -->
    <dialog id="registerModal" >

        <form action="" id = "registerMForm" method ="POST" class="register-modal-content">

            <span class="close-btn">
                &times;
            </span>

            <h2>Create Account</h2>
<?php if ($modal_error): ?>
    <div class="error-banner"><?= sanitize_string($modal_error) ?></div>
<?php endif; ?>
            <input type="email" id="emailRegModal" name="email" class="email" required placeholder="Email Address"value = "<?=  sanitize_string($_POST['email'] ?? '') ?>">
                    <small  class="error-message" id = "emailErrorM" name="emailError"></small>
            <br>

            <input type="password" id="passwordRegModal" name="modal_password" class="password" required placeholder="Password">
 <small  class="error-message" id = "passErrorM" name="passError"></small>
            <br>

            <button type="submit" name="register_user" value="Register" id="registerButtonPopup" class="registerbtn">

                Register

            </button>
            
        </form>

    </dialog>
<?php if ($modal_error): ?>
    document.addEventListener('DOMContentLoaded', () => popup.showModal());
<?php endif; ?>


    <script src="js/script.js"></script>

</body>

</html>