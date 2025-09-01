<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - NoteNest</title>
    <link rel="shortcut icon" href="img/fav.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- Main Content -->
<div class="container py-5">
    <div class="row justify-content-center g-4">
        <div class="col-sm-6 col-md-3">
            <div class="card text-center card-hov">
                <div class="card-body">
                    <i class="fa-solid fa-folder fa-2x mb-3" style="color: #0b4954"></i>
                    <h5 class="card-title fw-bold">MyNoteNest</h5>
                    <p class="card-text text-muted">Your personal folders and files.</p>
                    <a href="my_note_nest.php" class="btn btn-primary-cs">Open</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card text-center card-hov">
                <div class="card-body">
                    <i class="fas fa-share-alt fa-2x mb-3" style="color: #197f8f"></i>
                    <h5 class="card-title fw-bold">SharedNoteNest</h5>
                    <p class="card-text text-muted">Folders/files shared with you.</p>
                    <a href="shared_note_nest.php" class="btn btn-success-cs">Open</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card text-center card-hov">
                <div class="card-body">
                    <i class="fas fa-list-check fa-2x mb-3" style="color: #e67e22"></i>
                    <h5 class="card-title fw-bold">Todo</h5>
                    <p class="card-text text-muted">Manage your tasks and reminders.</p>
                    <a href="todo.php" class="btn todo-btn">Open</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card text-center card-hov">
                <div class="card-body">
                    <i class="fas fa-star fa-2x mb-3" style="color: #f1c40f"></i>
                    <h5 class="card-title fw-bold">Favorites</h5>
                    <p class="card-text text-muted">Quick access to your favorites.</p>
                    <a href="favorites.php" class="btn favo-btn">Open</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>