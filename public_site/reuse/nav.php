<?php
// reuse/nav.php
// Include AFTER db-conn.php and authHelper.php
// $nav_depth: how many levels deep from public_site root (default 1)
// e.g. listings/listing-view.php = 1, reuse = 1, home/auth = 2

$nav_depth = $nav_depth ?? 1;
$root      = str_repeat('../', $nav_depth);

$unreadCount      = 0;
$pendingReviews   = 0;
$notifItems       = []; // ['icon', 'text', 'href']

if (isLoggedIn()) {
    $uid = (int)$_SESSION['user_id'];

    // 1. Unread messages
    $s = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
    $s->execute([$uid]);
    $unreadCount = (int)$s->fetchColumn();

    if ($unreadCount > 0) {
        $notifItems[] = [
            'icon' => '💬',
            'text' => $unreadCount . ' unread message' . ($unreadCount === 1 ? '' : 's'),
            'href' => '/PROJECT/public_site/communication/messages.php',
        ];
    }

    // 2. Completed transactions with no review yet (either side)
    $s = $pdo->prepare('
        SELECT COUNT(DISTINCT t.transaction_id)
        FROM transactions t
        INNER JOIN listings l ON l.listing_id = t.listing_id
        LEFT JOIN ratings r ON (
            r.transaction_id = t.transaction_id
            AND r.reviewer_id = ?
        )
        WHERE (t.buyer_id = ? OR l.seller_id = ?)
          AND t.status = "completed"
          AND r.rating_id IS NULL
    ');
    $s->execute([$uid, $uid, $uid]);
    $pendingReviews = (int)$s->fetchColumn();

    if ($pendingReviews > 0) {
        $notifItems[] = [
            'icon' => '⭐',
            'text' => $pendingReviews . ' review' . ($pendingReviews === 1 ? '' : 's') . ' to complete',
            'href' => '/PROJECT/public_site/profile/my-transactions.php',
        ];
    }
}

$totalNotifs = count($notifItems);
?>

<nav class="navbar-custom d-flex justify-content-between align-items-center px-4" style="height:56px; position:sticky; top:0; z-index:100;">

    <!-- Brand -->
    <a href="/PROJECT/public_site/index.php" class="fw-bold text-decoration-none" style="color:var(--primary); font-size:18px;">
        Marketplace
    </a>

    <!-- Search -->
    <div class="d-none d-md-flex align-items-center" style="flex:1; max-width:420px; margin:0 24px;">
        <input type="search" class="form-control form-control-sm"
               placeholder="Search listings..."
               onkeydown="if(event.key==='Enter' && this.value.trim()) window.location.href='/PROJECT/public_site/listings/search.php?q='+encodeURIComponent(this.value.trim())">
    </div>

    <!-- Right actions -->
    <div class="d-flex align-items-center gap-3">

        <?php if (isLoggedIn()): ?>

            <!-- Notification bell -->
            <div class="notif-wrapper" id="navNotifWrapper" style="position:relative;">
                <button id="navNotifBtn" style="background:none; border:none; cursor:pointer; position:relative; padding:4px;">
                    <img src="<?= $root ?>img/notificationbell.png" style="width:20px; display:block;">
                    <?php if ($totalNotifs > 0): ?>
                        <span style="
                            position:absolute; top:-4px; right:-4px;
                            background:#ef4444; color:#fff;
                            border-radius:50%; width:16px; height:16px;
                            font-size:10px; font-weight:700;
                            display:flex; align-items:center; justify-content:center;
                        "><?= $totalNotifs ?></span>
                    <?php endif; ?>
                </button>

                <div id="navNotifDropdown" style="
                    display:none;
                    position:absolute; right:0; top:calc(100% + 8px);
                    width:280px;
                    background:var(--bg-card);
                    border:1px solid var(--border);
                    border-radius:10px;
                    box-shadow:0 4px 20px rgba(0,0,0,0.1);
                    z-index:300;
                    overflow:hidden;
                ">
                    <div style="padding:12px 16px; font-weight:600; font-size:14px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between;">
                        <span>Notifications</span>
                        <?php if ($totalNotifs > 0): ?>
                            <span style="color:var(--text-light); font-size:12px; font-weight:400;"><?= $totalNotifs ?> new</span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($notifItems)): ?>
                        <div style="padding:20px; text-align:center; color:var(--text-light); font-size:13px;">
                            All caught up! 🎉
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifItems as $notif): ?>
                            <button id="navReviewModal" style="
                                display:flex; align-items:center; gap:12px;
                                padding:12px 16px;
                                text-decoration:none; color:var(--text-dark);
                                border-bottom:1px solid var(--border);
                                font-size:13px;
                                transition:background 0.15s;
                            " onmouseover="this.style.background='var(--bg-main)'"
                               onmouseout="this.style.background=''">
                                <span style="font-size:20px; flex-shrink:0;"><?= $notif['icon'] ?></span>
                                <span><?= sanitize_string($notif['text']) ?></span>
                        </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile avatar -->
            <a href="/PROJECT/public_site/profile/profile-view.php">
                <img src="<?= $root ?>img/<?= sanitize_string($_SESSION['profile_picture_path'] ?? 'guest.png') ?>"
                     class="avatar" style="width:32px; height:32px;">
            </a>

            <!-- Sell button -->
            <a href="/PROJECT/public_site/listings/listing-create-edit.php"
               class="btn btn-primary btn-sm">Sell</a>

<a href="/PROJECT/public_site/communication/messages.php" class="btn btn-primary btn-sm">Message</button>
            <!-- Logout -->
            <a href="/PROJECT/public_site/home/auth/logout.php"
               class="text-muted text-decoration-none small">Logout</a>

        <?php else: ?>
            <a href="/PROJECT/public_site/home/auth/login.php" class="btn btn-outline-secondary btn-sm">Login</a>
            <button class="btn btn-primary btn-sm" id ="registerGuest">Register</button>
        <?php endif; ?>

    </div>
</nav>

<script>
(function () {
    const btn     = document.getElementById('navNotifBtn');
    const dropdown = document.getElementById('navNotifDropdown');
    const wrapper  = document.getElementById('navNotifWrapper');

    if (!btn || !dropdown) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    });

    document.addEventListener('click', (e) => {
        if (wrapper && !wrapper.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
})();
</script>