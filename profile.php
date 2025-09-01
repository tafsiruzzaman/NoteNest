<?php
// filepath: c:\xampp\htdocs\NoteNest\profile.php
require 'includes/auth.php';
require 'includes/db.php';
include 'includes/navbar.php';

$user_id = $_SESSION['user_id'];
$modal_message = "";

// Fetch user info
$stmt = $conn->prepare("SELECT name, email, phone, gender, photo FROM users WHERE id=?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($name, $email, $phone, $gender, $photo);
$stmt->fetch();
$stmt->close();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $new_gender = $_POST['gender'] ?? '';
    $new_pass = $_POST['password'] ?? '';
    $new_pass2 = $_POST['confirm_password'] ?? '';
    $update_photo = $photo;

    // Handle photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $target_dir = "img/user_photos/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $target_file = $target_dir . $new_filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                // Optionally delete old photo if not default
                if ($photo && $photo !== 'img/user.png' && file_exists($photo)) {
                    @unlink($photo);
                }
                $update_photo = $target_file;
            } else {
                $modal_message = "Failed to upload photo.";
            }
        } else {
            $modal_message = "Invalid photo format. Allowed: jpg, jpeg, png, gif.";
        }
    }

    if ($new_name === '') $modal_message = "Name is required.";
    elseif ($new_pass && $new_pass !== $new_pass2) $modal_message = "Passwords do not match.";
    else {
        if ($new_pass) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, gender=?, password=?, photo=? WHERE id=?");
            $stmt->bind_param('sssssi', $new_name, $new_phone, $new_gender, $hash, $update_photo, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, phone=?, gender=?, photo=? WHERE id=?");
            $stmt->bind_param('ssssi', $new_name, $new_phone, $new_gender, $update_photo, $user_id);
        }
        $stmt->execute(); $stmt->close();
        $_SESSION['user_name'] = $new_name;
        $modal_message = "Profile updated!";
        header("Location: profile.php");
        exit;
    }
    // Refresh photo if changed
    $photo = $update_photo;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile - NoteNest</title>
  <link rel="shortcut icon" href="img/fav.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/my_note_nest.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .profile-img-preview { width:80px; height:80px; object-fit:cover; border-radius:50%; }
  </style>
</head>
<body>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card shadow">
        <div class="card-header bg text-white">
          <i class="fas fa-user"></i> My Profile
        </div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data" autocomplete="off">
            <div class="row mb-3 align-items-center">
              <div class="col-auto">
                <img src="<?= htmlspecialchars($photo) ?>" alt="Profile" class="profile-img-preview" id="profileImgPreview">
              </div>
              <div class="col">
                <label for="photo" class="form-label mb-1">Change Photo</label>
                <input type="file" name="photo" id="photo" class="form-control form-control-sm" accept="image/*" onchange="previewPhoto(this)">
                <small class="text-muted">Allowed: jpg, jpeg, png, gif</small>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">Name</label>
              <input type="text" name="name" class="form-control" maxlength="100" value="<?= htmlspecialchars($name) ?>" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" disabled>
            </div>
            <div class="mb-2">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" maxlength="20" value="<?= htmlspecialchars($phone) ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-select" required>
                <option value="">Select Gender</option>
                <option value="Male" <?= $gender=='Male'?'selected':'' ?>>Male</option>
                <option value="Female" <?= $gender=='Female'?'selected':'' ?>>Female</option>
                <option value="Other" <?= $gender=='Other'?'selected':'' ?>>Other</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">New Password</label>
              <input type="password" name="password" class="form-control" placeholder="Leave blank to keep unchanged">
            </div>
            <div class="mb-2">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" class="form-control" placeholder="Leave blank to keep unchanged">
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Update Profile</button>
          </form>
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
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('profileImgPreview').src = e.target.result;
    }
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>