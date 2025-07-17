<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$upload_error = "";

// Handle file upload (POST-Redirect-GET)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image_file'])) {
    $file_name = trim(htmlspecialchars($_POST['file_name'] ?? ''));
    $file = $_FILES['image_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_error = "No file uploaded or upload error.";
    } elseif (!in_array($ext, $allowed)) {
        $upload_error = "Only image files (.jpg, .jpeg, .png, .gif) allowed.";
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $upload_error = "File must be less than 2 MB.";
    } elseif (empty($file_name)) {
        $upload_error = "Please enter an image name.";
    } else {
        $rand_file = uniqid("img_", true) . "." . $ext;
        $target_dir = __DIR__ . '/uploads/images/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_path = $target_dir . $rand_file;
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $stmt = $conn->prepare("INSERT INTO images (user_id, file_name, stored_file) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $user_id, $file_name, $rand_file);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_msg'] = "Image uploaded!";
            header("Location: my_images.php");
            exit;
        } else {
            $upload_error = "Failed to save image file.";
        }
    }
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT stored_file FROM images WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 1) {
        $stmt->bind_result($stored_file);
        $stmt->fetch();
        $stmt->close();
        $conn->query("DELETE FROM images WHERE id=$id");
        @unlink(__DIR__ . "/uploads/images/$stored_file");
        header("Location: my_images.php");
        exit;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Images - myDrive</title>
  <link rel="shortcut icon" href="img/cloud.png" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .table th, .table td { vertical-align: middle !important; }
    .modal-img-preview { max-width: 100%; border-radius: 12px; }
  </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
        <i class="fas fa-cloud fa-lg me-2 brand-icon"></i>
        <span class="brand-text fw-bold">myDrive</span>
    </a>
    <div class="d-flex align-items-center ms-auto">
        <span class="me-3 user-name text-secondary"><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a class="btn btn-link support-link" href="#" title="Support"><i class="fas fa-life-ring"></i> Support</a>
        <a class="btn btn-link text-danger logout-link" href="logout.php" title="Logout"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="container py-5">
  <div class="row g-5">
    <!-- Add image form -->
    <div class="col-md-4">
      <div class="card mb-4">
        <div class="card-header bg-success text-white">
          <i class="fas fa-upload me-2"></i>Add New Image
        </div>
        <div class="card-body">
          <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success py-2"><?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
          <?php endif; ?>
          <?php if($upload_error): ?>
            <div class="alert alert-danger py-2"><?= $upload_error ?></div>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" autocomplete="off">
            <div class="mb-2">
              <label class="form-label">Image Name</label>
              <input type="text" name="file_name" class="form-control" maxlength="100" required placeholder="Enter image name">
            </div>
            <div class="mb-2">
              <label class="form-label">Select Image</label>
              <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.gif" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success mt-2 w-100"><i class="fas fa-upload"></i> Upload Image</button>
          </form>
        </div>
      </div>
    </div>
    <!-- Images table -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-header bg-white text-success">
          <i class="fas fa-image me-2"></i>My Images
        </div>
        <div class="card-body table-responsive">
<?php
// Fetch images
$stmt = $conn->prepare("SELECT id, file_name, stored_file, uploaded_at FROM images WHERE user_id=? ORDER BY uploaded_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($iid, $ifile, $stored_file, $uploaded_at);
$images = [];
while($stmt->fetch()) $images[] = [$iid, $ifile, $stored_file, $uploaded_at];
$stmt->close();

if(empty($images)): ?>
  <div class="alert alert-secondary text-muted">You have no images. Upload your first image!</div>
<?php else: ?>
  <table class="table align-middle table-hover">
    <thead>
      <tr>
        <th scope="col">#</th>
        <th scope="col">Image Name</th>
        <th scope="col">Uploaded</th>
        <th scope="col" class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($images as $index=>$img): ?>
      <tr>
        <th><?=$index+1?></th>
        <td><?=htmlspecialchars($img[1])?></td>
        <td><?=date('d M Y, H:i', strtotime($img[3]))?></td>
        <td class="text-end">
          <a href="image_download.php?id=<?=$img[0]?>" title="Download" class="btn btn-sm btn-outline-secondary me-1">
              <i class="fas fa-download"></i>
          </a>
          <button type="button" class="btn btn-sm btn-outline-info me-1 preview-btn"
            data-file="<?=urlencode($img[2])?>" data-name="<?=htmlspecialchars($img[1])?>" title="Preview">
            <i class="fas fa-eye"></i>
          </button>
          <a href="my_images.php?delete=<?=$img[0]?>"
             onclick="return confirm('Are you sure to delete this image?');"
             class="btn btn-sm btn-outline-danger" title="Delete">
            <i class="fas fa-trash"></i>
          </a>
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

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewLabel">Image Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <img id="previewImg" class="modal-img-preview" src="" alt="Preview image">
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.preview-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        let file = btn.getAttribute('data-file');
        let name = btn.getAttribute('data-name');
        document.getElementById('previewLabel').textContent = name + " (Preview)";
        document.getElementById('previewImg').src = 'image_preview.php?file=' + file;
        let modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    });
});
</script>
</body>
</html>