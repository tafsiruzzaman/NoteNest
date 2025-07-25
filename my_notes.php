<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$upload_error = "";
$folder_error = "";
$modal_message = "";

// Sanity: get current folder
$current_folder_id = isset($_GET['folder']) && is_numeric($_GET['folder']) ? intval($_GET['folder']) : null;

// ----- UTILS -----
function folder_belongs_to_user($conn, $folder_id, $user_id) {
    $stmt = $conn->prepare("SELECT 1 FROM note_folders WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $folder_id, $user_id);
    $stmt->execute(); $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}
if ($current_folder_id !== null && !folder_belongs_to_user($conn, $current_folder_id, $user_id)) {
    header("Location: my_notes.php");
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
        $stmt = $conn->prepare("SELECT 1 FROM note_folders WHERE user_id=? AND folder_name=? AND ((parent_id IS NULL AND ? IS NULL) OR parent_id=?)");
        $stmt->bind_param('issi', $user_id, $folder_name, $current_folder_id, $current_folder_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $folder_error = "A folder with this name already exists here.";
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO note_folders (user_id, folder_name, parent_id) VALUES (?, ?, ?)");
            if ($current_folder_id !== null) {
                $stmt->bind_param('isi', $user_id, $folder_name, $current_folder_id);
            } else {
                $null = null;
                $stmt->bind_param('isi', $user_id, $folder_name, $null);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_msg'] = "Folder created!";
            $folder_error = ""; // Clear folder_error on success
        }
    }
    if ($folder_error == "") {
        $_SESSION['history_flatten'] = true;
        header("Location: my_notes.php" . ($current_folder_id ? "?folder=$current_folder_id" : ""));
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
        $stmt = $conn->prepare("SELECT parent_id FROM note_folders WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $rename_folder_id, $user_id);
        $stmt->execute();
        $stmt->bind_result($parent_id); $stmt->fetch();
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT 1 FROM note_folders WHERE user_id=? AND folder_name=? AND ((parent_id IS NULL AND ? IS NULL) OR parent_id=?) AND id<>?"
        );
        $stmt->bind_param('issii', $user_id, $rename_name, $parent_id, $parent_id, $rename_folder_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['folder_delete_error'] = "A folder with that name already exists here.";
        } else {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE note_folders SET folder_name=? WHERE id=? AND user_id=?");
            $stmt->bind_param('sii', $rename_name, $rename_folder_id, $user_id);
            $stmt->execute(); $stmt->close();
            $_SESSION['success_msg'] = "Folder renamed!";
        }
    }
    $_SESSION['history_flatten'] = true;
    header("Location: my_notes.php" . ($current_folder_id ? "?folder=$current_folder_id" : ""));
    exit;
}

// --- UPLOAD/CREATE NOTE ---
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
    } elseif ($ext !== 'txt') {
        $upload_error = "Only .txt files are allowed.";
    } elseif ($file['size'] > 256 * 1024) {
        $upload_error = "File must be less than 256 KB.";
    } elseif (empty($file_name)) {
        $upload_error = "Please enter a note name.";
    } else {
        $rand_file = uniqid("note_", true) . ".txt";
        $target_dir = __DIR__ . '/uploads/notes/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_path = $target_dir . $rand_file;
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $stmt = $conn->prepare("INSERT INTO notes (user_id, file_name, stored_file, folder_id) VALUES (?, ?, ?, ?)");
            if ($target_folder_id !== null) {
                $stmt->bind_param('issi', $user_id, $file_name, $rand_file, $target_folder_id);
            } else {
                $null = null;
                $stmt->bind_param('issi', $user_id, $file_name, $rand_file, $null);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_msg'] = "Note uploaded!";
            $upload_error = ""; // Clear upload_error on success
        } else {
            $upload_error = "Failed to save file.";
        }
    }
    if ($upload_error == "") {
        $_SESSION['history_flatten'] = true;
        header("Location: my_notes.php" . ($current_folder_id ? "?folder=$current_folder_id" : ""));
        exit;
    }
}

// --- DELETE NOTE ---
if (isset($_GET['delete_note']) && is_numeric($_GET['delete_note'])) {
    $id = (int)$_GET['delete_note'];
    $stmt = $conn->prepare("SELECT stored_file, folder_id FROM notes WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $id, $user_id);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows == 1) {
        $stmt->bind_result($stored_file, $note_folder_id); $stmt->fetch(); $stmt->close();
        $conn->query("DELETE FROM notes WHERE id=$id");
        @unlink(__DIR__ . "/uploads/notes/$stored_file");
        $_SESSION['history_flatten'] = true;
        header("Location: my_notes.php" . ($note_folder_id ? "?folder=" . $note_folder_id : ""));
        exit;
    }
    $stmt->close();
}

