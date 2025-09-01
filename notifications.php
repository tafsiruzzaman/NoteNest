<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) exit;
$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
if ($action === 'count') {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    echo $count;
    $stmt->close();
    exit;
} elseif ($action === 'latest') {
    $stmt = $conn->prepare('SELECT message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($message, $created_at, $is_read);
    $notifs = [];
    while ($stmt->fetch()) {
        $notifs[] = ['message'=>$message, 'created_at'=>$created_at, 'is_read'=>$is_read];
    }
    $stmt->close();
    if (empty($notifs)) {
        echo '<div class="text-center text-muted">No notifications.</div>';
    } else {
        foreach ($notifs as $n) {
            echo '<div class="border-bottom py-2">';
            echo '<div class="small '.($n['is_read']?'text-muted':'fw-bold').'">'.htmlspecialchars($n['message']).'</div>';
            echo '<div class="text-secondary small">'.date('M d, H:i', strtotime($n['created_at'])).'</div>';
            echo '</div>';
        }
    }
    exit;
} elseif ($action === 'mark_read') {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");
    echo 'ok';
    exit;
}
echo 'Invalid action';
