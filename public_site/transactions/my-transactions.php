<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

$me = currentUser();
$userId = (int)$me['user_id'];

// ── HANDLE POST ACTIONS ──────────────────────────────────────────────────────

// Seller: release funds (escrow held → released)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_funds'])) {
    $tid = (int)($_POST['transaction_id'] ?? 0);

    // Verify this user is the seller of the listing in this transaction
    $stmt = $pdo->prepare('
        SELECT t.transaction_id FROM transactions t
        JOIN listings l ON l.listing_id = t.listing_id
        WHERE t.transaction_id = ? AND l.seller_id = ? AND t.status = "pending"
        LIMIT 1
    ');
    $stmt->execute([$tid, $userId]);
    if ($stmt->fetch()) {
        $upd = $pdo->prepare('UPDATE escrow SET transaction_status = "released" WHERE transaction_id = ?');
        $upd->execute([$tid]);
    }
    redirect('/PROJECT/public_site/transactions/my-transactions.php');
}

// Buyer: confirm delivery (escrow released → transaction completed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delivery'])) {
    $tid = (int)($_POST['transaction_id'] ?? 0);

    // Verify this user is the buyer
    $stmt = $pdo->prepare('
        SELECT t.transaction_id FROM transactions t
        JOIN escrow e ON e.transaction_id = t.transaction_id
        WHERE t.transaction_id = ? AND t.buyer_id = ? AND e.transaction_status = "released"
        LIMIT 1
    ');
    $stmt->execute([$tid, $userId]);
    if ($stmt->fetch()) {
        $pdo->prepare('UPDATE transactions SET status = "completed" WHERE transaction_id = ?')->execute([$tid]);
        $pdo->prepare('UPDATE escrow SET transaction_status = "released" WHERE transaction_id = ?')->execute([$tid]);
    }
    redirect('/PROJECT/public_site/transactions/my-transactions.php');
}

