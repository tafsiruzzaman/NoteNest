<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) exit;
$user_id = $_SESSION['user_id'];
$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT file_name, stored_file FROM images WHERE id=? AND user_id=?");
$stmt->bind_param('ii', $id, $user_id);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows == 1) {
    $stmt->bind_result($file_name, $stored_file); $stmt->fetch();
    $file_path = __DIR__."/uploads/images/$stored_file";
    if (file_exists($file_path)) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime = "application/octet-stream";
        if ($ext === "jpg" || $ext === "jpeg") $mime = "image/jpeg";
        elseif ($ext === "png") $mime = "image/png";
        elseif ($ext === "gif") $mime = "image/gif";
        header('Content-Type: '.$mime);
        header('Content-Disposition: attachment; filename="'.basename($file_name).".".$ext.'"');
        readfile($file_path);
        exit;
    }
}
http_response_code(404);
echo "File not found.";
?>