<?php
require 'includes/auth.php';
require 'includes/db.php';
require_once 'includes/functions.php';
$user_id = $_SESSION['user_id'];
$modal_message = '';

// Handle favorite/unfavorite
if (isset($_POST['favorite_item'])) {
    $item_type = $_POST['item_type'];
    $item_id = intval($_POST['item_id']);
    $is_fav = isset($_POST['is_fav']) ? 1 : 0;
    if ($is_fav) {
        $stmt = $conn->prepare('DELETE FROM favorites WHERE user_id=? AND item_type=? AND item_id=?');
        $stmt->bind_param('isi', $user_id, $item_type, $item_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT IGNORE INTO favorites (user_id, item_type, item_id) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $user_id, $item_type, $item_id);
        $stmt->execute();
        $stmt->close();
    }
    exit('ok');
}

// Get favorites for this user
$fav_ids = ['file'=>[], 'folder'=>[]];
$res = $conn->query("SELECT item_type, item_id FROM favorites WHERE user_id=$user_id");
while ($row = $res->fetch_assoc()) {
    $fav_ids[$row['item_type']][] = $row['item_id'];
}

// Get favorite folders with owner info
$favorite_folders = [];
if (!empty($fav_ids['folder'])) {
    $folder_ids = implode(',', $fav_ids['folder']);
    $stmt = $conn->prepare("
        SELECT f.id, f.name, f.created_at, u.name as owner_name, 
               CASE WHEN f.owner_id = ? THEN 'own' ELSE 'shared' END as access_type
        FROM folders f 
        JOIN users u ON f.owner_id = u.id
        WHERE f.id IN ($folder_ids)
        AND (f.owner_id = ? OR EXISTS (
            SELECT 1 FROM shared_access sa 
            WHERE sa.item_type='folder' AND sa.item_id=f.id AND sa.shared_with_user_id=?
        ))
        ORDER BY f.created_at DESC
    ");
    $stmt->bind_param('iii', $user_id, $user_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($fid, $fname, $fcreated, $owner_name, $access_type);
    while ($stmt->fetch()) {
        $favorite_folders[] = [$fid, $fname, $fcreated, $owner_name, $access_type];
    }
    $stmt->close();
}

// Get favorite files with owner info
$favorite_files = [];
if (!empty($fav_ids['file'])) {
    $file_ids = implode(',', $fav_ids['file']);
    $stmt = $conn->prepare("
        SELECT f.id, f.name, f.file_path, f.mime_type, f.created_at, u.name as owner_name,
               CASE WHEN f.owner_id = ? THEN 'own' ELSE 'shared' END as access_type
        FROM files f 
        JOIN users u ON f.owner_id = u.id
        WHERE f.id IN ($file_ids)
        AND (f.owner_id = ? OR EXISTS (
            SELECT 1 FROM shared_access sa 
            WHERE sa.item_type='file' AND sa.item_id=f.id AND sa.shared_with_user_id=?
        ))
        ORDER BY f.created_at DESC
    ");
    $stmt->bind_param('iii', $user_id, $user_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($fid, $fname, $fpath, $fmime, $fcreated, $owner_name, $access_type);
    while ($stmt->fetch()) {
        $favorite_files[] = [$fid, $fname, $fpath, $fmime, $fcreated, $owner_name, $access_type];
    }
    $stmt->close();
}

if (isset($_SESSION['success_msg'])) {
    $modal_message = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if ($modal_message) {
    echo "<script>if (history.replaceState) history.replaceState(null, '', window.location.pathname);</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Favorites - NoteNest</title>
  <link rel="shortcut icon" href="img/fav.ico" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="css/favorites.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-md-4">
      <!-- INFO CARD -->
      <div class="card mb-3">
        <div class="card-header">
          <i class="fas fa-star me-2"></i>My Favorites
        </div>
        <div class="card-body">
          <p class="mb-2"><strong>Favorite Folders:</strong> <?= count($favorite_folders) ?></p>
          <p class="mb-0"><strong>Favorite Files:</strong> <?= count($favorite_files) ?></p>
        </div>
      </div>
    </div>
    
    <div class="col-md-8">
      <!-- FAVORITE FOLDERS HEADING -->
      <div class="section-heading mb-2">
        <i class="fas fa-folder-open"></i> Favorite Folders
      </div>
      
      <!-- FAVORITE FOLDER LIST -->
      <?php if(empty($favorite_folders)): ?>
        <p class="text-muted">No favorite folders yet.</p>
      <?php else: ?>
        <ul class="list-group folder-list-group mb-3">
          <?php foreach($favorite_folders as $f): ?>
            <li class="list-group-item d-flex align-items-center justify-content-between">
              <div>
                <?php if ($f[4] === 'own'): ?>
                  <a href="my_note_nest.php?folder=<?= $f[0] ?>" class="folder-link">
                    <i class="fa fa-folder folder-icon"></i><?= htmlspecialchars($f[1]) ?>
                  </a>
                <?php else: ?>
                  <a href="shared_note_nest.php?folder=<?= $f[0] ?>" class="folder-link">
                    <i class="fa fa-folder folder-icon"></i><?= htmlspecialchars($f[1]) ?>
                  </a>
                <?php endif; ?>
                <small class="text-muted d-block">
                  <?= $f[4] === 'own' ? 'My folder' : 'Shared by: ' . htmlspecialchars($f[3]) ?>
                  <span class="badge bg-<?= $f[4] === 'own' ? 'primary' : 'info' ?>">
                    <?= $f[4] === 'own' ? 'Owner' : 'View Only' ?>
                  </span>
                </small>
                <small class="text-muted">Created: <?= date('d M Y, H:i', strtotime($f[2])) ?></small>
              </div>
              <div>
                <a href="#" class="btn btn-sm btn-outline-warning me-1 favorite-btn" data-type="folder" data-id="<?= $f[0] ?>" data-fav="1" title="Remove from Favorites">
                  <i class="fas fa-star"></i>
                </a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      
      <!-- FAVORITE FILES HEADING -->
      <div class="section-heading mb-2" style="margin-top:32px;">
        <i class="fas fa-note-sticky"></i> Favorite Files
      </div>
      
      <div class="card">
        <div class="card-body table-responsive">
          <?php if(empty($favorite_files)): ?>
            <div class="alert alert-secondary text-muted mb-0">No favorite files yet.</div>
          <?php else: ?>
            <table class="table align-middle table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>File Name</th>
                  <th>Type</th>
                  <th><?= $f[4] === 'own' ? 'My file' : 'Shared By' ?></th>
                  <th>Created Date</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($favorite_files as $index=>$n): ?>
                  <tr>
                    <th><?= $index+1 ?></th>
                    <td><?= htmlspecialchars($n[1]) ?></td>
                    <td>
                      <?php if (strpos($n[3], 'image/') === 0): ?>
                        <span class="badge bg-success">Image</span>
                      <?php elseif (strpos($n[3], 'text/') === 0): ?>
                        <span class="badge bg-info">Text</span>
                      <?php elseif (strpos($n[3], 'application/pdf') === 0): ?>
                        <span class="badge bg-danger">PDF</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">File</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($n[5] === 'own'): ?>
                        <span class="text-primary">My file</span>
                      <?php else: ?>
                        <?= htmlspecialchars($n[4]) ?>
                      <?php endif; ?>
                    </td>
                    <td><?= date('d M Y, H:i', strtotime($n[4])) ?></td>
                    <td class="text-end">
                      <?php if ($n[5] === 'own'): ?>
                        <a href="note_download.php?id=<?= $n[0] ?>" title="Download" class="btn btn-sm btn-outline-secondary me-1">
                          <i class="fas fa-download"></i>
                        </a>
                      <?php else: ?>
                        <a href="note_download.php?id=<?= $n[0] ?>" title="Download" class="btn btn-sm btn-outline-secondary me-1">
                          <i class="fas fa-download"></i>
                        </a>
                      <?php endif; ?>
                      
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
                      
                      <a href="#" class="btn btn-sm btn-outline-warning me-1 favorite-btn" data-type="file" data-id="<?= $n[0] ?>" data-fav="1" title="Remove from Favorites">
                        <i class="fas fa-star"></i>
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
        <h5 class="modal-title" id="previewLabel">File Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="previewContent"></div>
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
        let previewContent = document.getElementById('previewContent');
        
        if (type === 'image') {
            previewContent.innerHTML = '<img src="' + file + '" style="max-width:100%;max-height:60vh;">';
        } else if (type === 'text') {
            fetch('note_preview.php?file=' + encodeURIComponent(file))
              .then(r=>r.text())
              .then(text => {
                  previewContent.innerHTML = '<pre style="font-family:inherit;max-height:60vh;overflow-y:auto;">' + text + '</pre>';
              });
        } else if (type === 'pdf') {
            previewContent.innerHTML = '<iframe src="' + file + '" style="width:100%;height:60vh;" frameborder="0"></iframe>';
        }
        
        document.getElementById('previewLabel').textContent = name + " (Preview)";
        let modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    });
});

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

<?php if ($modal_message): ?>
    const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    window.addEventListener('DOMContentLoaded', function() { feedbackModal.show(); });
    document.getElementById('feedbackModal').addEventListener('hidden.bs.modal', function () {
        window.location.replace(window.location.pathname);
    });
    setTimeout(() => { feedbackModal.hide(); }, 2500);
<?php endif; ?>
</script>
</body>
</html>
