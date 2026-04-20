<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

$me = currentUser();

// Fetch all conversations for the current user
// A conversation = the other person + their latest message
$stmt = $pdo->prepare("
    SELECT 
        u.user_id,
        u.username,
        u.profile_picture_path,
        latest.description_enc AS last_message,
        latest.date            AS last_date,
        SUM(CASE WHEN m.is_read = 0 AND m.receiver_id = ? THEN 1 ELSE 0 END) AS unread_count
    FROM messages m
    JOIN users u ON u.user_id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
    JOIN (
        SELECT 
            LEAST(sender_id, receiver_id)    AS user_a,
            GREATEST(sender_id, receiver_id) AS user_b,
            description_enc,
            date
        FROM messages
        WHERE sender_id = ? OR receiver_id = ?
        ORDER BY date DESC
        LIMIT 1
    ) latest ON (
        LEAST(m.sender_id, m.receiver_id)    = latest.user_a AND
        GREATEST(m.sender_id, m.receiver_id) = latest.user_b
    )
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY u.user_id, u.username, u.profile_picture_path, latest.description_enc, latest.date
    ORDER BY latest.date DESC
");
$stmt->execute([
    $me['user_id'], $me['user_id'],
    $me['user_id'], $me['user_id'],
    $me['user_id'], $me['user_id']
]);
$conversations = $stmt->fetchAll();

// Active conversation — load messages if receiver_id is in GET
$activePerson = null;
$chatMessages = [];

if (isset($_GET['with'])) {
    $withId = (int)$_GET['with'];

    $stmt = $pdo->prepare('SELECT user_id, username, profile_picture_path FROM users WHERE user_id = ?');
    $stmt->execute([$withId]);
    $activePerson = $stmt->fetch();

    if ($activePerson) {
        // Mark messages as read
        $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
        $stmt->execute([$withId, $me['user_id']]);

        // Fetch conversation
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.profile_picture_path
            FROM messages m
            JOIN users u ON u.user_id = m.sender_id
            WHERE (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.date ASC
        ");
        $stmt->execute([$me['user_id'], $withId, $withId, $me['user_id']]);
        $chatMessages = $stmt->fetchAll();
    }
}

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $body       = trim($_POST['message_body'] ?? '');

    if ($receiverId && $body !== '') {
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, description_enc) VALUES (?, ?, ?)');
        $stmt->execute([$me['user_id'], $receiverId, $body]);
    }

    redirect('/PROJECT/public_site/communication/messages.php?with=' . $receiverId);
}

