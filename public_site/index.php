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
                'is_banned' => 0,
            ]);
            redirect('/PROJECT/public_site/index.php');
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM listings WHERE NOT(status="sold")');
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
  AND t.transaction_id IS NOT NULL 
  AND t.transaction_id > 0
  AND r.rating_id IS NULL
LIMIT 1');

    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
 
    $eligibleTransaction = $stmt->fetchAll();
    if ($eligibleTransaction!=null){
    $receiverId = $eligibleTransaction[0]['receiver_id'];
    }else{
        $receiverId=null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id=?');
    $stmt->execute([$_SESSION['user_id']]);
    $profileUser = $stmt->fetchAll();
}

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - My Tech Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar">

        <h2>Marketplace</h2>

        <div class="flex flex-col gap-sm">
            <button class="btn-custom" id="sideBarBrowseBtn">Browse</button>
            <button class="btn-custom" id="sideBarSellBtn">Sell</button>
            <button class="btn-custom" id="sideBarMessagesBtn">Messages</button>
        </div>

    </div>


    <!-- MAIN -->
    <div class="main-content">

        <!-- NAV -->
        <nav class="headerNav d-flex justify-content-between align-items-center">

            <div class="d-flex gap-3">
                <a href="index.php">Home</a>
                <a href="profile/profile-view.php">Account</a>
                <a href="home/auth/settings.php">Settings</a>
            </div>

            <div class="d-flex align-items-center gap-3">

                <div class="searchContainer d-flex align-items-center gap-2">
                    <img src="img/search.png" width="18">
                    <input type="search" class="form-control form-control-sm" style="width:180px;">
                </div>

                <img src="img/notificationbell.png" width="20" id="notificationOpen">
                <img src="img/settings.png" width="20" id="viewAccount">

            </div>

        </nav>


        <!-- PRODUCTS -->
        <div class="container py-4">

            <div class="row g-4">

                <?php foreach ($listings as $listing): ?>
                    <div class="col-md-4 col-lg-3">

                        <div class="card h-100 shadow-sm">

                            <img class="card-img-top"
                                src="img/<?= sanitize_string($listing['media_path'] ?? 'placeholder.png') ?>"
                                style="height:180px; object-fit:cover;">

                            <div class="card-body">

                                <h6 class="card-title mb-1">
                                    <a href="listings/listing-view.php?listing_id=<?= (int)$listing['listing_id'] ?>">
                                        <?= sanitize_string($listing['title']) ?>
                                    </a>
                                </h6>

                                <span class="badge <?= $listing['status'] === 'sold' ? 'bg-danger' : 'bg-success' ?>">
                                    <?= sanitize_string($listing['status']) ?>
                                </span>

                                <p class="fw-bold mt-2 mb-0 text-primary">
                                    $<?= sanitize_string($listing['price']) ?>
                                </p>

                            </div>

                        </div>

                    </div>
                <?php endforeach; ?>

            </div>

        </div>

    </div>


    <!-- ACCOUNT MODAL -->

    <dialog id="accountModal" class="dialog">

        <div class="d-flex align-items-center gap-2 mb-3">

            <img class="rounded-circle" width="40"
                src="img/<?php if (isLoggedIn()): ?><?= sanitize_string($_SESSION['profile_picture_path'] ?? `guest.png`) ?> <?php else:?><?= sanitize_string(`guest.png`) ?> <?php endif; ?>" alt="Guest Profile">

            <strong>
                <?= isLoggedIn() ? sanitize_string($_SESSION['username']) : 'Guest' ?>
            </strong>

        </div>

        <div class="d-flex flex-column gap-2">

            <a href="home/auth/settings.php" class="btn btn-outline-secondary">
                Account Settings
            </a>

            <button class="btn btn-primary" id="logOutBtn">
                <?= isLoggedIn() ? 'Logout' : 'Login' ?>
            </button>

            <button class="btn btn-outline-primary" id="openRegister">
                Register
            </button>

            <button class="btn btn-light mt-2 close-dialog">
                Close
            </button>

        </div>

    </dialog>


    <!-- SUCCESS MODAL -->
    <dialog id="successModal" class="dialog">

        <form method="POST" class="flex flex-col gap-sm">

            <button type="button" class="close-btn">&times;</button>

            <h2>Success!</h2>

            <?php if ($modal_error): ?>
                <div class="error-banner"><?= sanitize_string($modal_error) ?></div>
            <?php endif; ?>

            <button class="btn-primary-custom">Return To Home</button>

        </form>

    </dialog>


    <!-- REGISTER MODAL -->
    <dialog id="registerModal" class="dialog">

        <form method="POST" class="d-flex flex-column gap-2">

            <h5>Create Account</h5>

            <input class="form-control"
                type="email"
                name="email"
                placeholder="Email"
                required>

            <input class="form-control"
                type="password"
                name="modal_password"
                placeholder="Password"
                required>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= sanitize_string($error) ?></div>
            <?php endif; ?>

            <button class="btn btn-primary">Register</button>

            <button type="button" class="btn btn-light close-dialog">
                Cancel
            </button>

        </form>

    </dialog>


    <!-- REVIEW MODAL -->
    <dialog id="reviewModal" class="dialog">

        <form method="POST" class="d-flex flex-column gap-2"  id="reviewForm" action="/PROJECT/public_site/communication/rate.php">

            <h5>Leave a Review</h5>

            <input type="hidden" name="transaction_id"value="<?= (int)$eligibleTransaction ?>">
            <input type="hidden" name="receiver_id"value="<?= (int)$receiverId ?>">

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

  
    <script src="js/script.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {

            const accountModal = document.getElementById("accountModal");
            const registerModal = document.getElementById("registerModal");

            const openAccountBtn = document.getElementById("viewAccount");
            const openRegisterBtn = document.getElementById("registerUser");
            const openRegisterFromAccount = document.getElementById("openRegister");

            // OPEN ACCOUNT
            if (openAccountBtn) {
                openAccountBtn.addEventListener("click", () => {
                    accountModal.showModal();
                });
            }

            // OPEN REGISTER
            if (openRegisterBtn) {
                openRegisterBtn.addEventListener("click", () => {
                    registerModal.showModal();
                });
            }

            // OPEN REGISTER FROM ACCOUNT
            if (openRegisterFromAccount) {
                openRegisterFromAccount.addEventListener("click", () => {
                    accountModal.close();
                    registerModal.showModal();
                });
            }

            // CLOSE BUTTONS
            document.querySelectorAll(".close-dialog").forEach(btn => {
                btn.addEventListener("click", () => {
                    btn.closest("dialog").close();
                });
            });

            // CLICK OUTSIDE TO CLOSE
            document.querySelectorAll("dialog").forEach(dialog => {
                dialog.addEventListener("click", (e) => {
                    const rect = dialog.getBoundingClientRect();
                    const inside =
                        rect.top <= e.clientY &&
                        e.clientY <= rect.bottom &&
                        rect.left <= e.clientX &&
                        e.clientX <= rect.right;

                    if (!inside) {
                        dialog.close();
                    }
                });
            });

        });
    </script>
</body>

</html>