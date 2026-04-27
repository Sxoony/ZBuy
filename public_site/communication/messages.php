<?php
require_once '../reuse/db-conn.php';
require_once '../reuse/authHelper.php';
require_once '../reuse/functions.php';
requireLogin();

$me = currentUser();

// All conversations
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

// Active conversation
$activePerson = null;
$chatMessages = [];

if (isset($_GET['with'])) {
    $withId = (int)$_GET['with'];

    $stmt = $pdo->prepare('SELECT user_id, username, profile_picture_path FROM users WHERE user_id = ?');
    $stmt->execute([$withId]);
    $activePerson = $stmt->fetch();

    if ($activePerson) {
        $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
        $stmt->execute([$withId, $me['user_id']]);

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

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $body       = trim($_POST['message_body'] ?? '');

    if ($receiverId && $body !== '') {
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, description_enc) VALUES (?, ?, ?)');
        $stmt->execute([$me['user_id'], $receiverId, $body]);
    }

    redirect('/PROJECT/public_site/communication/messages.php?with=' . $receiverId);
}

// Notification bell
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Messages page layout ── */
        .messages-layout {
            display: flex;
            height: calc(100vh - 56px);
            overflow: hidden;
        }

        /* Conversation list */
        .conv-list {
            width: 300px;
            flex-shrink: 0;
            border-right: 1px solid var(--border);
            overflow-y: auto;
            background: var(--bg-card);
        }

        .conv-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--text-dark);
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        .conv-item:hover  { background: var(--bg-main); }
        .conv-item.active { background: var(--bg-sidebar); }

        .conv-avatar-wrap { position: relative; flex-shrink: 0; }
        .conv-avatar      { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
        .conv-unread-dot  {
            position: absolute; bottom: 2px; right: 2px;
            width: 10px; height: 10px;
            background: var(--primary); border-radius: 50%;
            border: 2px solid var(--bg-card);
        }

        .conv-info     { flex: 1; min-width: 0; }
        .conv-top      { display: flex; justify-content: space-between; align-items: baseline; }
        .conv-name     { font-weight: 600; font-size: 14px; }
        .conv-time     { font-size: 11px; color: var(--text-light); white-space: nowrap; }
        .conv-preview  { font-size: 12px; color: var(--text-light); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .conv-preview.unread { font-weight: 600; color: var(--text-dark); }

        /* Chat panel */
        .chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: var(--bg-main);
        }

        .chat-header {
            padding: 12px 20px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-header-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .chat-header-name   { font-weight: 600; color: var(--text-dark); text-decoration: none; }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .message-row       { display: flex; align-items: flex-end; gap: 8px; }
        .message-row.mine  { flex-direction: row-reverse; }
        .msg-avatar        { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }

        .bubble {
            max-width: 65%;
            padding: 10px 14px;
            border-radius: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border);
        }
        .bubble p    { margin: 0; font-size: 14px; color: var(--text-dark); }
        .bubble .msg-time { display: block; font-size: 10px; color: var(--text-light); margin-top: 4px; }

        .message-row.mine .bubble {
            background: var(--primary);
            border-color: var(--primary);
        }
        .message-row.mine .bubble p,
        .message-row.mine .bubble .msg-time { color: #fff; }

        .chat-input-bar {
            padding: 12px 16px;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
        }
        .chat-input {
            flex: 1;
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: var(--bg-main);
            color: var(--text-dark);
            outline: none;
            font-size: 14px;
        }
        .chat-input:focus { border-color: var(--primary); }

        .chat-empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            gap: 12px;
        }

        /* Notification dropdown */
        .notif-wrapper  { position: relative; }
        .notif-dropdown {
            display: none;
            position: absolute;
            right: 0; top: calc(100% + 8px);
            width: 300px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            z-index: 200;
        }
        .notif-dropdown.open { display: block; }
        .notif-header {
            padding: 12px 16px;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
        }
        .notif-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            text-decoration: none;
            color: var(--text-dark);
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .notif-item:hover { background: var(--bg-main); }
        .notif-avatar  { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .notif-preview { color: var(--text-light); font-size: 12px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
        .notif-time    { color: var(--text-light); font-size: 11px; }
        .notif-empty   { padding: 20px; text-align: center; color: var(--text-light); font-size: 13px; }
        .notif-badge   {
            position: absolute; top: -4px; right: -4px;
            background: #ef4444; color: #fff;
            border-radius: 50%; width: 16px; height: 16px;
            font-size: 10px; display: flex; align-items: center; justify-content: center;
            font-weight: 700;
        }
    </style>
</head>
<body>

<!-- ── NAV ── -->
<nav class="navbar-custom d-flex justify-content-between align-items-center px-4" style="height:56px;">
    <a href="/PROJECT/public_site/index.php" class="fw-bold text-decoration-none" style="color:var(--primary);">Marketplace</a>

    <div class="d-flex align-items-center gap-3">

        <!-- Notification bell -->
        <div class="notif-wrapper" id="notifWrapper">
            <button class="btn btn-sm btn-outline-secondary position-relative" id="notifBtn" style="border:none; background:none;">
                <img src="../img/notificationbell.png" style="width:20px;">
                <?php if ($notificationCount > 0): ?>
                    <span class="notif-badge"><?= $notificationCount ?></span>
                <?php endif; ?>
            </button>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span>Notifications</span>
                    <?php if ($notificationCount > 0): ?>
                        <span class="text-muted small"><?= $notificationCount ?> new</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="notif-empty">No new notifications</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <a href="messages.php?with=<?= (int)($notif['user_id'] ?? 0) ?>" class="notif-item">
                            <img src="../img/<?= sanitize_string($notif['profile_picture_path'] ?? 'guest.png') ?>"
                                 class="notif-avatar">
                            <div>
                                <span class="fw-semibold"><?= sanitize_string($notif['username']) ?></span>
                                <span class="notif-preview"><?= sanitize_string($notif['description_enc']) ?></span>
                                <span class="notif-time"><?= timeAgo($notif['date']) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profile avatar -->
        <a href="/PROJECT/public_site/profile/profile-view.php">
            <img src="../img/<?= sanitize_string($me['profile_picture_path'] ?? 'guest.png') ?>"
                 class="avatar" style="width:32px; height:32px;">
        </a>
    </div>
</nav>

<!-- ── MESSAGES LAYOUT ── -->
<div class="messages-layout">

    <!-- LEFT: conversation list -->
    <div class="conv-list">
        <div class="px-3 py-3 fw-bold border-bottom" style="font-size:15px;">Messages</div>

        <?php if (empty($conversations)): ?>
            <div class="notif-empty">No conversations yet.</div>
        <?php else: ?>
            <?php foreach ($conversations as $conv):
                $isActive = $activePerson && $activePerson['user_id'] == $conv['user_id'];
            ?>
                <a href="messages.php?with=<?= (int)$conv['user_id'] ?>"
                   class="conv-item <?= $isActive ? 'active' : '' ?>">

                    <div class="conv-avatar-wrap">
                        <img src="../img/<?= sanitize_string($conv['profile_picture_path'] ?? 'guest.png') ?>"
                             class="conv-avatar">
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

    <!-- RIGHT: chat panel -->
    <div class="chat-panel">

        <?php if (!$activePerson): ?>
            <div class="chat-empty-state">
                <img src="../img/notificationbell.png" style="width:48px; opacity:0.3;">
                <p class="mb-0">Select a conversation to start chatting</p>
            </div>

        <?php else: ?>
            <!-- Header -->
            <div class="chat-header">
                <img src="../img/<?= sanitize_string($activePerson['profile_picture_path'] ?? 'guest.png') ?>"
                     class="chat-header-avatar">
                <a href="/PROJECT/public_site/profile/profile-view.php?user_id=<?= (int)$activePerson['user_id'] ?>"
                   class="chat-header-name">
                    <?= sanitize_string($activePerson['username']) ?>
                </a>
            </div>

            <!-- Messages -->
            <div class="chat-messages" id="chatMessages">
                <?php foreach ($chatMessages as $msg):
                    $isMine = $msg['sender_id'] == $me['user_id'];
                ?>
                    <div class="message-row <?= $isMine ? 'mine' : 'theirs' ?>">
                        <?php if (!$isMine): ?>
                            <img src="../img/<?= sanitize_string($msg['profile_picture_path'] ?? 'guest.png') ?>"
                                 class="msg-avatar">
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
                <button type="submit" name="send_message" class="btn btn-primary btn-sm px-3">Send</button>
            </form>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/script.js"></script>
<script>
    // Scroll to bottom
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;

    // Notification bell
    const notifBtn      = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifWrapper  = document.getElementById('notifWrapper');

    if (notifBtn) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('open');
        });
    }

    document.addEventListener('click', (e) => {
        if (notifWrapper && !notifWrapper.contains(e.target)) {
            notifDropdown.classList.remove('open');
        }
    });

    // Dark mode
    if (localStorage.getItem('darkMode') === 'true') document.body.classList.add('dark-mode');
</script>
</body>
</html>