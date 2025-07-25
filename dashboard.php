<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
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

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
        <img src="img/fav.ico" height="45px" alt="">
        <span class="brand-text fw-bold">NoteNest</span>
    </a>
    <div class="d-flex align-items-center ms-auto">
        <span class="me-3 user-name text-secondary"><i class="fas fa-user-circle me-1"></i>
          <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a class="btn btn-link support-link" href="#" title="Support">
            <i class="fas fa-life-ring"></i> Support
        </a>
        <a class="btn btn-link text-danger logout-link" href="logout.php" title="Logout">
            <i class="fas fa-right-from-bracket"></i> Logout
        </a>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div class="container py-5">
    <div class="row justify-content-center g-4">
        <div class="col-sm-6 col-md-4">
            <div class="card text-center card-hov">
                <div class="card-body">
                    <i class="fas fa-note-sticky fa-2x mb-3" style="color: #0b4954"></i>
                    <h5 class="card-title fw-bold">My Notes</h5>
                    <p class="card-text text-muted">View, add, and manage your text notes securely in the cloud.</p>
                    <a href="my_notes.php" class="btn btn-primary-cs">Open Notes</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-4">
            <div class="card text-center card-hov">
                <div class="card-body">
                    <i class="fas fa-image fa-2x mb-3" style="color: #197f8f"></i>
                    <h5 class="card-title fw-bold">My Images</h5>
                    <p class="card-text text-muted">Upload and access your images from anywhere, anytime.</p>
                    <a href="my_images.php" class="btn btn-success-cs">Open Images</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>