// --- DELETE FOLDER (only if empty) ---
if (isset($_GET['delete_folder']) && is_numeric($_GET['delete_folder'])) {
    $folder_id = intval($_GET['delete_folder']);
    if (folder_belongs_to_user($conn, $folder_id, $user_id)) {
        $stmt = $conn->prepare("SELECT 1 FROM note_folders WHERE parent_id=? AND user_id=?");
        $stmt->bind_param('ii', $folder_id, $user_id);
        $stmt->execute(); $stmt->store_result();
        $has_subfolders = $stmt->num_rows > 0; $stmt->close();

        $stmt = $conn->prepare("SELECT 1 FROM notes WHERE folder_id=? AND user_id=?");
        $stmt->bind_param('ii', $folder_id, $user_id);
        $stmt->execute(); $stmt->store_result();
        $has_notes = $stmt->num_rows > 0; $stmt->close();

        if ($has_subfolders || $has_notes) {
            $_SESSION['folder_delete_error'] = "Folder is not empty.";
            $_SESSION['history_flatten'] = true;
            header("Location: my_notes.php" . ($current_folder_id ? "?folder=$current_folder_id" : ""));
            exit;
        } else {
            $stmt = $conn->prepare("SELECT parent_id FROM note_folders WHERE id=?");
            $stmt->bind_param('i', $folder_id);
            $stmt->execute();
            $stmt->bind_result($redirect_parent_id);
            $stmt->fetch();
            $stmt->close();
            $conn->query("DELETE FROM note_folders WHERE id = $folder_id");
            $_SESSION['success_msg'] = "Folder deleted.";
            $_SESSION['history_flatten'] = true;
            header("Location: my_notes.php" . ($redirect_parent_id ? "?folder=$redirect_parent_id" : ""));
            exit;
        }
    }
}

// After successful add/delete, set a session flag for history flattening
// This block is now redundant as history flattening is handled by the specific POST/GET redirects
// if (isset($_SESSION['success_msg']) || isset($_SESSION['folder_delete_error'])) {
//     $_SESSION['history_flatten'] = true;
//     header("Location: my_notes.php" . ($current_folder_id ? "?folder=$current_folder_id" : ""));
//     exit;
// }

// --- Folders/Notes for current folder:
$folders = [];
$stmt = $conn->prepare("SELECT id, folder_name FROM note_folders WHERE user_id=? AND " .
                       ($current_folder_id == null ? "parent_id IS NULL" : "parent_id=?"));
if ($current_folder_id == null) { $stmt->bind_param('i', $user_id); }
else { $stmt->bind_param('ii', $user_id, $current_folder_id); }
$stmt->execute();
$stmt->bind_result($fid, $fname);
while ($stmt->fetch()) $folders[] = [$fid, $fname];
$stmt->close();

$notes = [];
if ($current_folder_id === null) {
    $stmt = $conn->prepare("SELECT id, file_name, stored_file, uploaded_at FROM notes WHERE user_id=? AND folder_id IS NULL ORDER BY uploaded_at DESC");
    $stmt->bind_param('i', $user_id);
} else {
    $stmt = $conn->prepare("SELECT id, file_name, stored_file, uploaded_at FROM notes WHERE user_id=? AND folder_id=? ORDER BY uploaded_at DESC");
    $stmt->bind_param('ii', $user_id, $current_folder_id);
}
$stmt->execute();
$stmt->bind_result($nid, $nfile, $stored_file, $uploaded_at);
while($stmt->fetch()) $notes[] = [$nid, $nfile, $stored_file, $uploaded_at];
$stmt->close();