// ── FETCH SELLING TRANSACTIONS ───────────────────────────────────────────────
$stmtSell = $pdo->prepare('
    SELECT t.*, l.title, l.media_path, l.seller_id,
           u.username AS buyer_username,
           e.transaction_status AS escrow_status, e.transaction_fee
    FROM transactions t
    JOIN listings l ON l.listing_id = t.listing_id
    JOIN users u ON u.user_id = t.buyer_id
    LEFT JOIN escrow e ON e.transaction_id = t.transaction_id
    WHERE l.seller_id = ?
    ORDER BY t.date DESC
');
$stmtSell->execute([$userId]);
$sellingAll = $stmtSell->fetchAll();

// ── FETCH BUYING TRANSACTIONS ────────────────────────────────────────────────
$stmtBuy = $pdo->prepare('
    SELECT t.*, l.title, l.media_path, l.seller_id,
           u.username AS seller_username,
           e.transaction_status AS escrow_status, e.transaction_fee
    FROM transactions t
    JOIN listings l ON l.listing_id = t.listing_id
    JOIN users u ON u.user_id = l.seller_id
    LEFT JOIN escrow e ON e.transaction_id = t.transaction_id
    WHERE t.buyer_id = ?
    ORDER BY t.date DESC
');
$stmtBuy->execute([$userId]);
$buyingAll = $stmtBuy->fetchAll();

// ── HELPER: map status to label/colour ───────────────────────────────────────
function statusMeta(string $txStatus, string $escrowStatus): array {
    if ($txStatus === 'disputed' || $escrowStatus === 'disputed') {
        return ['label' => 'Disputed',    'class' => 'status-disputed'];
    }
    if ($txStatus === 'completed') {
        return ['label' => 'Completed',   'class' => 'status-complete'];
    }
    if ($escrowStatus === 'released') {
        return ['label' => 'Awaiting Confirmation', 'class' => 'status-progress'];
    }
    return ['label' => 'In Progress',  'class' => 'status-progress'];
}

function filterTab(array $rows, string $tab): array {
    return array_filter($rows, function($r) use ($tab) {
        $escrow = $r['escrow_status'] ?? 'held';
        $tx     = $r['status'];
        if ($tab === 'all') return true;
        if ($tab === 'progress')  return $tx === 'pending' && $tx !== 'disputed' && $escrow !== 'disputed';
        if ($tab === 'complete')  return $tx === 'completed';
        if ($tab === 'disputed')  return $tx === 'disputed' || $escrow === 'disputed';
        return true;
    });
}

$newId = isset($_GET['new']) ? (int)$_GET['new'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offers &amp; Deals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ═══ PAGE SHELL ═══ */
        body { background: var(--bg-main); }
        .page-wrap { max-width: 900px; margin: 0 auto; padding: 80px 20px 40px; }

        /* ═══ PAGE HEADER ═══ */
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 1.6rem; font-weight: 700; color: var(--text-dark); margin: 0; }
        .page-header p  { color: var(--text-light); font-size: 0.875rem; margin: 4px 0 0; }

        /* ═══ SIDE TABS (Selling / My Purchases) ═══ */
        .side-tabs { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid var(--border); }
        .side-tab  {
            padding: 10px 20px; font-weight: 600; font-size: 0.9rem;
            border: none; background: none; cursor: pointer;
            color: var(--text-light); border-bottom: 2px solid transparent;
            margin-bottom: -2px; transition: color .2s, border-color .2s;
        }
        .side-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

        /* ═══ STATUS TABS ═══ */
        .status-tabs { display: flex; gap: 6px; margin-bottom: 20px; flex-wrap: wrap; }
        .status-tab  {
            padding: 6px 16px; border-radius: 20px; font-size: 0.82rem; font-weight: 500;
            border: 1.5px solid var(--border); background: var(--bg-card);
            color: var(--text-light); cursor: pointer; transition: all .15s;
        }
        .status-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* ═══ TRANSACTION CARD ═══ */
        .tx-card {
            display: flex; align-items: center; gap: 16px;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px; margin-bottom: 12px;
            transition: box-shadow .2s;
        }
        .tx-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.07); }

        .tx-thumb {
            width: 72px; height: 72px; border-radius: 8px;
            object-fit: cover; flex-shrink: 0;
        }

        .tx-info { flex: 1; min-width: 0; }
        .tx-title { font-weight: 600; font-size: 0.95rem; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tx-id    { font-size: 0.78rem; color: var(--text-light); }
        .tx-party { font-size: 0.83rem; color: var(--text-light); margin: 2px 0; }

        /* ═══ STATUS BADGES ═══ */
        .status-badge {
            display: inline-block; padding: 3px 10px; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600;
        }
        .status-progress { background: #fef3c7; color: #92400e; }
        .status-complete  { background: #d1fae5; color: #065f46; }
        .status-disputed  { background: #fee2e2; color: #991b1b; }

        /* ═══ ACTION BUTTON ═══ */
        .tx-action { flex-shrink: 0; }
        .btn-view {
            padding: 8px 18px; border-radius: 8px; font-size: 0.85rem;
            font-weight: 600; background: var(--primary); color: #fff;
            border: none; cursor: pointer; text-decoration: none;
            white-space: nowrap; transition: background .15s;
        }
        .btn-view:hover { background: var(--secondary); color: #fff; }

        /* ═══ EMPTY STATE ═══ */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-light); }
        .empty-state h3 { font-size: 1.1rem; margin-bottom: 6px; color: var(--text-dark); }

        /* ═══ SUCCESS BANNER ═══ */
        .success-banner {
            background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46;
            border-radius: 10px; padding: 14px 20px; margin-bottom: 20px;
            font-weight: 500;
        }

        /* Dark mode */
        body.dark-mode .status-progress { background: #3b2a00; color: #fbbf24; }
        body.dark-mode .status-complete  { background: #022c22; color: #34d399; }
        body.dark-mode .status-disputed  { background: #3b0a0a; color: #f87171; }
    </style>
</head>
<body>

<!-- Simple nav bar -->
<nav style="position:fixed;top:0;left:0;right:0;height:56px;background:var(--bg-card);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;z-index:100;gap:20px;">
    <a href="/PROJECT/public_site/index.php" style="font-weight:700;color:var(--primary);text-decoration:none;font-size:1.05rem;">Marketplace</a>
    <a href="/PROJECT/public_site/profile/profile-view.php" style="color:var(--text-light);text-decoration:none;font-size:.9rem;">Profile</a>
    <a href="/PROJECT/public_site/communication/messages.php" style="color:var(--text-light);text-decoration:none;font-size:.9rem;">Messages</a>
</nav>

<div class="page-wrap">

    <?php if ($newId): ?>
        <div class="success-banner">
            🎉 Your order #<?= $newId ?> has been placed! Payment is held in escrow until delivery is confirmed.
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Offers &amp; Deals</h1>
        <p>Manage your sales and purchases in one place.</p>
    </div>

    <!-- SIDE TABS -->
    <div class="side-tabs">
        <button class="side-tab active" data-side="selling">Selling</button>
        <button class="side-tab"        data-side="buying">My Purchases</button>
    </div>

    <!-- ══════════════════ SELLING PANEL ══════════════════ -->
    <div id="panel-selling">
        <div class="status-tabs">
            <button class="status-tab active" data-filter="all">All deals</button>
            <button class="status-tab" data-filter="progress">In Progress</button>
            <button class="status-tab" data-filter="complete">Complete</button>
            <button class="status-tab" data-filter="disputed">Disputed</button>
        </div>

        <?php
        $groups = ['all' => $sellingAll];
        foreach (['progress','complete','disputed'] as $f) {
            $groups[$f] = filterTab($sellingAll, $f);
        }
        foreach ($groups as $filter => $rows):
            $active = $filter === 'all' ? 'style=""' : 'style="display:none"';
        ?>
        <div class="tx-list" data-filter="<?= $filter ?>" <?= $filter === 'all' ? '' : 'style="display:none"' ?>>
            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <h3>Nothing here yet</h3>
                    <p>No <?= $filter === 'all' ? '' : $filter ?> transactions to show.</p>
                </div>
            <?php else: ?>
                <?php foreach ($rows as $row):
                    $escrow = $row['escrow_status'] ?? 'held';
                    $meta   = statusMeta($row['status'], $escrow);
                    $img    = !empty($row['media_path']) ? explode('#', trim($row['media_path'],'#'))[0] : 'placeholder.png';
                ?>
                <div class="tx-card">
                    <img class="tx-thumb" src="../img/<?= sanitize_string($img) ?>" alt="">
                    <div class="tx-info">
                        <div class="tx-title">
                            <?= sanitize_string($row['title']) ?>
                            <span class="tx-id">#<?= (int)$row['transaction_id'] ?></span>
                        </div>
                        <div class="tx-party">Selling to <strong><?= sanitize_string($row['buyer_username']) ?></strong></div>
                        <span class="status-badge <?= $meta['class'] ?>"><?= $meta['label'] ?></span>
                    </div>
                    <div class="tx-action">
                        <a class="btn-view" href="/PROJECT/public_site/transactions/transaction-view.php?transaction_id=<?= (int)$row['transaction_id'] ?>">View Deal</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ══════════════════ BUYING PANEL ══════════════════ -->
    <div id="panel-buying" style="display:none">
        <div class="status-tabs">
            <button class="status-tab active" data-filter="all">All deals</button>
            <button class="status-tab" data-filter="progress">In Progress</button>
            <button class="status-tab" data-filter="complete">Complete</button>
            <button class="status-tab" data-filter="disputed">Disputed</button>
        </div>

        <?php
        foreach (['all','progress','complete','disputed'] as $f):
            $rows = filterTab($buyingAll, $f);
        ?>
        <div class="tx-list" data-filter="<?= $f ?>" <?= $f === 'all' ? '' : 'style="display:none"' ?>>
            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <h3>Nothing here yet</h3>
                    <p>No <?= $f === 'all' ? '' : $f ?> purchases to show.</p>
                </div>
            <?php else: ?>
                <?php foreach ($rows as $row):
                    $escrow = $row['escrow_status'] ?? 'held';
                    $meta   = statusMeta($row['status'], $escrow);
                    $img    = !empty($row['media_path']) ? explode('#', trim($row['media_path'],'#'))[0] : 'placeholder.png';
                ?>
                <div class="tx-card">
                    <img class="tx-thumb" src="../img/<?= sanitize_string($img) ?>" alt="">
                    <div class="tx-info">
                        <div class="tx-title">
                            <?= sanitize_string($row['title']) ?>
                            <span class="tx-id">#<?= (int)$row['transaction_id'] ?></span>
                        </div>
                        <div class="tx-party">Bought from <strong><?= sanitize_string($row['seller_username']) ?></strong></div>
                        <span class="status-badge <?= $meta['class'] ?>"><?= $meta['label'] ?></span>
                    </div>
                    <div class="tx-action" style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                        <a class="btn-view" href="/PROJECT/public_site/transactions/transaction-view.php?transaction_id=<?= (int)$row['transaction_id'] ?>">View Deal</a>
                        <?php if ($row['status'] === 'pending' && $escrow !== 'disputed'): ?>
                            <a href="/PROJECT/public_site/transactions/dispute.php?transaction_id=<?= (int)$row['transaction_id'] ?>"
                               style="font-size:.78rem;color:#991b1b;text-decoration:none;font-weight:500;">Raise Dispute</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

</div><!-- /page-wrap -->

<script>
// ── Side tab switching ──────────────────────────────────────────────────────
document.querySelectorAll('.side-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.side-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const side = this.dataset.side;
        document.getElementById('panel-selling').style.display = side === 'selling' ? '' : 'none';
        document.getElementById('panel-buying').style.display  = side === 'buying'  ? '' : 'none';
    });
});

// ── Status tab filtering (per panel) ───────────────────────────────────────
document.querySelectorAll('.status-tabs').forEach(tabGroup => {
    tabGroup.querySelectorAll('.status-tab').forEach(btn => {
        btn.addEventListener('click', function() {
            tabGroup.querySelectorAll('.status-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            // Find the parent panel and toggle lists inside it
            const panel = tabGroup.closest('#panel-selling, #panel-buying');
            panel.querySelectorAll('.tx-list').forEach(list => {
                list.style.display = list.dataset.filter === filter ? '' : 'none';
            });
        });
    });
});
</script>
<script src="../js/script.js"></script>
</body>
</html>