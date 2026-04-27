<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';

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

// Increment views (skip for own listing — checked after session load)
$isOwnListing = isLoggedIn() && ($listing['seller_id'] === (int)$_SESSION['user_id']);

if (!$isOwnListing) {
    $stmt = $pdo->prepare('UPDATE listings SET views = views + 1 WHERE listing_id = ?');
    $stmt->execute([$listingId]);
    $listing['views'] = $listing['views'] + 1;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
$stmt->execute([$listing['seller_id']]);
$seller = $stmt->fetch();

$stmt = $pdo->prepare('SELECT ROUND(AVG(score), 1) FROM ratings WHERE receiver_id = ?');
$stmt->execute([$listing['seller_id']]);
$sellerRating = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(listing_id) FROM listings WHERE seller_id = ?');
$stmt->execute([$listing['seller_id']]);
$count = $stmt->fetchColumn();

$images = !empty($listing['media_path'])
    ? explode('#', trim($listing['media_path'], '#'))
    : ['placeholder.png'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_string($listing['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php require_once '../reuse/nav.php';?>
<div class="container py-4">
    <div class="row g-4">

        <!-- LEFT: Images + Details -->
        <div class="col-lg-8">

            <!-- Main Image -->
            <div class="card-custom mb-3" style="padding:0; overflow:hidden; border-radius:12px;">
                <img src="../img/<?= sanitize_string($images[0]) ?>"
                     class="img-cover"
                     id="mainImage"
                     style="height:420px;">
            </div>

            <!-- Thumbnail Strip -->
            <?php if (count($images) > 1): ?>
            <div class="d-flex gap-2 mb-3 overflow-auto pb-1">
                <?php foreach ($images as $img): ?>
                    <img src="../img/<?= sanitize_string($img) ?>"
                         class="thumbnail rounded <?= $img === $images[0] ? 'border border-primary border-2' : 'border' ?>"
                         style="width:72px; height:72px; object-fit:cover; cursor:pointer; flex-shrink:0;"
                         onclick="switchImage(this)">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Listing Details -->
            <div class="card-custom">
                <h2 class="fw-bold mb-1" style="color:var(--primary);">
                    <?= formatPrice((float)$listing['price']) ?>
                </h2>

                <div class="d-flex gap-3 text-muted small mb-2">
                    <span><?= (int)$listing['views'] ?> view<?= $listing['views'] == 1 ? '' : 's' ?></span>
                    <span>·</span>
                    <span>Posted <?= timeAgo($listing['created_at']) ?></span>
                    <span>·</span>
                    <span><?= (int)$listing['amount'] ?> available</span>
                </div>

                <p class="mb-0"><?= sanitize_string($listing['description']) ?></p>
            </div>

        </div>

        <!-- RIGHT: Sidebar -->
        <div class="col-lg-4">
            <div class="position-sticky" style="top: 20px;">

                <!-- Title -->
                <h1 class="h4 fw-bold mb-3"><?= sanitize_string($listing['title']) ?></h1>

                <!-- Seller Card -->
                <div class="card-custom mb-3">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img src="../img/<?= sanitize_string($seller['profile_picture_path'] ?? 'guest.png') ?>"
                             class="avatar"
                             style="width:48px; height:48px;">
                        <div>
                            <a href="/PROJECT/public_site/profile/profile-view.php?user_id=<?= (int)$seller['user_id'] ?>"
                               class="fw-semibold text-decoration-none" style="color:var(--text-dark);">
                                <?= sanitize_string($seller['username']) ?>
                            </a>
                            <div>
                                <input type="range" min="0" max="5" step="0.5"
                                       value="<?= (float)($sellerRating ?? 0) ?>"
                                       class="rating"
                                       style="--val:<?= (float)($sellerRating ?? 0) ?>;"
                                       disabled>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2 text-muted small mb-2">
                        <img src="../img/location.png" style="width:14px;">
                        <span><?= sanitize_string($seller['address'] ?? 'No address provided') ?></span>
                    </div>

                    <hr class="my-2">

                    <div class="small text-muted">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <img src="../img/email-icon-614x460.png" style="width:14px;">
                            <a href="mailto:<?= sanitize_string($seller['email']) ?>" class="text-decoration-none" style="color:var(--text-light);">
                                <?= sanitize_string($seller['email']) ?>
                            </a>
                        </div>
                        <span>Joined <?= date('jS F Y', strtotime($seller['created_at'] ?? 'now')) ?></span>
                        &nbsp;·&nbsp;
                        <span><?= $count ?> ads</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <?php if ($isOwnListing): ?>
                    <a href="/PROJECT/public_site/listings/listing-create-edit.php?listing_id=<?= $listingId ?>"
                       class="btn btn-outline-secondary w-100 mb-2">
                        Edit Listing
                    </a>

                <?php elseif (isLoggedIn()): ?>
                    <a href="/PROJECT/public_site/transactions/checkout.php?listing_id=<?= $listingId ?>"
                       class="btn btn-primary w-100 mb-2">
                        Buy Now
                    </a>
                    <a href="/PROJECT/public_site/communication/messages.php?with=<?= (int)$seller['user_id'] ?>"
                       class="btn btn-outline-secondary w-100">
                        Message Seller
                    </a>

                <?php else: ?>
                    <a href="/PROJECT/public_site/home/auth/login.php"
                       class="btn btn-primary w-100">
                        Login to Buy
                    </a>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/script.js"></script>
<script>
function switchImage(thumbnail) {
    const main = document.getElementById('mainImage');
    main.src = thumbnail.src;
    document.querySelectorAll('.thumbnail').forEach(t => {
        t.classList.remove('border-primary', 'border-2');
        t.classList.add('border');
    });
    thumbnail.classList.add('border-primary', 'border-2');
    thumbnail.classList.remove('border');
}
</script>
</body>
</html>