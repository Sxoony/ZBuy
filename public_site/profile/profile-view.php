<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();
$profileId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];
$isOwnProfile = ($profileId === (int)$_SESSION['user_id']);


$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$profileId]);
$profileUser = $stmt->fetch();

if (!$profileUser) {

    redirect('/PROJECT/public_site/index.php');
}
$stmt = $pdo->prepare('SELECT ROUND(AVG(score), 1) FROM ratings WHERE receiver_id = ?');
$stmt->execute([$profileId]);
$score = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(listing_id) FROM listings WHERE seller_id = ?');
$stmt->execute([$profileId]);
$count = $stmt->fetchColumn();
$activeCount = $pdo->prepare('SELECT COUNT(listing_id) FROM listings WHERE seller_id = ? AND status != "sold"');
$activeCount->execute([$profileId]);


$stmt = $pdo->prepare('SELECT * FROM listings WHERE seller_id = ?');
$stmt->execute([$profileId]);
$listings = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT COUNT(rating_id) FROM ratings WHERE receiver_id = ?');
$stmt->execute([$profileId]);
$countR = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM ratings WHERE receiver_id = ?');
$stmt->execute([$profileId]);
$ratings = $stmt->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveSettings'])) {

    $username = trim(post('settingsUsername'));
    $fullName = trim(post('settingsFullName'));
    $address  = trim(post('settingsAddress'));
    $password = $_POST['settingsPassword'] ?? '';

    $updates = [];
    $params  = [];

    if ($username !== '' && $username !== $_SESSION['username']) {
        // Check not taken by someone else
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? AND user_id != ?');
        $stmt->execute([$username, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            // handle error
        } else {
            $updates[] = 'username = ?';
            $params[]  = $username;
        }
    }

    if ($fullName !== '') {
        $updates[] = 'full_name = ?';
        $params[]  = $fullName;
    }

    if ($address !== '') {
        $updates[] = 'address = ?';
        $params[]  = $address;
    }

    // Only hash and update password if one was provided
    if ($password !== '') {
        $updates[] = 'password_hash = ?';
        $params[]  = password_hash($password, PASSWORD_BCRYPT);
    }

    // Profile picture upload
    if (!empty($_FILES['settingsProfilePic']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../img/';
        $fileName  = uniqid() . '_' . basename($_FILES['settingsProfilePic']['name']);
        if (move_uploaded_file($_FILES['settingsProfilePic']['tmp_name'], $uploadDir . $fileName)) {
            $updates[] = 'profile_picture_path = ?';
            $params[]  = $fileName;
        }
    }

    if (!empty($updates)) {
        $params[] = $_SESSION['user_id'];
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE user_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Refresh session with new values
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        loginUser($user);
    }

    redirect('/public_site/index.php');
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <div class="sidebar" id="sideBar">

        <h2>Marketplace</h2>

        <span class="searchContainer">
            <img src="../img/search.png" alt="Search Icon" class="homeIcons">

            <input type="search" name="search" id="searchBar" placeholder="Search">
        </span>
        <ul>
            <button class="label" id="sideBarBrowseBtn">Browse</button><br><br>
            <button class="label" id="sideBarBuyeBtn">Buy</button><br><br>
            <button class="label" id="sideBarSellBtn">Sell</button><br><br>
            <button class="label" id="sideBarMessagesBtn"><a href="../communication/messages.php">Messages</a></button><br><br>
        </ul>
    </div>


    <!-- PROFILE CONTAINER -->
    <div class="main-content">
        <div class="profile-container">

            <div class="profileCard" id="profileCard">
                <img src="../img/<?= sanitize_string($profileUser['profile_picture_path'] ?? "guest.png") ?>" alt="" class="profilePic">
                <span class="profileName"><?php if (isLoggedIn()): ?>
                        <?= sanitize_string($profileUser['full_name']) ?>
                    <?php endif; ?></span>
                <?php if ($isOwnProfile): ?>
                    <button class="profileEditBtn" id="profileEditBtn">Edit</button>
                <?php endif; ?>
            </div>
            <div class="subInfo">
                <input type="range" name="stars" id="starRating" min="0" max="5" step="0.5" value="<?= $score ?>" class="rating" style="--val:<?= $score ?>" oninput="this.style='--val:'+this.value">
                <BR></BR>
                <span>
                    <?=
                    sanitize_string($profileUser['address'] ?? 'No Address Provided');
                    ?>
                </span><br>
                <span>Joined <?= date('jS F Y', strtotime($profileUser['created_at'] ?? 'now')) ?>|</span>
                <span>Total Ads
                    <?= $count ?>
                </span>
            </div>
        </div>


        <h3>Active Ads (<?= $activeCount->fetchColumn() ?>)</h3>
        <div class="product-section">
            <?php
            foreach ($listings as $listing) {
                 
                if (!$isOwnProfile && $listing['status'] == 'sold') {
                    continue; // Skip sold items if not own profile
                }else{

                


                echo '<div class="product-card">';
                echo '<img src="../img/' . sanitize_string($listing['media_path'] ?? 'placeholder.png') . '" alt="Product Image">';
                echo '<h3> <a href="../listings/listing-view.php?listing_id=' . (int)$listing['listing_id'] . '">' . sanitize_string($listing['title']) . '</a> <span style=color:' . ($listing['status'] == 'sold' ? 'red' : 'green') .  '>(' . htmlspecialchars(sanitize_string($listing['status'])) . ')</span></h3>'; 
                echo '<p>$' . sanitize_string($listing['price']) . '</p>';
                echo '</div>';
                }




            }
            ?>
        </div>


        <h3>Reviews</h3>
        <div class="review-overview">
            <h4><?php
                echo $score;
                ?>
                </h4>
            <input type="range" name="stars" id="starRating" min="0" max="5" step="0.5" value="<?= $score ?>" class="rating" style="--val:<?= $score ?>" oninput="this.style='--val:'+this.value">
            <h4>
                <?php
                echo $countR;
                if ($countR == 1) {
                    echo " review";
                } else {
                    echo " reviews";
                }
                ?> 
                </h4>
        </div>

        <form action="profile.php" method="POST" class="product-section">
            <div class="review-card">
                <?php
                foreach ($ratings as $rating) {
                    $stmt = $pdo->prepare('SELECT profile_picture_path FROM users WHERE user_id = ?');
                    $stmt->execute([$rating['reviewer_id']]);
                    $pfpath = $stmt->fetchColumn();
                    echo '<div class="review-card">';
                    echo '<img src=../img/' . (sanitize_string($pfpath) ?? "guest.png"). ' alt="" class="accountIcons">';
                    echo '<a href="profile-view.php?user_id=' . (int)$rating['reviewer_id'] . '" class="profileName">'
                        . sanitize_string($profileUser['username']) . '</a>';
                    echo '<input type="range" name="stars" id="starRating" min="0" max="5" step="0.5" value="' . sanitize_string($rating['score']) . '" class="rating" style="--val:' . sanitize_string($rating['score']) . '" oninput="this.style=\'--val:\'+this.value">';
                    echo '<p class="review-comment">' . sanitize_string($rating['comment'] ?? '') . '</p>';
                    echo '</div>';
                }
                ?>

            </div>

    </div>
    </form>
<!-- REST TO DO: - add a hyperlink to the listing cards, add functionality to edit profile - full name, address, password, profile picture. -->
 <dialog id="settingsModal" class="register-modal-content">
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
    <script src="../js/script.js"></script>
</body>

</html>