// Notifications for the bell
$stmt = $pdo->prepare("
    SELECT m.message_id, u.username, u.profile_picture_path, m.description_enc, m.date
    FROM messages m
    JOIN users u ON u.user_id = m.sender_id
    WHERE m.receiver_id = ? AND m.is_read = 0
    ORDER BY m.date DESC
    LIMIT 10
");
$stmt->execute([$me['user_id']]);
$notifications     = $stmt->fetchAll();
$notificationCount = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- ─── TOP NAV ─── -->
<nav class="top-nav">
    <a href="/PROJECT/public_site/index.php" class="nav-brand">Marketplace</a>

    <div class="nav-actions">
        <!-- Bell icon + dropdown -->
        <div class="notif-wrapper" id="notifWrapper">
            <button class="notif-btn" id="notifBtn">
                <img src="../img/notificationbell.png" alt="Notifications" class="nav-icon">
                <?php if ($notificationCount > 0): ?>
                    <span class="notif-badge"><?= $notificationCount ?></span>
                <?php endif; ?>
            </button>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span>Notifications</span>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notif-count"><?= $notificationCount ?> new</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="notif-empty">No new notifications</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <a href="/PROJECT/public_site/communication/messages.php?with=<?= (int)$notif['user_id'] ?? '' ?>"
                           class="notif-item">
                            <img src="../img/<?= sanitize_string($notif['profile_picture_path'] ?? 'guest.png') ?>"
                                 alt="" class="notif-avatar">
                            <div class="notif-body">
                                <span class="notif-name"><?= sanitize_string($notif['username']) ?></span>
                                <span class="notif-preview"><?= sanitize_string($notif['description_enc']) ?></span>
                                <span class="notif-time"><?= timeAgo($notif['date']) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <a href="/PROJECT/public_site/profile/profile-view.php" class="nav-profile">
            <img src="../img/<?= sanitize_string($me['profile_picture_path'] ?? 'guest.png') ?>"
                 alt="Profile" class="nav-avatar">
        </a>
    </div>
</nav>

<!-- ─── MESSAGES LAYOUT ─── -->
<div class="messages-page">

    <!-- LEFT: conversation list -->
    <div class="conversation-list">
        <div class="conv-list-header">
            <h2>Messages</h2>
        </div>

        <?php if (empty($conversations)): ?>
            <div class="conv-empty">No conversations yet.</div>
        <?php else: ?>
            <?php foreach ($conversations as $conv): ?>
                <?php
                    $isActive = $activePerson && $activePerson['user_id'] == $conv['user_id'];
                ?>
                <a href="messages.php?with=<?= (int)$conv['user_id'] ?>"
                   class="conv-item <?= $isActive ? 'active' : '' ?>">

                    <div class="conv-avatar-wrap">
                        <img src="../img/<?= sanitize_string($conv['profile_picture_path'] ?? 'guest.png') ?>"
                             alt="" class="conv-avatar">
                        <?php if ($conv['unread_count'] > 0): ?>
                            <span class="conv-unread-dot"></span>
                        <?php endif; ?>
                    </div>

                    <div class="conv-info">
                        <div class="conv-top">
                            <span class="conv-name"><?= sanitize_string($conv['username']) ?></span>
                            <span class="conv-time"><?= timeAgo($conv['last_date']) ?></span>
                        </div>
                        <div class="conv-preview <?= $conv['unread_count'] > 0 ? 'unread' : '' ?>">
                            <?= sanitize_string($conv['last_message']) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- RIGHT: chat window -->
    <div class="chat-panel">

        <?php if (!$activePerson): ?>
            <!-- Empty state -->
            <div class="chat-empty-state">
                <img src="../img/notificationbell.png" alt="" class="chat-empty-icon">
                <p>Select a conversation to start chatting</p>
            </div>

        <?php else: ?>
            <!-- Chat header -->
            <div class="chat-header">
                <a href="/PROJECT/public_site/profile/profile-view.php?user_id=<?= (int)$activePerson['user_id'] ?>"
                   class="chat-header-user">
                    <img src="../img/<?= sanitize_string($activePerson['profile_picture_path'] ?? 'guest.png') ?>"
                         alt="" class="chat-header-avatar">
                    <span class="chat-header-name"><?= sanitize_string($activePerson['username']) ?></span>
                </a>
            </div>

            <!-- Messages -->
            <div class="chat-messages" id="chatMessages">
                <?php foreach ($chatMessages as $msg): ?>
                    <?php $isMine = $msg['sender_id'] == $me['user_id']; ?>
                    <div class="message-row <?= $isMine ? 'mine' : 'theirs' ?>">
                        <?php if (!$isMine): ?>
                            <img src="../img/<?= sanitize_string($msg['profile_picture_path'] ?? 'guest.png') ?>"
                                 alt="" class="msg-avatar">
                        <?php endif; ?>
                        <div class="bubble">
                            <p><?= sanitize_string($msg['description_enc']) ?></p>
                            <span class="msg-time"><?= timeAgo($msg['date']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Send form -->
            <form class="chat-input-bar" method="POST" action="">
                <input type="hidden" name="receiver_id" value="<?= (int)$activePerson['user_id'] ?>">
                <input type="text" name="message_body" class="chat-input"
                       placeholder="Type a message..." autocomplete="off" required>
                <button type="submit" name="send_message" class="chat-send-btn">Send</button>
            </form>

        <?php endif; ?>
    </div>
</div>

<script src="../js/script.js"></script>
<script>
    // Scroll chat to bottom on load
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;

    // Notification bell toggle
    const notifBtn      = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifWrapper  = document.getElementById('notifWrapper');

    if (notifBtn) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('open');
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (notifWrapper && !notifWrapper.contains(e.target)) {
            notifDropdown.classList.remove('open');
        }
    });
</script>
</body>
</html>