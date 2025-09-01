<?php
// --- UTILS ---
function folder_belongs_to_user($conn, $folder_id, $user_id) {
    $stmt = $conn->prepare("SELECT 1 FROM folders WHERE id=? AND owner_id=?");
    $stmt->bind_param('ii', $folder_id, $user_id);
    $stmt->execute(); $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}
function get_folder_path($conn, $folder_id, $user_id) {
    $path = [];
    while ($folder_id) {
        $stmt = $conn->prepare("SELECT id, name, parent_folder_id FROM folders WHERE id=? AND owner_id=?");
        $stmt->bind_param('ii', $folder_id, $user_id);
        $stmt->execute();
        $stmt->bind_result($id, $name, $parent_id);
        if ($stmt->fetch()) {
            array_unshift($path, ['id' => $id, 'name' => $name]);
            $folder_id = $parent_id;
        } else {
            break;
        }
        $stmt->close();
    }
    return $path;
}
require 'includes/auth.php';
require 'includes/db.php';
$user_id = $_SESSION['user_id'];
$upload_error = $folder_error = $modal_message = '';
$current_folder_id = isset($_GET['folder']) && is_numeric($_GET['folder']) ? intval($_GET['folder']) : null;

// --- Brute-force recursive share ---
function share_folder_children($conn, $folder_id, $recipient_user_id) {
    // Share all subfolders
    $stmt = $conn->prepare("SELECT id FROM folders WHERE parent_folder_id = ?");
    $stmt->bind_param('i', $folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subfolders = [];
    while ($row = $result->fetch_assoc()) $subfolders[] = $row['id'];
    $stmt->close();
    // Share all files in this folder
    $stmt = $conn->prepare("SELECT id FROM files WHERE folder_id = ?");
    $stmt->bind_param('i', $folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $files = [];
    while ($row = $result->fetch_assoc()) $files[] = $row['id'];
    $stmt->close();
    // Share subfolders recursively
    foreach ($subfolders as $subfolder_id) {
        $stmt = $conn->prepare("INSERT IGNORE INTO shared_access (item_type, item_id, shared_with_user_id) VALUES ('folder', ?, ?)");
        $stmt->bind_param('ii', $subfolder_id, $recipient_user_id);
        $stmt->execute(); $stmt->close();
        share_folder_children($conn, $subfolder_id, $recipient_user_id);
    }
    // Share all files
    foreach ($files as $file_id) {
        $stmt = $conn->prepare("INSERT IGNORE INTO shared_access (item_type, item_id, shared_with_user_id) VALUES ('file', ?, ?)");
        $stmt->bind_param('ii', $file_id, $recipient_user_id);
        $stmt->execute(); $stmt->close();
    }
}
// Brute-force recursive revoke
function revoke_folder_children($conn, $folder_id, $recipient_user_id) {
    $stmt = $conn->prepare("SELECT id FROM folders WHERE parent_folder_id = ?");
    $stmt->bind_param('i', $folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subfolders = [];
    while ($row = $result->fetch_assoc()) $subfolders[] = $row['id'];
    $stmt->close();
    $stmt = $conn->prepare("SELECT id FROM files WHERE folder_id = ?");
    $stmt->bind_param('i', $folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $files = [];
    while ($row = $result->fetch_assoc()) $files[] = $row['id'];
    $stmt->close();
    foreach ($subfolders as $subfolder_id) {
        $stmt = $conn->prepare("DELETE FROM shared_access WHERE item_type='folder' AND item_id=? AND shared_with_user_id=?");
        $stmt->bind_param('ii', $subfolder_id, $recipient_user_id);
        $stmt->execute(); $stmt->close();
        revoke_folder_children($conn, $subfolder_id, $recipient_user_id);
    }
    foreach ($files as $file_id) {
        $stmt = $conn->prepare("DELETE FROM shared_access WHERE item_type='file' AND item_id=? AND shared_with_user_id=?");
        $stmt->bind_param('ii', $file_id, $recipient_user_id);
        $stmt->execute(); $stmt->close();
    }
}

// --- AJAX/POST HANDLERS (MUST BE BEFORE ANY HTML OUTPUT) ---
if (isset($_POST['share_item'])) {
    $item_type = $_POST['item_type'];
    $item_id = intval($_POST['item_id']);
    $recipient_email = trim($_POST['recipient_email']);
    if (!in_array($item_type, ['file', 'folder'])) exit('Invalid item type.');
    // Validate ownership
    if ($item_type === 'file') {
        $stmt = $conn->prepare("SELECT 1 FROM files WHERE id=? AND owner_id=?");
        $stmt->bind_param('ii', $item_id, $user_id);
    } else {
        $stmt = $conn->prepare("SELECT 1 FROM folders WHERE id=? AND owner_id=?");
        $stmt->bind_param('ii', $item_id, $user_id);
    }
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows === 0) exit('Invalid item or not owned by you.');
    $stmt->close();
    // Lookup recipient by email
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email=?");
    $stmt->bind_param('s', $recipient_email);
    $stmt->execute();
    $stmt->bind_result($recipient_id, $recipient_name);
    if (!$stmt->fetch()) exit('User not found.');
    $stmt->close();
    if ($recipient_id === $user_id) exit('Cannot share with yourself.');
    // Share the item
    $stmt = $conn->prepare("INSERT IGNORE INTO shared_access (item_type, item_id, shared_with_user_id) VALUES (?, ?, ?)");
    $stmt->bind_param('sii', $item_type, $item_id, $recipient_id);
    $stmt->execute(); $stmt->close();
    // If sharing a folder, recursively share its contents
    if ($item_type === 'folder') share_folder_children($conn, $item_id, $recipient_id);
    // Create notification
    $item_name = '';
    if ($item_type === 'file') {
        $stmt = $conn->prepare("SELECT name FROM files WHERE id=?");
        $stmt->bind_param('i', $item_id);
        $stmt->execute(); $stmt->bind_result($item_name); $stmt->fetch(); $stmt->close();
    } else {
        $stmt = $conn->prepare("SELECT name FROM folders WHERE id=?");
        $stmt->bind_param('i', $item_id);
        $stmt->execute(); $stmt->bind_result($item_name); $stmt->fetch(); $stmt->close();
    }
    $owner_name = $_SESSION['user_name'];
    $message = ucfirst($item_type) . ' "' . $item_name . '" has been shared with you by ' . $owner_name . '.';
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->bind_param('is', $recipient_id, $message);
    $stmt->execute(); $stmt->close();
    exit('Shared successfully.');
}
if (isset($_POST['revoke_share'])) {
    $item_type = $_POST['item_type'];
    $item_id = intval($_POST['item_id']);
    $recipient_id = intval($_POST['recipient_id']);
    if (!in_array($item_type, ['file', 'folder'])) exit('Invalid item type.');
    // Validate ownership
    if ($item_type === 'file') {
        $stmt = $conn->prepare("SELECT 1 FROM files WHERE id=? AND owner_id=?");
        $stmt->bind_param('ii', $item_id, $user_id);
    } else {
        $stmt = $conn->prepare("SELECT 1 FROM folders WHERE id=? AND owner_id=?");
        $stmt->bind_param('ii', $item_id, $user_id);
    }
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows === 0) exit('Invalid item or not owned by you.');
    $stmt->close();
    if ($item_type === 'file') {
        $stmt = $conn->prepare("DELETE FROM shared_access WHERE item_type='file' AND item_id=? AND shared_with_user_id=?");
        $stmt->bind_param('ii', $item_id, $recipient_id);
        $stmt->execute(); $stmt->close();
    } else {
        // Revoke folder and all its descendants
        revoke_folder_children($conn, $item_id, $recipient_id);
        $stmt = $conn->prepare("DELETE FROM shared_access WHERE item_type='folder' AND item_id=? AND shared_with_user_id=?");
        $stmt->bind_param('ii', $item_id, $recipient_id);
        $stmt->execute(); $stmt->close();
    }
    exit('Access revoked.');
}
if (isset($_POST['favorite_item'])) {
    $item_type = $_POST['item_type'];
    $item_id = intval($_POST['item_id']);
    $is_fav = isset($_POST['is_fav']) ? 1 : 0;
    if ($is_fav) {
        $stmt = $conn->prepare('DELETE FROM favorites WHERE user_id=? AND item_type=? AND item_id=?');
        $stmt->bind_param('isi', $user_id, $item_type, $item_id);
        $stmt->execute(); $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT IGNORE INTO favorites (user_id, item_type, item_id) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $user_id, $item_type, $item_id);
        $stmt->execute(); $stmt->close();
    }
    exit('ok');
}
// --- END AJAX/POST HANDLERS ---

if ($current_folder_id !== null && !folder_belongs_to_user($conn, $current_folder_id, $user_id)) {
    header("Location: my_note_nest.php");
    exit;
}
// --- CREATE FOLDER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_folder'])) {
    $folder_name = trim($_POST['folder_name'] ?? '');
    if ($folder_name == '') {
        $folder_error = "Folder name cannot be empty.";
    } elseif (mb_strlen($folder_name) > 100) {
        $folder_error = "Folder name too long (max 100 chars).";
    } else {
        $stmt = $conn->prepare("SELECT 1 FROM folders WHERE owner_id=? AND name=? AND ((parent_folder_id IS NULL AND ? IS NULL) OR parent_folder_id=?)");
        $stmt->bind_param('issi', $user_id, $folder_name, $current_folder_id, $current_folder_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $folder_error = "A folder with this name already exists here.";
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO folders (owner_id, name, parent_folder_id) VALUES (?, ?, ?)");
            if ($current_folder_id !== null) {
                $stmt->bind_param('isi', $user_id, $folder_name, $current_folder_id);
            } else {
                $null = null;
                $stmt->bind_param('isi', $user_id, $folder_name, $null);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_msg'] = "Folder created!";
            $folder_error = "";
        }
    }
    if ($folder_error == "") {
        $_SESSION['history_flatten'] = true;
        header("Location: my_note_nest.php" . ($current_folder_id ? "?folder=$current_folder_id" : ""));
        exit;
    }
}
// --- RENAME FOLDER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rename_folder'])) {
    $rename_folder_id = intval($_POST['rename_folder_id']);
    $rename_name = trim($_POST['rename_folder_name'] ?? '');
    if (!folder_belongs_to_user($conn, $rename_folder_id, $user_id)) {
        $_SESSION['folder_delete_error'] = "Invalid folder.";
    } elseif ($rename_name === '') {
        $_SESSION['folder_delete_error'] = "Folder name cannot be empty.";
    } elseif (mb_strlen($rename_name) > 100) {
        $_SESSION['folder_delete_error'] = "Folder name too long.";
    } else {
        $stmt = $conn->prepare("SELECT parent_folder_id FROM folders WHERE id=? AND owner_id=?");
        $stmt->bind_param('ii', $rename_folder_id, $user_id);
        $stmt->execute();
        $stmt->bind_result($parent_id); $stmt->fetch();
        $stmt->close();
        $stmt = $conn->prepare(
            "SELECT 1 FROM folders WHERE owner_id=? AND name=? AND ((parent_folder_id IS NULL AND ? IS NULL) OR parent_folder_id=?) AND id<>?"
        );
        $stmt->bind_param('issii', $user_id, $rename_name, $parent_id, $parent_id, $rename_folder_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['folder_delete_error'] = "A folder with that name already exists here.";
        } else {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE folders SET name=? WHERE id=? AND owner_id=?");
            $stmt->bind_param('sii', $rename_name, $rename_folder_id, $user_id);
            $stmt->execute(); $stmt->close();
            $_SESSION['success_msg'] = "Folder renamed!";
        }
    }
    $_SESSION['history_flatten'] = true;
    header("Location: my_note_nest.php" . ($current_folder_id ? "?folder=$current_folder_id" : ""));
    exit;
}
// --- RENAME FILE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rename_file'])) {
    $rename_file_id = intval($_POST['rename_file_id']);
    $rename_name = trim($_POST['rename_file_name'] ?? '');
    if ($rename_name === '') {
        $_SESSION['file_rename_error'] = "File name cannot be empty.";
    } elseif (mb_strlen($rename_name) > 100) {
        $_SESSION['file_rename_error'] = "File name too long.";
    } else {
        $stmt = $conn->prepare("UPDATE files SET name=? WHERE id=? AND owner_id=?");
        $stmt->bind_param('sii', $rename_name, $rename_file_id, $user_id);
        $stmt->execute(); $stmt->close();
        $_SESSION['success_msg'] = "File renamed!";
    }
    $_SESSION['history_flatten'] = true;
    $redirect_folder = $current_folder_id ? "?folder=$current_folder_id" : "";
    header("Location: my_note_nest.php$redirect_folder");
    exit;
}
// --- UPLOAD FILE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['note_file'])) {
    $file_name = trim(htmlspecialchars($_POST['file_name'] ?? ''));
    $file = $_FILES['note_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $target_folder_id = isset($_POST['parent_folder_id']) && $_POST['parent_folder_id'] !== '' && is_numeric($_POST['parent_folder_id'])
        ? intval($_POST['parent_folder_id']) : null;
    if ($target_folder_id !== null && !folder_belongs_to_user($conn, $target_folder_id, $user_id)) {
        $upload_error = "Invalid target folder!";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_error = "No file uploaded or upload error.";
    } elseif (empty($file_name)) {
        $upload_error = "Please enter a file name.";
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $upload_error = "File must be less than 10 MB.";
    } else {
        $rand_file = uniqid("file_", true) . "." . $ext;
        $target_dir = __DIR__ . '/uploads/notes/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_path = $target_dir . $rand_file;
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $mime = mime_content_type($target_path);
            $db_path = 'uploads/notes/' . $rand_file;
            if ($target_folder_id !== null) {
                $stmt = $conn->prepare("INSERT INTO files (owner_id, name, file_path, mime_type, folder_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('isssi', $user_id, $file_name, $db_path, $mime, $target_folder_id);
            } else {
                $null = null;
                $stmt = $conn->prepare("INSERT INTO files (owner_id, name, file_path, mime_type, folder_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('isssi', $user_id, $file_name, $db_path, $mime, $null);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_msg'] = "File uploaded!";
            $upload_error = "";
        } else {
            $upload_error = "Failed to save file.";
        }
    }
    if ($upload_error == "") {
        $_SESSION['history_flatten'] = true;
        header("Location: my_note_nest.php" . ($current_folder_id ? "?folder=$current_folder_id" : ""));
        exit;
    }
}
// --- DELETE FILE ---
if (isset($_GET['delete_file']) && is_numeric($_GET['delete_file'])) {
    $id = (int)$_GET['delete_file'];
    $stmt = $conn->prepare("SELECT file_path, folder_id FROM files WHERE id=? AND owner_id=?");
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows == 1) {
        $stmt->bind_result($file_path, $file_folder_id); $stmt->fetch(); $stmt->close();
        $conn->query("DELETE FROM files WHERE id=$id");
        @unlink(__DIR__ . "/" . $file_path);
        $_SESSION['history_flatten'] = true;
        header("Location: my_note_nest.php" . ($file_folder_id ? "?folder=" . $file_folder_id : ""));
        exit;
    }
    $stmt->close();
}
// --- DELETE FOLDER (only if empty) ---
if (isset($_GET['delete_folder']) && is_numeric($_GET['delete_folder'])) {
    $folder_id = intval($_GET['delete_folder']);
    if (folder_belongs_to_user($conn, $folder_id, $user_id)) {
        $stmt = $conn->prepare("SELECT 1 FROM folders WHERE parent_folder_id=? AND owner_id=?");
        $stmt->bind_param('ii', $folder_id, $user_id);
        $stmt->execute(); $stmt->store_result();
        $has_subfolders = $stmt->num_rows > 0; $stmt->close();
        $stmt = $conn->prepare("SELECT 1 FROM files WHERE folder_id=? AND owner_id=?");
        $stmt->bind_param('ii', $folder_id, $user_id);
        $stmt->execute(); $stmt->store_result();
        $has_files = $stmt->num_rows > 0; $stmt->close();
        if ($has_subfolders || $has_files) {
            $_SESSION['folder_delete_error'] = "Folder is not empty.";
            $_SESSION['history_flatten'] = true;
            header("Location: my_note_nest.php" . ($current_folder_id ? "?folder=$current_folder_id" : ""));
            exit;
        } else {
            $stmt = $conn->prepare("SELECT parent_folder_id FROM folders WHERE id=?");
            $stmt->bind_param('i', $folder_id);
            $stmt->execute();
            $stmt->bind_result($redirect_parent_id);
            $stmt->fetch();
            $stmt->close();
            $conn->query("DELETE FROM folders WHERE id = $folder_id");
            $_SESSION['success_msg'] = "Folder deleted.";
            $_SESSION['history_flatten'] = true;
            header("Location: my_note_nest.php" . ($redirect_parent_id ? "?folder=$redirect_parent_id" : ""));
            exit;
        }
    }
}
// --- LOAD FOLDERS AND FILES FOR OWNER ---
$folders = [];
if ($current_folder_id === null) {
    $stmt = $conn->prepare("SELECT id, name FROM folders WHERE owner_id=? AND parent_folder_id IS NULL ORDER BY name");
    $stmt->bind_param('i', $user_id);
} else {
    $stmt = $conn->prepare("SELECT id, name FROM folders WHERE owner_id=? AND parent_folder_id=? ORDER BY name");
    $stmt->bind_param('ii', $user_id, $current_folder_id);
}
$stmt->execute();
$stmt->bind_result($fid, $fname);
while ($stmt->fetch()) $folders[] = [$fid, $fname];
$stmt->close();
$files = [];
if ($current_folder_id === null) {
    $stmt = $conn->prepare("SELECT id, name, file_path, mime_type, created_at FROM files WHERE owner_id=? AND folder_id IS NULL ORDER BY created_at DESC");
    $stmt->bind_param('i', $user_id);
} else {
    $stmt = $conn->prepare("SELECT id, name, file_path, mime_type, created_at FROM files WHERE owner_id=? AND folder_id=? ORDER BY created_at DESC");
    $stmt->bind_param('ii', $user_id, $current_folder_id);
}
$stmt->execute();
$stmt->bind_result($nid, $nname, $npath, $nmime, $ncreated);
while ($stmt->fetch()) $files[] = [$nid, $nname, $npath, $nmime, $ncreated];
$stmt->close();
// --- END LOAD FOLDERS/FILES ---
// Get favorites for this user
$fav_ids = ['file'=>[], 'folder'=>[]];
$res = $conn->query("SELECT item_type, item_id FROM favorites WHERE user_id=$user_id");
while ($row = $res->fetch_assoc()) $fav_ids[$row['item_type']][] = $row['item_id'];
// Get shared status for items
$shared_items = ['file'=>[], 'folder'=>[]];
$res = $conn->query("SELECT item_type, item_id FROM shared_access WHERE item_id IN (SELECT id FROM files WHERE owner_id=$user_id UNION SELECT id FROM folders WHERE owner_id=$user_id)");
while ($row = $res->fetch_assoc()) $shared_items[$row['item_type']][] = $row['item_id'];
// --- Breadcrumbs ---
$breadcrumbs = get_folder_path($conn, $current_folder_id, $user_id);
if (isset($_SESSION['success_msg'])) {
    $modal_message = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
} elseif (isset($_SESSION['folder_delete_error'])) {
    $modal_message = $_SESSION['folder_delete_error'];
    unset($_SESSION['folder_delete_error']);
} elseif (isset($_SESSION['file_rename_error'])) {
    $modal_message = $_SESSION['file_rename_error'];
    unset($_SESSION['file_rename_error']);
}
if ($modal_message) {
    echo "<script>if (history.replaceState) history.replaceState(null, '', window.location.pathname + window.location.search);</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MyNoteNest - NoteNest</title>
  <link rel="shortcut icon" href="img/fav.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/my_note_nest.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-md-4">
      <!-- BREADCRUMBS -->
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white px-2 py-2 rounded shadow-sm">
          <li class="breadcrumb-item">
            <?php if ($current_folder_id !== null): ?>
              <a href="my_note_nest.php"><i class="fas fa-house"></i> All Folders</a>
            <?php else: ?>
              <span><i class="fas fa-house"></i> All Folders</span>
            <?php endif; ?>
          </li>
          <?php foreach ($breadcrumbs as $i=>$bc): ?>
              <li class="breadcrumb-item <?= $i==count($breadcrumbs)-1 ? 'active' : '' ?>">
                <?php if ($i==count($breadcrumbs)-1): ?>
                  <?= htmlspecialchars($bc['name']) ?>
                <?php else: ?>
                  <a href="my_note_nest.php?folder=<?= $bc['id'] ?>"><?= htmlspecialchars($bc['name']) ?></a>
                <?php endif; ?>
              </li>
          <?php endforeach; ?>
        </ol>
      </nav>
      <!-- CREATE FOLDER FORM -->
      <div class="card mb-3">
        <div class="card-header bg text-white">
          <i class="fas fa-folder-plus me-2"></i>Create New Folder
        </div>
        <div class="card-body">
          <?php if($folder_error): ?>
            <div class="alert alert-danger py-2"><?= $folder_error ?></div>
          <?php endif; ?>
          <form method="post" autocomplete="off">
            <div class="input-group">
              <input type="text" name="folder_name" class="form-control" maxlength="100" required placeholder="Folder Name">
              <button class="btn btn-primary-cs" type="submit" name="create_folder"><i class="fa fa-plus"></i> Add</button>
            </div>
          </form>
        </div>
      </div>
      <!-- NOTE UPLOAD FORM -->
      <div class="card">
        <div class="card-header bg text-white">
          <i class="fas fa-upload me-2"></i>Add New Note (Any Format)
        </div>
        <div class="card-body">
          <?php if($upload_error): ?>
            <div class="alert alert-danger py-2"><?= $upload_error ?></div>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" autocomplete="off">
            <div class="mb-2">
              <label class="form-label">Note Name</label>
              <input type="text" name="file_name" class="form-control" maxlength="100" required placeholder="Enter note name">
            </div>
            <div class="mb-2">
              <label class="form-label">Select Note File</label>
              <input type="file" name="note_file" accept="*.*" class="form-control" required>
            </div>
            <input type="hidden" name="parent_folder_id" value="<?= htmlspecialchars($current_folder_id ?? '') ?>">
            <button type="submit" class="btn upload-btn mt-2 w-100 text-white">
                <i class="fas fa-upload"></i> Upload Note
            </button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <!-- FOLDERS HEADING -->
      <div class="section-heading mb-2">
        <i class="fas fa-folder-open"></i> Folders
      </div>
      <!-- SUBFOLDER LIST -->
      <?php if(empty($folders)): ?>
        <p class="text-muted">No subfolders here.</p>
      <?php else: ?>
        <ul class="list-group folder-list-group mb-3">
          <?php foreach($folders as $f): ?>
            <li class="list-group-item d-flex align-items-center justify-content-between">
              <div>
                <a href="my_note_nest.php?folder=<?= $f[0] ?>" class="folder-link">
                  <i class="fa fa-folder folder-icon"></i><?= htmlspecialchars($f[1]) ?>
                </a>
              </div>
              <div>
                <button type="button" 
                  class="btn btn-sm btn-outline-primary folder-action-btn me-1 rename-folder-btn"
                  data-id="<?= $f[0] ?>" data-name="<?= htmlspecialchars($f[1]) ?>" title="Rename">
                  <i class="fas fa-pen"></i>
                </button>
                <a href="my_note_nest.php?delete_folder=<?= $f[0] ?>"
                   onclick="return confirm('Delete this folder? Folder must be empty!');"
                    class="btn btn-sm btn-outline-danger folder-action-btn" title="Delete Folder">
                    <i class="fas fa-trash"></i>
                </a>
                <a href="#" class="btn btn-sm btn-outline-warning me-1 favorite-btn" data-type="folder" data-id="<?= $f[0] ?>" data-fav="<?= in_array($f[0], $fav_ids['folder']) ? 1 : 0 ?>" title="Favorite">
                  <i class="fa<?= in_array($f[0], $fav_ids['folder']) ? 's' : 'r' ?> fa-star"></i>
                </a>
                                 <?php if (in_array($f[0], $shared_items['folder'])): ?>
                   <a href="#" class="btn btn-sm btn-outline-success me-1 shared-status-btn" data-type="folder" data-id="<?= $f[0] ?>" title="Manage Sharing">
                     <i class="fas fa-users"></i>
                   </a>
                 <?php endif; ?>
                 <a href="#" class="btn btn-sm btn-outline-info share-btn" data-type="folder" data-id="<?= $f[0] ?>" title="Share"><i class="fa fa-share"></i></a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <!-- NOTES HEADING -->
      <div class="section-heading mb-2" style="margin-top:32px;">
        <i class="fas fa-note-sticky"></i> Notes<?= ($current_folder_id !== null)? ' in "' . htmlspecialchars(end($breadcrumbs)['name']) . '"' : '' ?>
      </div>
      <div class="card">
        <div class="card-body table-responsive">
          <?php if(empty($files)): ?>
            <div class="alert alert-secondary text-muted mb-0">No notes in this folder.</div>
          <?php else: ?>
            <table class="table align-middle table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Note Name</th>
                  <th>Uploaded</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($files as $index=>$n): ?>
                <tr>
                  <th><?= $index+1 ?></th>
                  <td><?= htmlspecialchars($n[1]) ?></td>
                  <td><?= date('d M Y, H:i', strtotime($n[4])) ?></td>
                  <td class="text-end">
                    <a href="note_download.php?id=<?= $n[0] ?>" title="Download" class="btn btn-sm btn-outline-secondary me-1">
                      <i class="fas fa-download"></i>
                    </a>
                    <?php if (strpos($n[3], 'image/') === 0): ?>
                      <button type="button" class="btn btn-sm btn-outline-info me-1 preview-btn"
                              data-file="<?= htmlspecialchars($n[2]) ?>" data-name="<?= htmlspecialchars($n[1]) ?>" data-type="image" title="Preview">
                        <i class="fas fa-eye"></i>
                      </button>
                    <?php elseif (strpos($n[3], 'text/') === 0): ?>
                      <button type="button" class="btn btn-sm btn-outline-info me-1 preview-btn"
                              data-file="<?= htmlspecialchars($n[2]) ?>" data-name="<?= htmlspecialchars($n[1]) ?>" data-type="text" title="Preview">
                        <i class="fas fa-eye"></i>
                      </button>
                    <?php elseif (strpos($n[3], 'application/pdf') === 0): ?>
                      <button type="button" class="btn btn-sm btn-outline-info me-1 preview-btn"
                              data-file="<?= htmlspecialchars($n[2]) ?>" data-name="<?= htmlspecialchars($n[1]) ?>" data-type="pdf" title="Preview">
                        <i class="fas fa-eye"></i>
                      </button>
                    <?php endif; ?>
                    <a href="my_note_nest.php?delete_file=<?= $n[0] ?>"
                       onclick="return confirm('Are you sure to delete this file?');"
                       class="btn btn-sm btn-outline-danger" title="Delete">
                      <i class="fas fa-trash"></i>
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-warning me-1 favorite-btn" data-type="file" data-id="<?= $n[0] ?>" data-fav="<?= in_array($n[0], $fav_ids['file']) ? 1 : 0 ?>" title="Favorite">
                      <i class="fa<?= in_array($n[0], $fav_ids['file']) ? 's' : 'r' ?> fa-star"></i>
                    </a>
                                         <?php if (in_array($n[0], $shared_items['file'])): ?>
                       <a href="#" class="btn btn-sm btn-outline-success me-1 shared-status-btn" data-type="file" data-id="<?= $n[0] ?>" title="Manage Sharing">
                         <i class="fas fa-users"></i>
                       </a>
                     <?php endif; ?>
                     <a href="#" class="btn btn-sm btn-outline-info share-btn" data-type="file" data-id="<?= $n[0] ?>" title="Share"><i class="fa fa-share"></i></a>
                    <button type="button" class="btn btn-sm btn-outline-primary me-1 rename-file-btn" data-id="<?= $n[0] ?>" data-name="<?= htmlspecialchars($n[1]) ?>" title="Rename"><i class="fas fa-pen"></i></button>
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
        <h5 class="modal-title" id="previewLabel">Note Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <pre id="previewContent" style="font-family:inherit"></pre>
      </div>
    </div>
  </div>
</div>
<!-- Rename Folder Modal -->
<div class="modal fade" id="renameFolderModal" tabindex="-1" aria-labelledby="renameFolderLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="renameFolderLabel">Rename Folder</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="rename_folder_id" id="rename_folder_id">
        <div class="mb-3">
            <label for="rename_folder_name" class="form-label">New Folder Name</label>
            <input type="text" name="rename_folder_name" id="rename_folder_name" class="form-control" maxlength="100" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="rename_folder" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
<!-- Rename File Modal -->
<div class="modal fade" id="renameFileModal" tabindex="-1" aria-labelledby="renameFileLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="renameFileLabel">Rename File</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="rename_file_id" id="rename_file_id">
        <div class="mb-3">
            <label for="rename_file_name" class="form-label">New File Name</label>
            <input type="text" name="rename_file_name" id="rename_file_name" class="form-control" maxlength="100" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="rename_file" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
<!-- Feedback Modal -->
<?php if($modal_message): ?>
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="feedbackModalLabel">Notice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <?= htmlspecialchars($modal_message) ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<!-- Add Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="shareForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="shareModalLabel">Share Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="item_type" id="share_item_type">
        <input type="hidden" name="item_id" id="share_item_id">
        <div class="mb-3">
          <label class="form-label">Recipient Email</label>
          <input type="email" name="recipient_email" class="form-control" required>
        </div>
        <div class="mb-3">
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> Shared items are view-only. Recipients can preview, download, and add to favorites.
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Share</button>
      </div>
    </form>
  </div>
</div>

<!-- Share Management Modal -->
<div class="modal fade" id="shareManagementModal" tabindex="-1" aria-labelledby="shareManagementLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="shareManagementLabel">Manage Sharing</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="shareManagementContent">
          <!-- Content will be loaded dynamically -->
        </div>
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
        let type = btn.getAttribute('data-type');
        if (type === 'image') {
            document.getElementById('previewContent').innerHTML = '<img src="' + file + '" style="max-width:100%;max-height:60vh;">';
        } else if (type === 'text') {
            fetch('note_preview.php?file=' + encodeURIComponent(file))
              .then(r=>r.text())
              .then(text => {
                  document.getElementById('previewContent').innerHTML = '<pre style="font-family:inherit;max-height:60vh;overflow-y:auto;">' + text + '</pre>';
              });
        } else if (type === 'pdf') {
            document.getElementById('previewContent').innerHTML = '<iframe src="' + file + '" style="width:100%;height:60vh;" frameborder="0"></iframe>';
        }
        document.getElementById('previewLabel').textContent = name + " (Preview)";
        let modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    });
});
document.querySelectorAll('.rename-folder-btn').forEach(function(btn) {
    btn.addEventListener('click', function () {
        document.getElementById('rename_folder_id').value = btn.getAttribute('data-id');
        document.getElementById('rename_folder_name').value = btn.getAttribute('data-name');
        let modal = new bootstrap.Modal(document.getElementById('renameFolderModal'));
        modal.show();
        setTimeout(function() {
            document.getElementById('rename_folder_name').focus();
        }, 150);
    });
});
<?php if ($modal_message): ?>
    const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    window.addEventListener('DOMContentLoaded', function() { feedbackModal.show(); });
    document.getElementById('feedbackModal').addEventListener('hidden.bs.modal', function () {
        window.location.replace(window.location.pathname + window.location.search);
    });
    setTimeout(() => { feedbackModal.hide(); }, 2500);
