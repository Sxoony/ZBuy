<?php
require_once 'reuse/db-conn.php';
require_once 'reuse/authHelper.php';
require_once 'reuse/functions.php';

$error = '';

// Quick-register from modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {

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
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, username, full_name) VALUES (?, ?, "temp", "")');
            $stmt->execute([$email, $hash]);
            $userId = $pdo->lastInsertId();
            $pdo->prepare('UPDATE users SET username = ? WHERE user_id = ?')->execute(['user' . $userId, $userId]);
            loginUser([
                'user_id'   => $userId,
                'username'  => 'user' . $userId,
                'full_name' => '',
                'email'     => $email,
                'role'      => 'user',
                'is_banned' => 0,
                'address'   => '',
                'profile_picture_path' => '',
            ]);
            redirect('/PROJECT/public_site/index.php');
        }
    }
}

// Listings (exclude sold)
$stmt = $pdo->prepare('SELECT * FROM listings WHERE status != "sold" ORDER BY created_at DESC');
$stmt->execute();
$listings = $stmt->fetchAll();

if (isLoggedIn()) {
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
  AND t.status = "completed"
  AND r.rating_id IS NULL ');

    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
 
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($transaction){
    $receiverId = $transaction['receiver_id'];
    $eligibleTransaction=$transaction['transaction_id'];
        }else{
        $receiverId=null;
        $eligibleTransaction=null;
    }

    }
