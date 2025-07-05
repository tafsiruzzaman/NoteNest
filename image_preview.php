<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['file'])) exit;
$user_id = $_SESSION['user_id'];
$file = basename($_GET['file']);

// Security: Only allow preview if current user owns the file
$stmt = $conn->prepare("SELECT id FROM images WHERE stored_file=? AND user_id=?");
$stmt->bind_param('si', $file, $user_id);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows == 1) {
    $file_path = __DIR__ . "/uploads/images/$file";
    if (file_exists($file_path)) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if ($ext === "jpg" || $ext === "jpeg") header("Content-Type: image/jpeg");
        elseif ($ext === "png") header("Content-Type: image/png");
        elseif ($ext === "gif") header("Content-Type: image/gif");
        else header("Content-Type: application/octet-stream");
        readfile($file_path);
        exit;
    }
}
http_response_code(404);
echo "Preview unavailable";
?>