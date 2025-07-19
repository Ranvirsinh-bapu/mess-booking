<?php
$page_title = "Manage Bookings";
$current_page = "manage_bookings";
require_once 'admin-header.php';
require_once 'config.php';
$conn = getDBConnection();
$message = '';
$message_type = '';

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['new_status'];

    $stmt = $conn->prepare("UPDATE bookings SET booking_status = ? WHERE booking_id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $new_status, $booking_id);
        if ($stmt->execute()) {
            $message = "Booking status updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating booking status: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
        $message_type = "danger";
    }
}

// Handle booking deletion
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $message = "Booking deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting booking: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
        $message_type = "danger";
    }
}

// Fetch all bookings with user and mess names
$bookings = [];
$sql = "SELECT b.*, s.username, m.name AS mess_name
        FROM bookings b
        JOIN staff s ON b.user_email = s.email
        JOIN mess m ON b.mess_id = m.id
        ORDER BY b.booking_date DESC, b.created_at DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}

?>

<div class="container-fluid">
    <h1 class="mt-4">Manage Bookings</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="admin-dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Manage Bookings</li>
    </ol>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table mr-1"></i>
            All Bookings
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="bookingsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Mess</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Persons</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No bookings found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['username']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['mess_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['booking_time']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['num_persons']); ?></td>
                                    <td>
                                        <?php
                                            $status_class = '';
                                            switch ($booking['booking_status']) {
                                                case 'confirmed': $status_class = 'status-badge-success'; break;
                                                case 'pending': $status_class = 'status-badge-warning'; break;
                                                case 'cancelled': $status_class = 'status-badge-danger'; break;
                                                case 'attended': $status_class = 'status-badge-primary'; break;
                                                default: $status_class = 'status-badge-info'; break;
                                            }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars(ucfirst($booking['booking_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info update-status-btn"
                                                data-id="<?php echo htmlspecialchars($booking['booking_id']); ?>"
                                                data-current_status="<?php echo htmlspecialchars($booking['booking_status']); ?>"
                                                data-toggle="modal" data-target="#updateStatusModal">
                                            <i class="fas fa-sync-alt"></i> Update Status
                                        </button>
                                        <a href="admin-manage-bookings.php?delete_id=<?php echo htmlspecialchars($booking['booking_id']); ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this booking?');">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">Update Booking Status</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="admin-manage-bookings.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" id="update_booking_id" name="booking_id">
                    <div class="form-group">
                        <label for="new_status">New Status</label>
                        <select class="form-control" id="new_status" name="new_status" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="attended">Attended</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('.update-status-btn').on('click', function() {
            var id = $(this).data('id');
            var current_status = $(this).data('current_status');

            $('#update_booking_id').val(id);
            $('#new_status').val(current_status);
        });
    });
</script>

<?php require_once 'footer.php'; ?>