<?php endif; ?>
<?php if (!empty($_SESSION['history_flatten'])): ?>
    if (window.history.replaceState) {
        window.history.replaceState(null, '', window.location.pathname + window.location.search);
    }
<?php unset($_SESSION['history_flatten']); endif; ?>
// Add favorite functionality
document.querySelectorAll('.favorite-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        let type = btn.getAttribute('data-type');
        let id = btn.getAttribute('data-id');
        let isFav = btn.getAttribute('data-fav') === '1';
        let formData = new FormData();
        formData.append('favorite_item', 1);
        formData.append('item_type', type);
        formData.append('item_id', id);
        formData.append('is_fav', isFav ? 1 : 0);
        fetch('', {method:'POST', body:formData})
          .then(r=>r.text())
          .then(() => { location.reload(); });
    });
});
// Add share functionality
document.querySelectorAll('.share-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        let type = btn.getAttribute('data-type');
        let id = btn.getAttribute('data-id');
        document.getElementById('share_item_type').value = type;
        document.getElementById('share_item_id').value = id;
        let modal = new bootstrap.Modal(document.getElementById('shareModal'));
        modal.show();
    });
});
// Handle share form submission
document.getElementById('shareForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let formData = new FormData(this);
    formData.append('share_item', 1);
    fetch('', {method:'POST', body:formData})
      .then(r=>r.text())
      .then(msg => {
        alert(msg);
        location.reload();
      });
});
// Add rename file functionality
document.querySelectorAll('.rename-file-btn').forEach(function(btn) {
    btn.addEventListener('click', function () {
        document.getElementById('rename_file_id').value = btn.getAttribute('data-id');
        document.getElementById('rename_file_name').value = btn.getAttribute('data-name');
        let modal = new bootstrap.Modal(document.getElementById('renameFileModal'));
        modal.show();
        setTimeout(function() {
            document.getElementById('rename_file_name').focus();
        }, 150);
    });
});

