<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['file'])) exit;
$user_id = $_SESSION['user_id'];
$file = $_GET['file'];
// Allow if owner or explicit shared_access
$stmt = $conn->prepare("SELECT file_path, mime_type FROM files WHERE file_path=? AND (owner_id=? OR EXISTS (SELECT 1 FROM shared_access WHERE item_type='file' AND item_id=files.id AND shared_with_user_id=?))");
$stmt->bind_param('sii', $file, $user_id, $user_id);
$stmt->execute();
$stmt->bind_result($file_path, $mime_type);
if ($stmt->fetch()) {
    $abs_path = __DIR__ . '/' . $file_path;
    if (file_exists($abs_path)) {
        if (strpos($mime_type, 'text/') === 0) {
            header('Content-Type: text/plain; charset=UTF-8');
            readfile($abs_path);
            exit;
        } elseif (strpos($mime_type, 'image/') === 0) {
            header('Content-Type: ' . $mime_type);
            readfile($abs_path);
            exit;
        } elseif (strpos($mime_type, 'application/pdf') === 0) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
            readfile($abs_path);
            exit;
        }
    }
}
echo "Preview unavailable";