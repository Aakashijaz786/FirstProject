<?php
session_start();
include '../includes/config.php';

// Check if admin is logged in (you may need to adjust this based on your authentication system)
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $delete_query = "DELETE FROM contact_messages WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $success_message = "Message deleted successfully!";
    } else {
        $error_message = "Error deleting message!";
    }
    $stmt->close();
}

// Handle status update
if (isset($_GET['update_status']) && is_numeric($_GET['update_status']) && isset($_GET['status'])) {
    $id = (int)$_GET['update_status'];
    $status = $_GET['status'];
    
    // Validate status
    $valid_statuses = ['new', 'read', 'replied', 'closed'];
    if (in_array($status, $valid_statuses)) {
        $update_query = "UPDATE contact_messages SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            $success_message = "Status updated successfully!";
        } else {
            $error_message = "Error updating status!";
        }
        $stmt->close();
    }
}

// Fetch contact messages
$query = "SELECT * FROM contact_messages ORDER BY created_at DESC";
$result = $conn->query($query);

include 'includes/header.php';
?>
<style>
    .card .card-body {
    display: block;
    align-items: center;
}
</style>
<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Contact Messages</h2>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-envelope"></i> Contact Messages
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Email</th>
                                            <th>Message</th>
                                            <th>Status</th>
                                            <th>IP Address</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="<?php echo $row['status'] === 'new' ? 'table-warning' : ''; ?>">
                                                <td><?php echo $row['id']; ?></td>
                                                <td>
                                                    <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>">
                                                        <?php echo htmlspecialchars($row['email']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#messageModal<?php echo $row['id']; ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $row['status'] === 'new' ? 'warning' : 
                                                            ($row['status'] === 'read' ? 'info' : 
                                                            ($row['status'] === 'replied' ? 'success' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($row['ip_address'] ?? 'N/A'); ?></small>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                            Status
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="?update_status=<?php echo $row['id']; ?>&status=new">Mark as New</a></li>
                                                            <li><a class="dropdown-item" href="?update_status=<?php echo $row['id']; ?>&status=read">Mark as Read</a></li>
                                                            <li><a class="dropdown-item" href="?update_status=<?php echo $row['id']; ?>&status=replied">Mark as Replied</a></li>
                                                            <li><a class="dropdown-item" href="?update_status=<?php echo $row['id']; ?>&status=closed">Mark as Closed</a></li>
                                                        </ul>
                                                    </div>
                                                    <a href="?delete=<?php echo $row['id']; ?>" 
                                                       class="btn btn-sm btn-danger ms-1"
                                                       onclick="return confirm('Are you sure you want to delete this message?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            
                                            <!-- Message Modal -->
                                            <div class="modal fade" id="messageModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="messageModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="messageModalLabel<?php echo $row['id']; ?>">
                                                                Message from <?php echo htmlspecialchars($row['email']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <strong>From:</strong> 
                                                                    <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>">
                                                                        <?php echo htmlspecialchars($row['email']); ?>
                                                                    </a>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <strong>Status:</strong> 
                                                                    <span class="badge bg-<?php 
                                                                        echo $row['status'] === 'new' ? 'warning' : 
                                                                            ($row['status'] === 'read' ? 'info' : 
                                                                            ($row['status'] === 'replied' ? 'success' : 'secondary')); 
                                                                    ?>">
                                                                        <?php echo ucfirst($row['status']); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <strong>IP Address:</strong> <?php echo htmlspecialchars($row['ip_address'] ?? 'N/A'); ?>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <strong>Date:</strong> <?php echo date('F d, Y \a\t H:i A', strtotime($row['created_at'])); ?>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-3">
                                                                <div class="col-12">
                                                                    <strong>Message:</strong>
                                                                    <div class="mt-2 p-3 bg-light rounded">
                                                                         <?php echo htmlspecialchars($row['message']); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty($row['user_agent'])): ?>
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <strong>User Agent:</strong>
                                                                    <div class="mt-2 p-2 bg-light rounded">
                                                                        <small><?php echo htmlspecialchars($row['user_agent']); ?></small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" class="btn btn-primary">
                                                                <i class="fas fa-reply"></i> Reply
                                                            </a>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No contact messages found</h5>
                                <p class="text-muted">When users submit contact forms, their messages will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>