// Nav depth = 0 (we are at public_site root)
$nav_depth = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Override nav img path for root-level nav */
        .listing-badge-sold   { color: #ef4444; }
        .listing-badge-avail  { color: #22c55e; }
    </style>
</head>
<body>


<?php require_once 'reuse/nav.php';?>
<div class="container py-4">

    <!-- ── Hero row (guest only) ── -->
    <?php if (!isLoggedIn()): ?>
    <div class="card-custom mb-4 text-center py-5">
        <h1 class="fw-bold mb-2">Buy &amp; Sell, Locally</h1>
        <p class="mb-3">Join thousands of people trading on Marketplace.</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="home/auth/register.php" class="btn btn-primary px-4">Get Started</a>
            <a href="home/auth/login.php"    class="btn btn-outline-secondary px-4">Login</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Listings grid ── -->
    <h4 class="fw-semibold mb-3">Latest Listings</h4>

    <?php if (empty($listings)): ?>
        <div class="text-center text-muted py-5">No listings yet. Be the first to post!</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($listings as $listing):
                $firstImg = explode('#', trim($listing['media_path'] ?? 'placeholder.png', '#'))[0];
            ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm" style="border-color:var(--border); border-radius:12px; overflow:hidden;">
                        <img src="img/<?= sanitize_string($firstImg) ?>"
                             style="height:180px; object-fit:cover; width:100%;">
                        <div class="card-body p-3">
                            <h6 class="mb-1 fw-semibold" style="color:var(--text-dark);">
                                <a href="listings/listing-view.php?listing_id=<?= (int)$listing['listing_id'] ?>"
                                   class="text-decoration-none" style="color:inherit;">
                                    <?= sanitize_string($listing['title']) ?>
                                </a>
                            </h6>
                            <span class="badge <?= $listing['status'] === 'sold' ? 'bg-danger' : 'bg-success' ?> mb-1">
                                <?= sanitize_string($listing['status']) ?>
                            </span>
                            <p class="fw-bold mb-0 mt-1" style="color:var(--primary);">
                                <?= formatPrice((float)$listing['price']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>


<!-- ── ACCOUNT MODAL ── -->
<dialog id="accountModal" class="dialog">
    <div class="d-flex align-items-center gap-3 mb-3">
        <img src="img/<?= isLoggedIn() ? sanitize_string($_SESSION['profile_picture_path'] ?? 'guest.png') : 'guest.png' ?>"
             class="avatar" style="width:44px; height:44px;">
        <div>
            <strong><?= isLoggedIn() ? sanitize_string($_SESSION['username']) : 'Guest' ?></strong>
            <?php if (isLoggedIn()): ?>
                <div class="text-muted small"><?= sanitize_string($_SESSION['email']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex flex-column gap-2">
        <?php if (isLoggedIn()): ?>
            <a href="profile/profile-view.php" class="btn btn-outline-secondary btn-sm">My Profile</a>
            <a href="home/auth/settings.php"   class="btn btn-outline-secondary btn-sm">Settings</a>
            <a href="home/auth/logout.php"      class="btn btn-danger btn-sm">Logout</a>
        <?php else: ?>
            <a href="home/auth/login.php"    class="btn btn-primary btn-sm">Login</a>
            <a href="home/auth/register.php" class="btn btn-outline-secondary btn-sm">Register</a>
        <?php endif; ?>
        <button type="button" class="btn btn-light btn-sm close-dialog">Close</button>
    </div>
</dialog>


<!-- ── REGISTER MODAL (quick signup) ── -->
<dialog id="registerModal" class="dialog">
    <form method="POST" class="d-flex flex-column gap-2" id ="registerMForm">
        <input type="hidden" name="register_user" value="1">

        <div class="d-flex justify-content-between align-items-center mb-1">
            <h5 class="mb-0">Create Account</h5>
            <button type="button" class="btn-close close-dialog"></button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-sm py-2 mb-1"><?= sanitize_string($error) ?></div>
        <?php endif; ?>

        <input class="form-control form-control-sm" type="email"
               name="email" placeholder="Email" id="emailRegModal" required>
            <small id="emailErrorM"></small>
        <input class="form-control form-control-sm" type="password"
               name="modal_password" placeholder="Password" id="passwordRegModal"required>
            <small id="passErrorM"></small>
        <button class="btn btn-primary btn-sm">Register</button>
        <a href="home/auth/register.php" class="text-center small text-muted text-decoration-none">
            Full registration →
        </a>
        <button type="button" class="btn btn-light btn-sm close-dialog">Cancel</button>
    </form>
</dialog>
<!-- REVIEW MODAL -->
    <dialog id="reviewModal" class="dialog">

        <form method="POST" class="d-flex flex-column gap-2"  id="reviewForm" action="/PROJECT/public_site/communication/rate.php">

            <h5>Leave a Review</h5>

            <input type="text" name="transaction_id"value="<?= (int)$eligibleTransaction ?>">
            <input type="text" name="receiver_id"value="<?= (int)$receiverId ?>">

            <label>Rating</label>
            <input type="range"
                class="rating form-range"
                name="score"
                id="reviewScore"
                min="0" max="5" step="0.5"
                style="--val:0"
                oninput="this.style.setProperty('--val', this.value)">
    <span id="scoreDisplay">0 / 5</span>
            <textarea id="reviewComment" class="form-control"
                name="comment"
                placeholder="Write your review..."></textarea>
<small class="error-message" id="reviewError"></small>
            <button class="btn btn-primary" name="submitReview">Submit</button>

            <button type="button" class="btn btn-light close-dialog">
                Cancel
            </button>

        </form>

    </dialog>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    // Close-dialog buttons
    document.querySelectorAll('.close-dialog').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('dialog').close());
    });

    // Click-outside to close any dialog
    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('click', (e) => {
            const r = dialog.getBoundingClientRect();
            if (e.clientY < r.top || e.clientY > r.bottom || e.clientX < r.left || e.clientX > r.right) {
                dialog.close();
            }
        });
    });

    // Open register modal if POST returned an error (error is set)
    <?php if ($error): ?>
    document.getElementById('registerModal')?.showModal();
    <?php endif; ?>

    // Dark mode
    if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');

    // Mouse gradient
    document.addEventListener('mousemove', (e) => {
        document.documentElement.style.setProperty('--mouse-x', (e.clientX / window.innerWidth * 100) + '%');
        document.documentElement.style.setProperty('--mouse-y', (e.clientY / window.innerHeight * 100) + '%');
    });
});
</script>
</body>
</html>