<?php
session_start();
require 'includes/db.php';
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) exit;
$user_id = $_SESSION['user_id'];
$id = (int)$_GET['id'];
// Allow if owner or explicit shared_access
$stmt = $conn->prepare("SELECT name, file_path, mime_type FROM files WHERE id=? AND (owner_id=? OR EXISTS (SELECT 1 FROM shared_access WHERE item_type='file' AND item_id=files.id AND shared_with_user_id=?))");
$stmt->bind_param('iii', $id, $user_id, $user_id);
$stmt->execute();
$stmt->bind_result($file_name, $file_path, $mime_type);
if ($stmt->fetch()) {
    $abs_path = __DIR__ . '/' . $file_path;
    if (file_exists($abs_path)) {
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="'.basename($file_name).'"');
        readfile($abs_path);
        exit;
    }
}
http_response_code(404);
echo "File not found.";