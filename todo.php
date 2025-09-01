<?php
require 'includes/auth.php';
require 'includes/db.php';
include 'includes/navbar.php';

$user_id = $_SESSION['user_id'];
$modal_message = "";

// --- Handle Add/Edit/Mark as Done ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $details = trim($_POST['details'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    $todo_id = isset($_POST['todo_id']) ? intval($_POST['todo_id']) : null;

    $event_datetime = "$event_date $event_time:00";
    if ($title === '' || !$event_date || !$event_time) {
        $modal_message = "Title, date, and time are required.";
    } elseif ($todo_id) {
        // Update
        $stmt = $conn->prepare("UPDATE todos SET title=?, event_datetime=?, details=?, status=? WHERE id=? AND user_id=?");
        $stmt->bind_param('ssssii', $title, $event_datetime, $details, $status, $todo_id, $user_id);
        $stmt->execute(); $stmt->close();
        $modal_message = "Todo updated!";
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO todos (user_id, title, event_datetime, details, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param('isss', $user_id, $title, $event_datetime, $details);
        $stmt->execute(); $stmt->close();
        $modal_message = "Todo added!";
    }
    // Insert notification for 1hr before event
    if (!$todo_id) {
        $reminder_time = date('Y-m-d H:i:s', strtotime("$event_datetime -1 hour"));
        $msg = "Upcoming Todo: $title at $event_time";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $user_id, $msg, $reminder_time);
        $stmt->execute(); $stmt->close();
    }
    header("Location: todo.php");
    exit;
}

// Mark as done
if (isset($_GET['done']) && is_numeric($_GET['done'])) {
    $id = intval($_GET['done']);
    $stmt = $conn->prepare("UPDATE todos SET status='done' WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute(); $stmt->close();
    $modal_message = "Todo marked as done!";
    header("Location: todo.php");
    exit;
}

// --- Fetch Todos ---
$todos = [];
$res = $conn->query("SELECT id, title, event_datetime, details, status FROM todos WHERE user_id=$user_id ORDER BY event_datetime ASC");
while ($row = $res->fetch_assoc()) $todos[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Todo List - NoteNest</title>
  <link rel="shortcut icon" href="img/fav.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/my_note_nest.css">
  <link rel="stylesheet" href="css/todo.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .todo-done { text-decoration: line-through; color: #888; }
    .todo-upcoming { background: #e8f7ff; }
    .todo-pending { background: #fffbe7; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-md-5">
      <div class="card mb-3">
        <div class="card-header bg text-white">
          <i class="fas fa-plus"></i> Add / Edit Todo
        </div>
        <div class="card-body">
          <form method="post" autocomplete="off">
            <input type="hidden" name="todo_id" id="todo_id">
            <div class="mb-2">
              <label class="form-label">Title</label>
              <input type="text" name="title" id="title" class="form-control" maxlength="100" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Event Date</label>
              <input type="date" name="event_date" id="event_date" class="form-control" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Event Time</label>
              <input type="time" name="event_time" id="event_time" class="form-control" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Details</label>
              <textarea name="details" id="details" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn submit-btn w-100"><i class="fas fa-save"></i> Save</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-7">
      <div class="section-heading mb-2"><i class="fas fa-list-check"></i> My Todos</div>
      <div class="card">
        <div class="card-body table-responsive">
          <?php if(empty($todos)): ?>
            <div class="alert alert-secondary text-muted mb-0">No todos yet.</div>
          <?php else: ?>
            <table class="table align-middle table-hover">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($todos as $todo): 
                  $dt = explode(' ', $todo['event_datetime']);
                  $row_class = $todo['status']=='done' ? 'todo-done' : (strtotime($todo['event_datetime']) > time() ? 'todo-upcoming' : 'todo-pending');
                ?>
                <tr class="<?= $row_class ?>">
                  <td><?= htmlspecialchars($todo['title']) ?></td>
                  <td><?= htmlspecialchars($dt[0]) ?></td>
                  <td><?= htmlspecialchars(substr($dt[1],0,5)) ?></td>
                  <td>
                    <?php if($todo['status']=='done'): ?>
                      <span class="badge bg-success">Done</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary me-1 edit-btn"
                      data-id="<?= $todo['id'] ?>"
                      data-title="<?= htmlspecialchars($todo['title']) ?>"
                      data-date="<?= $dt[0] ?>"
                      data-time="<?= substr($dt[1],0,5) ?>"
                      data-details="<?= htmlspecialchars($todo['details']) ?>"
                      title="Edit"><i class="fas fa-pen"></i></button>
                    <?php if($todo['status']!='done'): ?>
                      <a href="todo.php?done=<?= $todo['id'] ?>" class="btn btn-sm btn-outline-success" title="Mark as Done"><i class="fas fa-check"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php if($modal_message): ?>
<script>
  window.onload = () => alert("<?= htmlspecialchars($modal_message) ?>");
</script>
<?php endif; ?>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.onclick = function() {
    document.getElementById('todo_id').value = btn.getAttribute('data-id');
    document.getElementById('title').value = btn.getAttribute('data-title');
    document.getElementById('event_date').value = btn.getAttribute('data-date');
    document.getElementById('event_time').value = btn.getAttribute('data-time');
    document.getElementById('details').value = btn.getAttribute('data-details');
    window.scrollTo({top:0,behavior:'smooth'});
  };
});
</script>
</body>
</html>