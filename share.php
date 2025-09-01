<?php
require 'includes/auth.php';
require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $item_type = $_POST['item_type'] ?? '';
    $item_id = intval($_POST['item_id'] ?? 0);
    $recipient_email = $_POST['recipient_email'] ?? '';
    $can_edit = intval($_POST['can_edit'] ?? 0);
    
    // Validate item type
    if (!in_array($item_type, ['file', 'folder'])) {
        die('Invalid item type');
    }
    
    // Check if user owns the item
    if ($item_type === 'file') {
        $stmt = $conn->prepare("SELECT 1 FROM files WHERE id=? AND owner_id=?");
    } else {
        $stmt = $conn->prepare("SELECT 1 FROM folders WHERE id=? AND owner_id=?");
    }
    $stmt->bind_param('ii', $item_id, $user_id);
    $stmt->execute();
    if (!$stmt->fetch()) {
        die('Item not found or access denied');
    }
    $stmt->close();
    
    // Find recipient user
    $stmt = $conn->prepare('SELECT id FROM users WHERE email=?');
    $stmt->bind_param('s', $recipient_email);
    $stmt->execute();
    $stmt->bind_result($recipient_id);
    if (!$stmt->fetch()) {
        die('Recipient not found');
    }
    $stmt->close();
    
    // Insert sharing record (INSERT IGNORE will handle duplicates)
    $stmt = $conn->prepare('INSERT IGNORE INTO shared_access (item_type, item_id, shared_with_user_id, can_edit) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('siii', $item_type, $item_id, $recipient_id, $can_edit);
    $stmt->execute();
    $stmt->close();
    
    // Send notification
    $permission_text = $can_edit ? 'with edit access' : 'with view-only access';
    $msg = "A {$item_type} was shared with you {$permission_text}.";
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $stmt->bind_param('is', $recipient_id, $msg);
    $stmt->execute();
    $stmt->close();
    
    echo 'Shared successfully!';
} else {
    http_response_code(405);
    echo 'Method not allowed';
}
?>
