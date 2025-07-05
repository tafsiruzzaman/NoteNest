<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['file'])) exit;
$user_id = $_SESSION['user_id'];
$file = basename($_GET['file']);

$stmt = $conn->prepare("SELECT id FROM notes WHERE stored_file=? AND user_id=?");
$stmt->bind_param('si', $file, $user_id);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows == 1) {
    $file_path = __DIR__ . "/uploads/notes/$file";
    if (file_exists($file_path)) {
        header('Content-Type: text/plain; charset=UTF-8');
        readfile($file_path);
        exit;
    }
}
echo "Preview unavailable";