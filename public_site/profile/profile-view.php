<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

$profileId    = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];
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
$totalCount = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(listing_id) FROM listings WHERE seller_id = ? AND status != "sold"');
$stmt->execute([$profileId]);
$activeCount = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM listings WHERE seller_id = ?');
$stmt->execute([$profileId]);
$listings = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(rating_id) FROM ratings WHERE receiver_id = ?');
$stmt->execute([$profileId]);
$countR = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM ratings WHERE receiver_id = ?');
$stmt->execute([$profileId]);
$ratings = $stmt->fetchAll();

// Handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveSettings'])) {
    $username = trim(post('settingsUsername'));
    $fullName = trim(post('settingsFullName'));
    $address  = trim(post('settingsAddress'));
    $password = $_POST['settingsPassword'] ?? '';

    $updates = [];
    $params  = [];

    if ($username !== '' && $username !== $_SESSION['username']) {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? AND user_id != ?');
        $stmt->execute([$username, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            $updates[] = 'username = ?';
            $params[]  = $username;
        }
    }

    if ($fullName !== '') { $updates[] = 'full_name = ?'; $params[] = $fullName; }
    if ($address  !== '') { $updates[] = 'address = ?';   $params[] = $address;  }

    if ($password !== '') {
        $updates[] = 'password_hash = ?';
        $params[]  = password_hash($password, PASSWORD_BCRYPT);
    }

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
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE user_id = ?');
        $stmt->execute($params);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        loginUser($user);
    }

    redirect('/PROJECT/public_site/profile/profile-view.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize_string($profileUser['username']) ?> — Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- ── NAV ── -->
<nav class="navbar-custom d-flex justify-content-between align-items-center px-4" style="height:56px; position:sticky; top:0; z-index:100;">
    <a href="/PROJECT/public_site/index.php" class="fw-bold text-decoration-none" style="color:var(--primary);">Marketplace</a>
    <div class="d-flex align-items-center gap-3">
        <a href="/PROJECT/public_site/communication/messages.php" class="text-decoration-none" style="color:var(--text-dark);">Messages</a>
        <a href="/PROJECT/public_site/home/auth/logout.php" class="text-decoration-none text-muted">Logout</a>
        <img src="../img/<?= sanitize_string($profileUser['profile_picture_path'] ?? 'guest.png') ?>"
             class="avatar" style="width:32px; height:32px;">
    </div>
</nav>

<div class="container py-4">

    <!-- ── PROFILE HEADER ── -->
    <div class="card-custom mb-4">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <img src="../img/<?= sanitize_string($profileUser['profile_picture_path'] ?? 'guest.png') ?>"
                 class="avatar" style="width:88px; height:88px;">

            <div class="flex-grow-1">
                <h2 class="mb-0 fw-bold"><?= sanitize_string($profileUser['full_name'] ?: $profileUser['username']) ?></h2>
                <span class="text-muted small">@<?= sanitize_string($profileUser['username']) ?></span>

                <div class="d-flex align-items-center gap-2 mt-1">
                    <input type="range" min="0" max="5" step="0.5"
                           value="<?= (float)($score ?? 0) ?>"
                           class="rating" style="--val:<?= (float)($score ?? 0) ?>;"
                           disabled>
                    <span class="text-muted small"><?= $score ?? '0' ?> / 5 &nbsp;·&nbsp; <?= $countR ?> review<?= $countR == 1 ? '' : 's' ?></span>
                </div>

                <div class="text-muted small mt-1">
                    <?= sanitize_string($profileUser['address'] ?? 'No address provided') ?>
                    &nbsp;·&nbsp;
                    Joined <?= date('jS F Y', strtotime($profileUser['created_at'] ?? 'now')) ?>
                    &nbsp;·&nbsp;
                    <?= $totalCount ?> total ads
                </div>
            </div>

            <?php if ($isOwnProfile): ?>
                <button class="btn btn-outline-secondary btn-sm" id="profileEditBtn">Edit Profile</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── ACTIVE ADS ── -->
    <h4 class="mb-3">Active Ads (<?= $activeCount ?>)</h4>
    <div class="row g-3 mb-5">
        <?php foreach ($listings as $listing):
            if (!$isOwnProfile && $listing['status'] === 'sold') continue;
            $firstImg = explode('#', trim($listing['media_path'] ?? 'placeholder.png', '#'))[0];
        ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="card h-100 shadow-sm" style="border-color:var(--border);">
                    <img src="../img/<?= sanitize_string($firstImg) ?>"
                         class="card-img-top"
                         style="height:160px; object-fit:cover;">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-1">
                            <a href="../listings/listing-view.php?listing_id=<?= (int)$listing['listing_id'] ?>"
                               class="text-decoration-none" style="color:var(--text-dark);">
                                <?= sanitize_string($listing['title']) ?>
                            </a>
                        </h6>
                        <span class="badge <?= $listing['status'] === 'sold' ? 'bg-danger' : 'bg-success' ?>">
                            <?= sanitize_string($listing['status']) ?>
                        </span>
                        <p class="fw-bold mt-2 mb-0" style="color:var(--primary);">
                            <?= formatPrice((float)$listing['price']) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── REVIEWS ── -->
    <h4 class="mb-3">Reviews</h4>

    <!-- Overview bar -->
    <div class="card-custom d-flex align-items-center gap-3 mb-4" style="max-width:360px;">
        <span class="display-6 fw-bold"><?= $score ?? '—' ?></span>
        <div>
            <input type="range" min="0" max="5" step="0.5"
                   value="<?= (float)($score ?? 0) ?>"
                   class="rating" style="--val:<?= (float)($score ?? 0) ?>;"
                   disabled>
            <div class="text-muted small"><?= $countR ?> review<?= $countR == 1 ? '' : 's' ?></div>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($ratings as $rating):
            $stmt = $pdo->prepare('SELECT username, profile_picture_path FROM users WHERE user_id = ?');
            $stmt->execute([$rating['reviewer_id']]);
            $reviewer = $stmt->fetch();
        ?>
            <div class="col-md-6">
                <div class="card-custom h-100">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img src="../img/<?= sanitize_string($reviewer['profile_picture_path'] ?? 'guest.png') ?>"
                             class="avatar" style="width:40px; height:40px;">
                        <div>
                            <a href="profile-view.php?user_id=<?= (int)$rating['reviewer_id'] ?>"
                               class="fw-semibold text-decoration-none" style="color:var(--text-dark);">
                                <?= sanitize_string($reviewer['username'] ?? 'Unknown') ?>
                            </a>
                            <div>
                                <input type="range" min="0" max="5" step="0.5"
                                       value="<?= (float)$rating['score'] ?>"
                                       class="rating" style="--val:<?= (float)$rating['score'] ?>;"
                                       disabled>
                            </div>
                        </div>
                    </div>
                    <?php if ($rating['comment']): ?>
                        <p class="mb-0 small"><?= sanitize_string($rating['comment']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>


<!-- ── SETTINGS MODAL ── -->
<?php if ($isOwnProfile): ?>
<dialog id="settingsModal" class="dialog">
    <form method="POST" action="" id="settingsForm" enctype="multipart/form-data">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Edit Profile</h5>
            <button type="button" class="btn-close" id="closeSettings"></button>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Username</label>
            <input type="text" name="settingsUsername" class="form-control form-control-sm"
                   value="<?= sanitize_string($_SESSION['username']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Full Name</label>
            <input type="text" name="settingsFullName" class="form-control form-control-sm"
                   value="<?= sanitize_string($profileUser['full_name'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Address</label>
            <input type="text" name="settingsAddress" class="form-control form-control-sm"
                   value="<?= sanitize_string($profileUser['address'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">
                New Password <span class="text-muted fw-normal">(leave blank to keep current)</span>
            </label>
            <div class="input-group input-group-sm">
                <input type="password" name="settingsPassword" id="settingsPassword"
                       class="form-control" placeholder="New password">
                <button type="button" class="btn btn-outline-secondary" id="togglePassword">👁</button>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold">Profile Picture</label>
            <input type="file" name="settingsProfilePic" class="form-control form-control-sm"
                   accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="saveSettings" class="btn btn-primary btn-sm flex-grow-1">Save Changes</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="closeSettingsCancel">Cancel</button>
        </div>

    </form>
</dialog>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/script.js"></script>
<script>
    // Settings modal open/close
    const profileEditBtn     = document.getElementById('profileEditBtn');
    const settingsModal      = document.getElementById('settingsModal');
    const closeSettings      = document.getElementById('closeSettings');
    const closeSettingsCancel = document.getElementById('closeSettingsCancel');

    if (profileEditBtn && settingsModal) {
        profileEditBtn.addEventListener('click', () => settingsModal.showModal());
    }
    if (closeSettings) {
        closeSettings.addEventListener('click', () => settingsModal.close());
    }
    if (closeSettingsCancel) {
        closeSettingsCancel.addEventListener('click', () => settingsModal.close());
    }

    // Password toggle
    const togglePassword   = document.getElementById('togglePassword');
    const settingsPassword = document.getElementById('settingsPassword');
    if (togglePassword) {
        togglePassword.addEventListener('click', function () {
            const isPass = settingsPassword.type === 'password';
            settingsPassword.type = isPass ? 'text' : 'password';
            this.textContent = isPass ? '🙈' : '👁';
        });
    }

    // Dark mode
    if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');
</script>
</body>
</html>