// --- Breadcrumbs ---
function get_folder_path($conn, $folder_id, $user_id) {
    $path = [];
    while ($folder_id) {
        $stmt = $conn->prepare("SELECT id, folder_name, parent_id FROM note_folders WHERE id=? AND user_id=?");
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
$breadcrumbs = get_folder_path($conn, $current_folder_id, $user_id);

if (isset($_SESSION['success_msg'])) {
    $modal_message = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
} elseif (isset($_SESSION['folder_delete_error'])) {
    $modal_message = $_SESSION['folder_delete_error'];
    unset($_SESSION['folder_delete_error']);
}
if ($modal_message) {
    echo "<script>if (history.replaceState) history.replaceState(null, '', window.location.pathname + window.location.search);</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Notes - NoteNest</title>
  <link rel="shortcut icon" href="img/fav.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/my_notes.css">
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
        <span class="me-3 user-name text-secondary"><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a class="btn btn-link support-link" href="#" title="Support"><i class="fas fa-life-ring"></i> Support</a>
        <a class="btn btn-link text-danger logout-link" href="logout.php" title="Logout"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <div class="row g-4">
    <div class="col-md-4">
      <!-- BREADCRUMBS -->
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white px-2 py-2 rounded shadow-sm">
          <li class="breadcrumb-item">
            <?php if ($current_folder_id !== null): ?>
              <a href="my_notes.php"><i class="fas fa-house"></i> All Folders</a>
            <?php else: ?>
              <span><i class="fas fa-house"></i> All Folders</span>
            <?php endif; ?>
          </li>
          <?php foreach ($breadcrumbs as $i=>$bc): ?>
              <li class="breadcrumb-item <?= $i==count($breadcrumbs)-1 ? 'active' : '' ?>">
                <?php if ($i==count($breadcrumbs)-1): ?>
                  <?= htmlspecialchars($bc['name']) ?>
                <?php else: ?>
                  <a href="my_notes.php?folder=<?= $bc['id'] ?>"><?= htmlspecialchars($bc['name']) ?></a>
                <?php endif; ?>
              </li>
          <?php endforeach; ?>
        </ol>
      </nav>
      <!-- CREATE FOLDER FORM -->
      <div class="card mb-3">
        <div class="card-header bg text-white" style="background-color: #0b4954">
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
        <div class="card-header bg text-white" style="background-color: #0b4954">
          <i class="fas fa-upload me-2"></i>Add New Note (.txt)
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
              <label class="form-label">Select .txt file</label>
              <input type="file" name="note_file" accept=".txt" class="form-control" required>
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
                <a href="my_notes.php?folder=<?= $f[0] ?>" class="folder-link">
                  <i class="fa fa-folder folder-icon"></i><?= htmlspecialchars($f[1]) ?>
                </a>
              </div>
              <div>
                <button type="button" 
                  class="btn btn-sm btn-outline-primary folder-action-btn me-1 rename-folder-btn"
                  data-id="<?= $f[0] ?>" data-name="<?= htmlspecialchars($f[1]) ?>" title="Rename">
                  <i class="fas fa-pen"></i>
                </button>
                <a href="my_notes.php?delete_folder=<?= $f[0] ?>"
                   onclick="return confirm('Delete this folder? Folder must be empty!');"
                    class="btn btn-sm btn-outline-danger folder-action-btn" title="Delete Folder">
                    <i class="fas fa-trash"></i>
                </a>
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
          <?php if(empty($notes)): ?>
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
                <?php foreach($notes as $index=>$n): ?>
                <tr>
                  <th><?= $index+1 ?></th>
                  <td><?= htmlspecialchars($n[1]) ?></td>
                  <td><?= date('d M Y, H:i', strtotime($n[3])) ?></td>
                  <td class="text-end">
                    <a href="note_download.php?id=<?= $n[0] ?>" title="Download" class="btn btn-sm btn-outline-secondary me-1">
                      <i class="fas fa-download"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-info me-1 preview-btn"
                            data-file="<?= htmlspecialchars($n[2]) ?>" data-name="<?= htmlspecialchars($n[1]) ?>" title="Preview">
                      <i class="fas fa-eye"></i>
                    </button>
                    <a href="my_notes.php?delete_note=<?= $n[0] ?>"
                       onclick="return confirm('Are you sure to delete this note?');"
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.preview-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        let file = btn.getAttribute('data-file');
        let name = btn.getAttribute('data-name');
        fetch('note_preview.php?file=' + encodeURIComponent(file))
          .then(r=>r.text())
          .then(text => {
              document.getElementById('previewLabel').textContent = name + " (Preview)";
              document.getElementById('previewContent').textContent = text;
              let modal = new bootstrap.Modal(document.getElementById('previewModal'));
              modal.show();
          });
    });
});
// RENAME FOLDER MODAL
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
// FEEDBACK MODAL for notices/history flatten
<?php if ($modal_message): ?>
    const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    window.addEventListener('DOMContentLoaded', function() { feedbackModal.show(); });
    document.getElementById('feedbackModal').addEventListener('hidden.bs.modal', function () {
        window.location.replace(window.location.pathname + window.location.search);
    });
    setTimeout(() => { feedbackModal.hide(); }, 2500);
<?php endif; ?>
</script>
<?php if (!empty($_SESSION['history_flatten'])): ?>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, '', window.location.pathname + window.location.search);
    }
</script>
<?php unset($_SESSION['history_flatten']); endif; ?>
</body>
</html>