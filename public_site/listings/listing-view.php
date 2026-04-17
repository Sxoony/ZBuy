<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';

// No requireLogin() — listings are public
// But we still need to know if someone is logged in for the buttons

$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : 0;

if (!$listingId) {
    redirect('/PROJECT/public_site/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM listings WHERE listing_id = ?');
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    redirect('/PROJECT/public_site/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$listing['seller_id']]);
$seller = $stmt->fetch();

$stmt = $pdo->prepare('SELECT ROUND(AVG(score), 1) FROM ratings WHERE receiver_id = ?');
$stmt->execute([$listing['seller_id']]);
$sellerRating = $stmt->fetchColumn();

$images = !empty($listing['media_path'])
    ? explode('#', trim($listing['media_path'], '#'))
    : ['placeholder.png'];
$stmt = $pdo->prepare('SELECT COUNT(listing_id) FROM listings WHERE seller_id = ?');
$stmt->execute([$listing['seller_id']]);
$count = $stmt->fetchColumn();
// Only determine ownership if someone is actually logged in
$isOwnListing = isLoggedIn() && ($listing['seller_id'] === (int)$_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_string($listing['title']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <div class="listing-page">

        <!-- LEFT: images + listing details -->
        <div class="listing-main">

            <div class="listing-media">
                <div class="main-image-container">
                    <img src="../img/<?= sanitize_string($images[0]) ?>" class="main-image" id="mainImage">
                </div>
                <div class="thumbnail-strip">
                    <?php foreach ($images as $img): ?>
                        <img src="../img/<?= sanitize_string($img) ?>"
                            class="thumbnail <?= $img === $images[0] ? 'active' : '' ?>"
                            onclick="switchImage(this)">
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="listing-details">
                <p class="listing-price"><?= formatPrice((float)$listing['price']) ?></p>
                
                <p class="listing-stock"><?= (int)$listing['amount'] ?> available</p>
                
                <p class="listing-description"><?= sanitize_string($listing['description']) ?></p>
            </div>

        </div>

        <!-- RIGHT: seller sidebar -->
        <div class="listing-sidebar">

            <h1 class="listing-title"><?= sanitize_string($listing['title']) ?></h1>

           <div class="seller-card">
    <img src="../img/<?= sanitize_string($seller['profile_picture_path'] ?? 'guest.png') ?>"
        alt="Seller" class="seller-avatar">

    <div class="seller-info">
        <div class="seller-top">
            <a href="/PROJECT/public_site/profile/profile-view.php?user_id=<?= (int)$seller['user_id'] ?>"
                class="seller-name">
                <?= sanitize_string($seller['username']) ?>
            </a>

            <input type="range" min="0" max="5" step="0.5"
                value="<?= (float)($sellerRating ?? 0) ?>"
                class="rating"
                style="--val:<?= (float)($sellerRating ?? 0) ?>"
                disabled>
        </div>

        <div class="seller-address">
            <img src="../img/location.png" alt="Location">
            <span>
                <?= sanitize_string($seller['address'] ?? 'No address provided') ?>
            </span>
        </div>
    </div>
</div>
<div class="aboutCard"> 
    <h2>About the Seller</h2>

    <div class="contactRow">
        <img src="../img/email-icon-614x460.png" alt="email" class="accountIcons">
        <span class="contact"><a href="mailto:<?= sanitize_string($seller['email'])?>" class="contactRow"><?= sanitize_string($seller['email'])?></a></span>
    </div>

    <div class="aboutInfo">
        <span>Joined <?= date('jS F Y', strtotime($seller['created_at'] ?? 'now')) ?></span>
        <span>Total Ads <?= $count ?></span>
    </div>
</div>
            <?php if ($isOwnListing): ?>
                <a href="/PROJECT/public_site/listings/listing-create-edit.php?listing_id=<?= $listingId ?>" class="btn-edit">
                    Edit Listing
                </a>

            <?php elseif (isLoggedIn()): ?>
                <a href="/PROJECT/public_site/transactions/checkout.php?listing_id=<?= $listingId ?>" class="btn-buy">
                    Buy Now
                </a>
                <a href="/PROJECT/public_site/communication/messages.php?receiver_id=<?= (int)$seller['user_id'] ?>" class="btn-message">
                    Message Seller
                </a>

            <?php else: ?>
                <a href="/PROJECT/public_site/home/auth/login.php" class="btn-buy">
                    Login to Buy
                </a>
            <?php endif; ?>

        </div>
    </div>

    <script src="../js/script.js"></script>
</body>

</html>