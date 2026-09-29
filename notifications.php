<?php

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}
if (!in_array($_SESSION['role'] ?? '', ['staff', 'patient'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$userId = (int) $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all') {
        $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE recipient_user_id = ? AND read_at IS NULL');
        $stmt->execute([$userId]);
    } elseif ($action === 'mark' && isset($_POST['notification_id']) && is_string($_POST['notification_id']) && ctype_digit($_POST['notification_id'])) {
        $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE notification_id = ? AND recipient_user_id = ? AND read_at IS NULL');
        $stmt->execute([(int) $_POST['notification_id'], $userId]);
    }
    header('Location: notifications.php');
    exit;
}

$stmt = $pdo->prepare('SELECT notification_id, appointment_id, notification_type, title, body, created_at, read_at FROM notifications WHERE recipient_user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT 100');
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
$unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND read_at IS NULL');
$unreadStmt->execute([$userId]);
$unreadCount = (int) $unreadStmt->fetchColumn();
$isStaff = ($_SESSION['role'] ?? '') === 'staff';
$dashboard = $isStaff ? 'staff/dashboard.php' : 'patient/dashboard.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifications - The Tooth Lounge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>body{background:#f2f8f9;color:#20363c}.wrap{max-width:900px;margin:36px auto;padding:0 16px}.notification{background:#fff;border:1px solid #dfe9eb;border-radius:12px;padding:18px;margin-bottom:12px}.notification.unread{border-left:5px solid #167b8c;background:#f9fefe}.notification p{margin:6px 0;color:#54676b}.notification small{color:#78898d}.notification-actions{display:flex;gap:8px;align-items:center}.empty{background:#fff;border-radius:12px;padding:40px;text-align:center;color:#687a7e}</style>
</head>
<body>
<main class="wrap">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
        <div><a href="<?= h($dashboard) ?>" class="text-decoration-none">← Dashboard</a><h1 class="h2 mt-3 mb-1">Notifications</h1><p class="text-secondary mb-0"><?= h($unreadCount) ?> unread</p></div>
        <?php if ($unreadCount > 0): ?><form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all"><button class="btn btn-outline-primary">Mark all as read</button></form><?php endif; ?>
    </div>
    <?php if (!$notifications): ?>
        <div class="empty"><h2 class="h5">You're all caught up</h2><p class="mb-0">New appointment updates and reminders will appear here.</p></div>
    <?php else: ?>
        <?php foreach ($notifications as $item): ?>
            <article class="notification <?= empty($item['read_at']) ? 'unread' : '' ?>">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div><h2 class="h5 mb-1"><?= h($item['title']) ?></h2><p><?= h($item['body']) ?></p><small><?= h(date('M j, Y g:i A', strtotime($item['created_at']))) ?></small></div>
                    <div class="notification-actions">
                        <?php if (!empty($item['appointment_id'])): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= $isStaff ? 'staff/appointment_view.php?id=' . h($item['appointment_id']) : 'patient/appointments.php' ?>">Open appointment</a>
                        <?php endif; ?>
                        <?php if (empty($item['read_at'])): ?>
                            <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="mark"><input type="hidden" name="notification_id" value="<?= h($item['notification_id']) ?>"><button class="btn btn-sm btn-outline-secondary">Mark read</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>
