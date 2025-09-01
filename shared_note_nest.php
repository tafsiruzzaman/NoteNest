<?php
require 'includes/auth.php';
require 'includes/db.php';
$user_id = $_SESSION['user_id'];
$modal_message = '';
$current_folder_id = isset($_GET['folder']) && is_numeric($_GET['folder']) ? intval($_GET['folder']) : null;

// --- UTILS ---
function has_shared_access($conn, $folder_id, $user_id) {
    $stmt = $conn->prepare("SELECT 1 FROM shared_access WHERE item_type='folder' AND item_id=? AND shared_with_user_id=?");
    $stmt->bind_param('ii', $folder_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    $has_access = $stmt->num_rows > 0;
    $stmt->close();
    return $has_access;
}
if ($current_folder_id !== null && !has_shared_access($conn, $current_folder_id, $user_id)) {
    header("Location: shared_note_nest.php");
    exit;
}
// Handle favorite/unfavorite
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
// --- Get shared folders and files using brute-force approach ---
$folders = [];
if ($current_folder_id === null) {
    $stmt = $conn->prepare("SELECT f.id, f.name, u.name as owner_name FROM shared_access sa JOIN folders f ON sa.item_type='folder' AND sa.item_id=f.id JOIN users u ON f.owner_id=u.id WHERE sa.shared_with_user_id=? AND f.parent_folder_id IS NULL ORDER BY f.name");
    $stmt->bind_param('i', $user_id);
} else {
    $stmt = $conn->prepare("SELECT f.id, f.name, u.name as owner_name FROM shared_access sa JOIN folders f ON sa.item_type='folder' AND sa.item_id=f.id JOIN users u ON f.owner_id=u.id WHERE sa.shared_with_user_id=? AND f.parent_folder_id=? ORDER BY f.name");
    $stmt->bind_param('ii', $user_id, $current_folder_id);
}
$stmt->execute();
$stmt->bind_result($fid, $fname, $owner_name);
while ($stmt->fetch()) $folders[] = [$fid, $fname, $owner_name];
$stmt->close();
$files = [];
if ($current_folder_id === null) {
    $stmt = $conn->prepare("SELECT f.id, f.name, f.file_path, f.mime_type, f.created_at, u.name as owner_name FROM shared_access sa JOIN files f ON sa.item_type='file' AND sa.item_id=f.id JOIN users u ON f.owner_id=u.id WHERE sa.shared_with_user_id=? AND f.folder_id IS NULL ORDER BY f.created_at DESC");
    $stmt->bind_param('i', $user_id);
} else {
    $stmt = $conn->prepare("SELECT f.id, f.name, f.file_path, f.mime_type, f.created_at, u.name as owner_name FROM shared_access sa JOIN files f ON sa.item_type='file' AND sa.item_id=f.id JOIN users u ON f.owner_id=u.id WHERE sa.shared_with_user_id=? AND f.folder_id=? ORDER BY f.created_at DESC");
    $stmt->bind_param('ii', $user_id, $current_folder_id);
}
$stmt->execute();
$stmt->bind_result($fid, $fname, $fpath, $fmime, $fcreated, $owner_name);
while ($stmt->fetch()) $files[] = [$fid, $fname, $fpath, $fmime, $fcreated, $owner_name];
$stmt->close();
// --- Breadcrumbs ---
function get_shared_folder_path($conn, $folder_id, $user_id) {
    $path = [];
    while ($folder_id) {
        $stmt = $conn->prepare("SELECT f.id, f.name, f.parent_folder_id FROM shared_access sa JOIN folders f ON sa.item_type='folder' AND sa.item_id=f.id WHERE sa.shared_with_user_id=? AND f.id=?");
        $stmt->bind_param('ii', $user_id, $folder_id);
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
$breadcrumbs = get_shared_folder_path($conn, $current_folder_id, $user_id);
// Get favorites for this user
$fav_ids = ['file'=>[], 'folder'=>[]];
$res = $conn->query("SELECT item_type, item_id FROM favorites WHERE user_id=$user_id");
while ($row = $res->fetch_assoc()) $fav_ids[$row['item_type']][] = $row['item_id'];
if (isset($_SESSION['success_msg'])) {
    $modal_message = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if ($modal_message) {
    echo "<script>if (history.replaceState) history.replaceState(null, '', window.location.pathname + window.location.search);</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Shared NoteNest - NoteNest</title>
  <link rel="shortcut icon" href="img/fav.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/shared_note_nest.css">
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
              <a href="shared_note_nest.php"><i class="fas fa-house"></i> Shared Root</a>
            <?php else: ?>
              <span><i class="fas fa-house"></i> Shared Root</span>
            <?php endif; ?>
          </li>
          <?php foreach ($breadcrumbs as $i=>$bc): ?>
              <li class="breadcrumb-item <?= $i==count($breadcrumbs)-1 ? 'active' : '' ?>">
                <?php if ($i==count($breadcrumbs)-1): ?>
                  <?= htmlspecialchars($bc['name']) ?>
                <?php else: ?>
                  <a href="shared_note_nest.php?folder=<?= $bc['id'] ?>"><?= htmlspecialchars($bc['name']) ?></a>
                <?php endif; ?>
              </li>
          <?php endforeach; ?>
        </ol>
      </nav>
      <!-- INFO CARD -->
      <div class="card mb-3">
        <div class="card-header">
          <i class="fas fa-info-circle me-2"></i>Shared Items
        </div>
        <div class="card-body">
          <p class="mb-2"><strong>Note:</strong> These items have been shared with you.</p>
          <p class="mb-0 text-muted small">You can preview, download, and add to favorites.</p>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <!-- FOLDERS HEADING -->
      <div class="section-heading mb-2">
        <i class="fas fa-folder-open"></i> Shared Folders
      </div>
      <!-- SUBFOLDER LIST -->
      <?php if(empty($folders)): ?>
        <p class="text-muted">No shared folders here.</p>
      <?php else: ?>
        <ul class="list-group folder-list-group mb-3">
          <?php foreach($folders as $f): ?>
            <li class="list-group-item d-flex align-items-center justify-content-between">
              <div>
                <a href="shared_note_nest.php?folder=<?= $f[0] ?>" class="folder-link">
                  <i class="fa fa-folder folder-icon"></i><?= htmlspecialchars($f[1]) ?>
                </a>
                <small class="text-muted d-block">Owner: <?= htmlspecialchars($f[2]) ?></small>
              </div>
              <div>
                <a href="#" class="btn btn-sm btn-outline-warning me-1 favorite-btn" data-type="folder" data-id="<?= $f[0] ?>" data-fav="<?= in_array($f[0], $fav_ids['folder']) ? 1 : 0 ?>" title="Favorite">
                  <i class="fa<?= in_array($f[0], $fav_ids['folder']) ? 's' : 'r' ?> fa-star"></i>
                </a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <!-- NOTES HEADING -->
      <div class="section-heading mb-2" style="margin-top:32px;">
        <i class="fas fa-note-sticky"></i> Shared Notes<?= ($current_folder_id !== null)? ' in "' . htmlspecialchars(end($breadcrumbs)['name']) . '"' : '' ?>
      </div>
      <div class="card">
        <div class="card-body table-responsive">
          <?php if(empty($files)): ?>
            <div class="alert alert-secondary text-muted mb-0">No shared notes in this folder.</div>
          <?php else: ?>
            <table class="table align-middle table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Note Name</th>
                  <th>Owner</th>
                  <th>Uploaded</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($files as $index=>$n): ?>
                <tr>
                  <th><?= $index+1 ?></th>
                  <td><?= htmlspecialchars($n[1]) ?></td>
                  <td><small class="text-muted"><?= htmlspecialchars($n[5]) ?></small></td>
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
                    <a href="#" class="btn btn-sm btn-outline-warning me-1 favorite-btn" data-type="file" data-id="<?= $n[0] ?>" data-fav="<?= in_array($n[0], $fav_ids['file']) ? 1 : 0 ?>" title="Favorite">
                      <i class="fa<?= in_array($n[0], $fav_ids['file']) ? 's' : 'r' ?> fa-star"></i>
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
</script>
</body>
</html>
