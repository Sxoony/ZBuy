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

 $stmt = $pdo->prepare('SELECT * FROM listings WHERE NOT(status="sold")');
$stmt->execute();
$listings = $stmt->fetchAll();
    
if (isLoggedIn()){
$stmt = $pdo->prepare('SELECT 
    t.transaction_id,
    IF(t.buyer_id = ?, l.seller_id, t.buyer_id) AS receiver_id
FROM transactions t
INNER JOIN listings l ON l.listing_id = t.listing_id
LEFT JOIN ratings r ON (
    r.transaction_id = t.transaction_id 
    AND r.reviewer_id = ?
)
WHERE (t.buyer_id = ? OR l.seller_id = ?)
  AND t.transaction_id IS NOT NULL 
  AND t.transaction_id > 0
  AND r.rating_id IS NULL
LIMIT 1');
$stmt->execute([$_SESSION['user_id'],$_SESSION['user_id'],$_SESSION['user_id'],$_SESSION['user_id']]);
$eligibleTransaction = $stmt->fetchAll();
$receiverId=$eligibleTransaction[0]['receiver_id'];
}

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
            <button class="label" id ="sideBarSellBtn">Sell</button><br><br>
   <button class="label" id="sideBarMessagesBtn">
   Message
</button>
        </ul>
    </div>


    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- NAVBAR -->
        <nav class="headerNav">

            <a href="index.php">Home</a>
            <a href="profile/profile-view.php">Account</a>
            <a href="home/auth/settings.php">Settings</a>
            <img src="img/notificationbell.png" alt="placeholder.jpg" class="homeIcons" id="notificationOpen">
 
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


            <form class="profileCard" method="POST">

                <img src="img/<?php if (isLoggedIn()):?><?=sanitize_string($_SESSION['profile_picture_path'] ?? "guest.png")?><?php endif;?>" alt="Guest Profile"  class="accountIcons">

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

            <button id="logOutBtn">
                <?php if (isLoggedIn()): ?>
                Logout
                <?php else: ?>
               Login
                <?php endif; ?>
            </button>

            <br><br>

            <button id="registerUser">
                Register
            </button>

  

    </dialog>

     <dialog id="successModal" >

        <form action="" id = "successForm" method ="POST" class="register-modal-content">

            <span class="close-btn">
                &times;
            </span>

            <h2>Success!</h2>
<?php if ($modal_error): ?>
    <div class="error-banner"><?= sanitize_string($modal_error) ?></div>
<?php endif; ?>
           
            <button type="submit" name="closeSuccess" value="Register" id="closeSuccess" class="registerbtn">

                Return To Home 
                

            </button>
        </form>

    </dialog>

    <!-- REGISTER MODAL -->
    <dialog id="registerModal" >

        <form action="" id = "registerMForm" method ="POST" class="register-modal-content">

            <span class="close-btn" id ="close-btn">
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
<?php if ($error): ?>
    <div class="error-banner"><?= sanitize_string($error) ?></div>
<?php endif; ?><br>
            <button type="submit" name="register_user" value="Register" id="registerButtonPopup" class="registerbtn">

                Register

            </button>
            
        </form>

    </dialog>
<?php if ($modal_error): ?>
    document.addEventListener('DOMContentLoaded', () => popup.showModal());
<?php endif; ?>

<dialog id="reviewModal">
    <form method="POST" action="/PROJECT/public_site/communication/rate.php" id="reviewForm">
     

        <button type="button" class="close-btn" id="closeReview">&times;</button>
        <h2>Leave a Review</h2>
   <input type="text" name="transaction_id" value="<?= (int)$eligibleTransaction ?>">
        <input type="text" name="receiver_id" value="<?= (int)$receiverId ?>">
        <label for="reviewScore">Rating (0–5)</label>
        <input type="range" name="score" id="reviewScore"
               min="0" max="5" step="0.5" value="0"
               class="rating" style="--val:0"
               oninput="this.style.setProperty('--val', this.value)">
        <span id="scoreDisplay">0 / 5</span>

        <label for="reviewComment">Comment</label>
        <textarea name="comment" id="reviewComment"
                  placeholder="Describe your experience..."
                  rows="4" maxlength="500"></textarea>
        <small class="error-message" id="reviewError"></small>

        <button type="submit" name="submitReview">Submit Review</button>
    </form>
</dialog>
    <script src="js/script.js"></script>

</body>

</html>