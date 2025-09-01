<?php
require 'includes/auth.php';
require 'includes/db.php';

$user_id = $_SESSION['user_id'];
$item_type = $_GET['item_type'] ?? '';
$item_id = intval($_GET['item_id'] ?? 0);

// Validate parameters
if (!in_array($item_type, ['file', 'folder']) || $item_id <= 0) {
    echo '<div class="alert alert-danger">Invalid parameters.</div>';
    exit;
}

// Validate ownership
if ($item_type === 'file') {
    $stmt = $conn->prepare("SELECT 1 FROM files WHERE id=? AND owner_id=?");
    $stmt->bind_param('ii', $item_id, $user_id);
} else {
    $stmt = $conn->prepare("SELECT 1 FROM folders WHERE id=? AND owner_id=?");
    $stmt->bind_param('ii', $item_id, $user_id);
}
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo '<div class="alert alert-danger">You do not own this item.</div>';
    exit;
}
$stmt->close();

// Get item name
$item_name = '';
if ($item_type === 'file') {
    $stmt = $conn->prepare("SELECT name FROM files WHERE id=?");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $stmt->bind_result($item_name);
    $stmt->fetch();
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT name FROM folders WHERE id=?");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $stmt->bind_result($item_name);
    $stmt->fetch();
    $stmt->close();
}

// Get users who have access to this item
$stmt = $conn->prepare("
    SELECT sa.shared_with_user_id, u.name, u.email, sa.created_at 
    FROM shared_access sa 
    JOIN users u ON sa.shared_with_user_id = u.id 
    WHERE sa.item_type = ? AND sa.item_id = ?
    ORDER BY sa.created_at DESC
");
$stmt->bind_param('si', $item_type, $item_id);
$stmt->execute();
$result = $stmt->get_result();
$shared_users = [];
while ($row = $result->fetch_assoc()) {
    $shared_users[] = $row;
}
$stmt->close();
?>

<div class="container-fluid">
    <h6 class="mb-3">
        <i class="fas fa-<?= $item_type === 'file' ? 'file' : 'folder' ?> me-2"></i>
        <?= htmlspecialchars($item_name) ?>
    </h6>
    
    <?php if (empty($shared_users)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            This <?= $item_type ?> is not shared with anyone yet.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Shared Since</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shared_users as $user): ?>
                        <tr>
                            <td>
                                <i class="fas fa-user me-2"></i>
                                <?= htmlspecialchars($user['name']) ?>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= date('d M Y, H:i', strtotime($user['created_at'])) ?></td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-danger revoke-btn"
                                        data-type="<?= $item_type ?>"
                                        data-id="<?= $item_id ?>"
                                        data-recipient="<?= $user['shared_with_user_id'] ?>"
                                        data-name="<?= htmlspecialchars($user['name']) ?>">
                                    <i class="fas fa-times"></i> Revoke
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
// Handle revoke buttons
document.querySelectorAll('.revoke-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        let type = btn.getAttribute('data-type');
        let id = btn.getAttribute('data-id');
        let recipient = btn.getAttribute('data-recipient');
        let name = btn.getAttribute('data-name');
        
        if (confirm('Are you sure you want to revoke access for ' + name + '?')) {
            let formData = new FormData();
            formData.append('revoke_share', 1);
            formData.append('item_type', type);
            formData.append('item_id', id);
            formData.append('recipient_id', recipient);
            
            fetch('my_note_nest.php', {method: 'POST', body: formData})
                .then(response => response.text())
                .then(msg => {
                    alert(msg);
                    // Reload the modal content
                    location.reload();
                });
        }
    });
});
</script>
