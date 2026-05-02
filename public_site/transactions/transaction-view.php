<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

$me     = currentUser();
$userId = (int)$me['user_id'];
$tid    = isset($_GET['transaction_id']) ? (int)$_GET['transaction_id'] : 0;

if (!$tid) redirect('/PROJECT/public_site/transactions/my-transactions.php');

// Fetch full transaction + listing + escrow
$stmt = $pdo->prepare('
    SELECT t.*, l.title, l.media_path, l.seller_id, l.description AS listing_desc,
           l.price AS listing_price,
           buyer.username AS buyer_username, buyer.profile_picture_path AS buyer_pic,
           seller.username AS seller_username, seller.profile_picture_path AS seller_pic,
           e.escrow_id, e.transaction_status AS escrow_status,
           e.transaction_fee, e.bank_details
    FROM transactions t
    JOIN listings l       ON l.listing_id   = t.listing_id
    JOIN users buyer      ON buyer.user_id  = t.buyer_id
    JOIN users seller     ON seller.user_id = l.seller_id
    LEFT JOIN escrow e    ON e.transaction_id = t.transaction_id
    WHERE t.transaction_id = ?
    LIMIT 1
');
$stmt->execute([$tid]);
$tx = $stmt->fetch();

if (!$tx) redirect('/PROJECT/public_site/transactions/my-transactions.php');

// Access control — only buyer or seller can view
$isSeller = ($userId === (int)$tx['seller_id']);
$isBuyer  = ($userId === (int)$tx['buyer_id']);
if (!$isSeller && !$isBuyer) redirect('/PROJECT/public_site/index.php');

$escrow = $tx['escrow_status'] ?? 'held';
$txStatus = $tx['status'];

// ── HANDLE ACTIONS ────────────────────────────────────────────────────────────

// Seller releases funds
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_funds']) && $isSeller) {
    if ($txStatus === 'pending' && $escrow === 'held') {
        $pdo->prepare('UPDATE escrow SET transaction_status = "released" WHERE transaction_id = ?')->execute([$tid]);
        redirect('/PROJECT/public_site/transactions/transaction-view.php?transaction_id=' . $tid);
    }
}

// Buyer confirms delivery
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delivery']) && $isBuyer) {
    if ($txStatus === 'pending' && $escrow === 'released') {
        $pdo->prepare('UPDATE transactions SET status = "completed" WHERE transaction_id = ?')->execute([$tid]);
        redirect('/PROJECT/public_site/transactions/transaction-view.php?transaction_id=' . $tid);
    }
}

// Re-fetch after possible update
$stmt->execute([$tid]);
$tx     = $stmt->fetch();
$escrow = $tx['escrow_status'] ?? 'held';
$txStatus = $tx['status'];

// ── STATUS META ───────────────────────────────────────────────────────────────
function pageMeta(string $txStatus, string $escrow): array {
    if ($txStatus === 'disputed' || $escrow === 'disputed') {
        return ['label' => 'Disputed', 'class' => 'hdr-disputed', 'icon' => '⚠️'];
    }
    if ($txStatus === 'completed') {
        return ['label' => 'Completed', 'class' => 'hdr-complete', 'icon' => '✅'];
    }
    if ($escrow === 'released') {
        return ['label' => 'Awaiting Delivery Confirmation', 'class' => 'hdr-progress', 'icon' => '📦'];
    }
    if ($escrow === 'refunded') {
        return ['label' => 'Refunded', 'class' => 'hdr-refunded', 'icon' => '↩️'];
    }
    return ['label' => 'In Progress', 'class' => 'hdr-progress', 'icon' => '🔄'];
}

$meta = pageMeta($txStatus, $escrow);
$img  = !empty($tx['media_path']) ? explode('#', trim($tx['media_path'],'#'))[0] : 'placeholder.png';

// ── CHECKPOINT LOGIC ──────────────────────────────────────────────────────────
// Escrow path:  held → released → (transaction: completed) → payout
// Dispute path: disputed → refunded
// Each checkpoint: ['label', 'sublabel', 'done']
$isDisputed = ($txStatus === 'disputed' || $escrow === 'disputed');
$isRefunded = ($escrow === 'refunded');
$isComplete = ($txStatus === 'completed');
$isReleased = ($escrow === 'released' || $isComplete);
$isHeld     = true; // always true once transaction exists

