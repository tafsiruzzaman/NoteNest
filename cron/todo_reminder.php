<?php
require_once __DIR__.'/../includes/db.php';
$now = date('Y-m-d H:i:s');
$hour_later = date('Y-m-d H:i:s', strtotime('+1 hour'));
$sql = "SELECT t.id, t.user_id, t.title, t.event_date, t.event_time FROM todos t LEFT JOIN todo_notifications n ON t.id=n.todo_id WHERE t.is_completed=0 AND n.id IS NULL AND CONCAT(t.event_date, ' ', t.event_time) BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $now, $hour_later);
$stmt->execute();
$stmt->bind_result($todo_id, $user_id, $title, $date, $time);
while ($stmt->fetch()) {
    $msg = "Reminder: Todo '$title' is due at $date $time.";
    $stmt2 = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
    $stmt2->bind_param('is', $user_id, $msg);
    $stmt2->execute();
    $stmt2->close();
    $stmt3 = $conn->prepare('INSERT INTO todo_notifications (todo_id) VALUES (?)');
    $stmt3->bind_param('i', $todo_id);
    $stmt3->execute();
    $stmt3->close();
}
$stmt->close();
