
<?php
include 'includes/header.php';
include '../includes/config.php';
?>

<?php
// Handle form submission
if ($_POST) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
            $ad_script = $conn->real_escape_string($_POST['ad_script']);
            
            if ($_POST['action'] == 'add') {
                $sql = "INSERT INTO google_adsense (ad_script) VALUES (?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $ad_script);
            } else {
                $id = (int)$_POST['id'];
                $sql = "UPDATE google_adsense SET ad_script=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $ad_script, $id);
            }
            
            if ($stmt->execute()) {
                $success_message = "Ad " . ($_POST['action'] == 'add' ? 'added' : 'updated') . " successfully!";
            } else {
                $error_message = "Error: " . $stmt->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            $sql = "DELETE FROM google_adsense WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = "Ad deleted successfully!";
            } else {
                $error_message = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Get ads for editing
$edit_ad = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM google_adsense WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_ad = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all ads
$ads = [];
$result = $conn->query("SELECT * FROM google_adsense ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ads[] = $row;
    }
}
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-3">
                    <i class="fas fa-ad"></i> Google AdSense Management
                </h2>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add/Edit Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php echo $edit_ad ? 'Edit Ad' : 'Add New Ad'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_ad ? 'edit' : 'add'; ?>">
                            <?php if ($edit_ad): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_ad['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="ad_script" class="form-label">AdSense Script *</label>
                                <textarea class="form-control" id="ad_script" name="ad_script" rows="8" required 
                                          placeholder="Paste your Google AdSense script here..."><?php echo $edit_ad ? htmlspecialchars($edit_ad['ad_script']) : ''; ?></textarea>
                                <div class="form-text">
                                    Paste the complete AdSense script from your Google AdSense account. 
                                    This should include the &lt;script&gt; tags and all necessary code.
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 
                                    <?php echo $edit_ad ? 'Update Ad' : 'Add Ad'; ?>
                                </button>
                                
                                <?php if ($edit_ad): ?>
                                    <a href="google_adsense.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ads List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Manage Ads</h5>
                    </div>
                    <div class="card-body" style="display: block;">
                        <?php if (empty($ads)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-ad fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No ads found. Add your first AdSense ad above.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Script Preview</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ads as $ad): ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?php echo $ad['id']; ?></strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php 
                                                        $script_preview = htmlspecialchars(substr($ad['ad_script'], 0, 100));
                                                        echo $script_preview . (strlen($ad['ad_script']) > 100 ? '...' : '');
                                                        ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($ad['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="?edit=<?php echo $ad['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <!-- <button type="button" class="btn btn-outline-info" title="Preview Script" 
                                                                onclick="previewScript('<?php echo htmlspecialchars(addslashes($ad['ad_script'])); ?>')">
                                                            <i class="fas fa-eye"></i>
                                                        </button> -->
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this ad?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo $ad['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script Preview Modal -->
<div class="modal fade" id="scriptPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ad Script Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="scriptPreviewContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function previewScript(script) {
    document.getElementById('scriptPreviewContent').innerHTML = script;
    new bootstrap.Modal(document.getElementById('scriptPreviewModal')).show();
}

// Auto-resize textarea
document.getElementById('ad_script').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});
</script>

<?php
include 'includes/footer.php';
?>