if ($isDisputed) {
    $checkpoints = [
        ['label' => 'Payment Received',        'sub' => 'Buyer paid ' . formatPrice((float)$tx['price']),   'done' => true],
        ['label' => 'Funds Held in Escrow',    'sub' => 'Escrow ref: ' . sanitize_string($tx['bank_details'] ?? '—'), 'done' => true],
        ['label' => 'Dispute Raised',          'sub' => 'This deal is under review',                         'done' => true,  'bad' => true],
        ['label' => 'Resolution & Refund',     'sub' => $isRefunded ? 'Refund has been issued' : 'Pending admin resolution', 'done' => $isRefunded, 'bad' => !$isRefunded],
    ];
} else {
    $checkpoints = [
        ['label' => 'Payment Received',        'sub' => 'Buyer paid ' . formatPrice((float)$tx['price']),           'done' => true],
        ['label' => 'Funds Held in Escrow',    'sub' => 'Escrow ref: ' . sanitize_string($tx['bank_details'] ?? '—'),    'done' => $isHeld],
        ['label' => 'Seller Released Funds',   'sub' => $isReleased ? 'Seller confirmed shipment/completion' : 'Waiting for seller to release', 'done' => $isReleased],
        ['label' => 'Buyer Confirmed Delivery','sub' => $isComplete  ? 'Buyer confirmed receipt' : 'Waiting for buyer to confirm',              'done' => $isComplete],
        ['label' => 'Payout to Seller',        'sub' => $isComplete  ? 'Funds released to seller (simulated)' : 'Pending delivery confirmation', 'done' => $isComplete],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal #<?= $tid ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: var(--bg-main); }
        .page-wrap { max-width: 860px; margin: 0 auto; padding: 80px 20px 60px; }

        /* ═══ STATUS HEADER ═══ */
        .deal-header {
            border-radius: 14px; padding: 22px 28px; margin-bottom: 28px;
            display: flex; align-items: center; gap: 14px;
        }
        .hdr-progress { background: #fef3c7; border: 1px solid #fde68a; }
        .hdr-complete { background: #d1fae5; border: 1px solid #6ee7b7; }
        .hdr-disputed { background: #fee2e2; border: 1px solid #fca5a5; }
        .hdr-refunded { background: #ede9fe; border: 1px solid #c4b5fd; }

        .deal-header .hdr-icon { font-size: 2rem; }
        .deal-header .hdr-text h2 { margin: 0; font-size: 1.25rem; font-weight: 700; }
        .deal-header .hdr-text p  { margin: 4px 0 0; font-size: 0.85rem; color: var(--text-light); }

        /* ═══ LAYOUT ═══ */
        .deal-body { display: grid; grid-template-columns: 1fr 300px; gap: 24px; }
        @media (max-width: 700px) { .deal-body { grid-template-columns: 1fr; } }

        /* ═══ CHECKPOINT TIMELINE ═══ */
        .timeline-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 14px; padding: 24px;
        }
        .timeline-card h3 { font-size: 1rem; font-weight: 700; margin: 0 0 20px; color: var(--text-dark); }

        .timeline { display: flex; flex-direction: column; gap: 0; }

        .checkpoint {
            display: flex; gap: 16px; position: relative;
        }
        .checkpoint:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 14px; top: 30px;
            width: 2px; height: calc(100% - 10px);
            background: var(--border);
        }
        .checkpoint.done:not(:last-child)::after { background: #10b981; }
        .checkpoint.bad::after { background: #ef4444 !important; }

        .cp-dot {
            width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
            border: 2px solid var(--border); background: var(--bg-main);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; z-index: 1; position: relative;
            margin-top: 2px;
        }
        .checkpoint.done .cp-dot  { background: #10b981; border-color: #10b981; color: #fff; }
        .checkpoint.bad  .cp-dot  { background: #ef4444; border-color: #ef4444; color: #fff; }

        .cp-content { padding-bottom: 24px; }
        .cp-label { font-weight: 600; font-size: 0.9rem; color: var(--text-dark); }
        .cp-sub   { font-size: 0.8rem; color: var(--text-light); margin-top: 2px; }
        .checkpoint:not(.done) .cp-label { color: var(--text-light); }

        /* ═══ SIDEBAR CARD ═══ */
        .side-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 14px; padding: 20px;
        }
        .side-card + .side-card { margin-top: 16px; }
        .side-card h3 { font-size: 0.9rem; font-weight: 700; margin: 0 0 14px; color: var(--text-dark); }

        .listing-thumb {
            width: 100%; height: 140px; object-fit: cover;
            border-radius: 8px; margin-bottom: 12px;
        }
        .listing-title { font-weight: 600; font-size: 0.95rem; color: var(--text-dark); }
        .listing-price { font-size: 1.15rem; font-weight: 700; color: var(--primary); margin: 4px 0 0; }

        .detail-row { display: flex; justify-content: space-between; font-size: 0.83rem; padding: 6px 0; border-bottom: 1px solid var(--border); }
        .detail-row:last-child { border-bottom: none; }
        .detail-row span:first-child { color: var(--text-light); }
        .detail-row span:last-child  { font-weight: 600; color: var(--text-dark); }

        /* ═══ ACTION BUTTONS ═══ */
        .action-btn {
            display: block; width: 100%; padding: 12px;
            border-radius: 10px; font-weight: 600; font-size: 0.9rem;
            text-align: center; border: none; cursor: pointer; margin-top: 12px;
            transition: opacity .15s;
        }
        .action-btn:hover { opacity: .85; }
        .btn-release  { background: #3b82f6; color: #fff; }
        .btn-confirm  { background: #10b981; color: #fff; }
        .btn-dispute  { background: #fee2e2; color: #991b1b; text-decoration: none; }

        /* Parties */
        .party-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; }
        .party-row img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .party-info small { display: block; font-size: 0.75rem; color: var(--text-light); }
        .party-info strong { font-size: 0.88rem; }

        /* dark mode */
        body.dark-mode .hdr-progress { background: #2d2200; border-color: #78400a; }
        body.dark-mode .hdr-complete { background: #022c22; border-color: #065f46; }
        body.dark-mode .hdr-disputed { background: #2d0a0a; border-color: #7f1d1d; }
        body.dark-mode .hdr-refunded { background: #1e1735; border-color: #4c1d95; }
    </style>
</head>
<body>

<!-- Nav -->
<nav style="position:fixed;top:0;left:0;right:0;height:56px;background:var(--bg-card);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;z-index:100;gap:20px;">
    <a href="/PROJECT/public_site/index.php" style="font-weight:700;color:var(--primary);text-decoration:none;font-size:1.05rem;">Marketplace</a>
    <a href="/PROJECT/public_site/transactions/my-transactions.php" style="color:var(--text-light);text-decoration:none;font-size:.9rem;">← Back to Deals</a>
</nav>

<div class="page-wrap">

    <!-- STATUS HEADER -->
    <div class="deal-header <?= $meta['class'] ?>">
        <span class="hdr-icon"><?= $meta['icon'] ?></span>
        <div class="hdr-text">
            <h2><?= $meta['label'] ?></h2>
            <p>Deal #<?= $tid ?> &nbsp;·&nbsp; <?= formatDate($tx['date']) ?></p>
        </div>
    </div>

    <div class="deal-body">

        <!-- LEFT: Timeline + Actions -->
        <div>
            <div class="timeline-card">
                <h3>Deal Progress</h3>
                <div class="timeline">
                    <?php foreach ($checkpoints as $cp):
                        $cls = '';
                        if ($cp['done']) $cls = 'done';
                        if (!empty($cp['bad'])) $cls .= ' bad';
                    ?>
                    <div class="checkpoint <?= trim($cls) ?>">
                        <div class="cp-dot"><?= $cp['done'] ? '✓' : '' ?></div>
                        <div class="cp-content">
                            <div class="cp-label"><?= sanitize_string($cp['label']) ?></div>
                            <div class="cp-sub"><?= sanitize_string($cp['sub']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── SELLER ACTIONS ── -->
            <?php if ($isSeller && $txStatus === 'pending' && $escrow === 'held'): ?>
            <div class="timeline-card" style="margin-top:16px;">
                <h3>Your Action Required</h3>
                <p style="font-size:.85rem;color:var(--text-light);">
                    Confirm you've shipped the item or completed the service. This releases the escrow and notifies the buyer to confirm receipt.
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="transaction_id" value="<?= $tid ?>">
                    <button name="release_funds" class="action-btn btn-release" type="submit">
                        🚀 Release Funds &amp; Confirm Shipment
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── BUYER ACTIONS ── -->
            <?php if ($isBuyer && $txStatus === 'pending' && $escrow === 'released'): ?>
            <div class="timeline-card" style="margin-top:16px;">
                <h3>Your Action Required</h3>
                <p style="font-size:.85rem;color:var(--text-light);">
                    The seller has confirmed shipment. Once you've received and are happy with the item, confirm delivery to complete the transaction and release payment.
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="transaction_id" value="<?= $tid ?>">
                    <button name="confirm_delivery" class="action-btn btn-confirm" type="submit">
                        ✅ Confirm Delivery Received
                    </button>
                </form>
                <?php if ($escrow !== 'disputed' && $txStatus !== 'disputed'): ?>
                <a href="/PROJECT/public_site/transactions/dispute.php?transaction_id=<?= $tid ?>"
                   class="action-btn btn-dispute">
                   ⚠️ Raise a Dispute Instead
                </a>
                <?php endif; ?>
            </div>
            <?php elseif ($isBuyer && $txStatus === 'pending' && $escrow === 'held'): ?>
            <div class="timeline-card" style="margin-top:16px;">
                <h3>Waiting on Seller</h3>
                <p style="font-size:.85rem;color:var(--text-light);">
                    Your payment is safely held in escrow. You'll be notified once the seller confirms shipment.
                </p>
                <a href="/PROJECT/public_site/transactions/dispute.php?transaction_id=<?= $tid ?>"
                   class="action-btn btn-dispute">
                   ⚠️ Raise a Dispute
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Sidebar -->
        <div>
            <!-- Listing summary -->
            <div class="side-card">
                <h3>Listing</h3>
                <img class="listing-thumb" src="../img/<?= sanitize_string($img) ?>" alt="">
                <div class="listing-title"><?= sanitize_string($tx['title']) ?></div>
                <div class="listing-price"><?= formatPrice((float)$tx['listing_price']) ?></div>
            </div>

            <!-- Transaction details -->
            <div class="side-card">
                <h3>Order Details</h3>
                <div class="detail-row">
                    <span>Quantity</span>
                    <span><?= (int)$tx['quantity'] ?></span>
                </div>
                <div class="detail-row">
                    <span>Item Total</span>
                    <span><?= formatPrice((float)$tx['price'] - (float)$tx['transaction_fee']) ?></span>
                </div>
                <div class="detail-row">
                    <span>Escrow Fee</span>
                    <span><?= formatPrice((float)$tx['transaction_fee']) ?></span>
                </div>
                <div class="detail-row">
                    <span>Grand Total</span>
                    <span><?= formatPrice((float)$tx['price']) ?></span>
                </div>
                <div class="detail-row">
                    <span>Date</span>
                    <span><?= formatDate($tx['date']) ?></span>
                </div>
                <div class="detail-row">
                    <span>Escrow Ref</span>
                    <span style="font-size:.75rem;"><?= sanitize_string(substr($tx['bank_details'] ?? '—', 0, 28)) ?></span>
                </div>
            </div>

            <!-- Parties -->
            <div class="side-card">
                <h3>Parties</h3>
                <div class="party-row">
                    <img src="../img/<?= sanitize_string($tx['seller_pic'] ?? 'guest.png') ?>" alt="">
                    <div class="party-info">
                        <small>Seller</small>
                        <strong>
                            <a href="/PROJECT/public_site/profile/profile-view.php?user_id=<?= (int)$tx['seller_id'] ?>">
                                <?= sanitize_string($tx['seller_username']) ?>
                            </a>
                        </strong>
                    </div>
                </div>
                <div class="party-row">
                    <img src="../img/<?= sanitize_string($tx['buyer_pic'] ?? 'guest.png') ?>" alt="">
                    <div class="party-info">
                        <small>Buyer</small>
                        <strong>
                            <a href="/PROJECT/public_site/profile/profile-view.php?user_id=<?= (int)$tx['buyer_id'] ?>">
                                <?= sanitize_string($tx['buyer_username']) ?>
                            </a>
                        </strong>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /deal-body -->
</div>

<script src="../js/script.js"></script>
</body>
</html>