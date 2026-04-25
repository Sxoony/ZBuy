<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitReview'])) {

    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    $receiverId    = (int)($_POST['receiver_id']    ?? 0);
    $score         = (float)($_POST['score']        ?? 0);
    $comment       = trim(post('comment'));

    // Re-verify server side — never trust the modal alone
    $stmt = $pdo->prepare('
    SELECT t.transaction_id FROM transactions t,listings l
        WHERE t.transaction_id = ? AND t.status = "completed"
        AND (t.buyer_id = ? OR l.seller_id = ?) AND t.listing_id = l.listing_id
        LIMIT 1');
    $stmt->execute([$transactionId, $_SESSION['user_id'], $_SESSION['user_id']]);

    if (!$stmt->fetch()) {
        redirect('/PROJECT/public_site/index.php');
    }

    $score = max(0, min(5, $score)); // clamp between 0 and 5

    $stmt = $pdo->prepare('
        INSERT INTO ratings (transaction_id, reviewer_id, receiver_id, score, comment)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$transactionId, $_SESSION['user_id'], $receiverId, $score, $comment]);

    redirect('/PROJECT/public_site/profile/profile-view.php?user_id=' . $receiverId);
    $stmt = $pdo->prepare('
    SELECT transaction_id FROM transactions
    WHERE status = "completed"
    AND (
        (buyer_id = ? AND seller_id = ?)
        OR
        (buyer_id = ? AND seller_id = ?)
    )
    AND transaction_id NOT IN (
        SELECT transaction_id FROM ratings WHERE reviewer_id = ?
    )
    LIMIT 1
');
$stmt->execute([
    $me['user_id'], $receiverId,
    $receiverId,    $me['user_id'],
    $me['user_id']
]);
$eligibleTransaction = $stmt->fetch();

if (!$eligibleTransaction) {
    // No completed transaction or already rated — block it
    redirect('/PROJECT/public_site/index.php');
}
}