// Add share management functionality (robust revoke handler)
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.shared-status-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      let type = btn.getAttribute('data-type');
      let id = btn.getAttribute('data-id');
      fetch('share_management.php?item_type=' + type + '&item_id=' + id)
        .then(response => response.text())
        .then(html => {
          document.getElementById('shareManagementContent').innerHTML = html;
          let modal = new bootstrap.Modal(document.getElementById('shareManagementModal'));
          modal.show();
          // Attach revoke handler
          document.querySelectorAll('.revoke-btn').forEach(function(revokeBtn) {
            revokeBtn.onclick = function() {
              let type = revokeBtn.getAttribute('data-type');
              let item_id = revokeBtn.getAttribute('data-id');
              let recipient = revokeBtn.getAttribute('data-recipient');
              let name = revokeBtn.getAttribute('data-name');
              if (confirm('Are you sure you want to revoke access for ' + name + '?')) {
                let formData = new FormData();
                formData.append('revoke_share', 1);
                formData.append('item_type', type);
                formData.append('item_id', item_id);
                formData.append('recipient_id', recipient);
                fetch('my_note_nest.php', {method: 'POST', body: formData})
                  .then(response => response.text())
                  .then(msg => {
                    alert(msg);
                    location.reload();
                  });
              }
            };
          });
        });
    });
  });
});
</script>
</body>
</html>
