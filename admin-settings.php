<?php
$page_title = "Settings";
$current_page = "settings";
require_once 'admin-header.php';
require_once 'config.php';
$conn = getDBConnection();
$message = '';
$message_type = '';

// Handle form submission for updating mess details
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_mess_details') {
    $mess_id = $_POST['mess_id'];
    $mess_name_new = trim($_POST['mess_name']);
    $mess_location_new = trim($_POST['mess_location']);
    $mess_contact_new = trim($_POST['mess_contact']);

    $stmt = $conn->prepare("UPDATE mess SET name = ?, location = ?, contact = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("sssi", $mess_name_new, $mess_location_new, $mess_contact_new, $mess_id);
        if ($stmt->execute()) {
            $message = "Mess details updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating mess details: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
        $message_type = "danger";
    }
}

// Fetch all mess for the dropdown and display
$mess = [];
$result = $conn->query("SELECT * FROM mess ORDER BY id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $mess[] = $row;
    }
}


// Default selected mess (first one if available)
$selected_mess_id = !empty($mess) ? $mess[0]['mess_id'] : null;
if (isset($_GET['mess_id']) && is_numeric($_GET['mess_id'])) {
    $selected_mess_id = intval($_GET['mess_id']);
}

$current_mess_details = null;
if ($selected_mess_id) {
    $stmt = $conn->prepare("SELECT * FROM mess WHERE mess_id = ?");
    $stmt->bind_param("i", $selected_mess_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $current_mess_details = $result->fetch_assoc();
    }
    $stmt->close();
}
?>

<div class="container-fluid">
    <h1 class="mt-4">Settings</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="admin-dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Settings</li>
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
            <i class="fas fa-utensils mr-1"></i>
            Manage Mess Details
        </div>
        <div class="card-body">
            <?php if (empty($mess)): ?>
                <div class="alert alert-info">No mess found. Please add a mess first in "Manage mess".</div>
            <?php else: ?>
                <form method="GET" action="admin-settings.php" class="mb-4">
                    <div class="form-group">
                        <label for="select_mess">Select Mess to Edit</label>
                        <select class="form-control" id="select_mess" name="mess_id" onchange="this.form.submit()">
                            <?php foreach ($mess as $mess): ?>
                                <option value="<?php echo htmlspecialchars($mess['id']); ?>"
                                    <?php echo ($selected_mess_id == $mess['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mess['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($current_mess_details): ?>
                    <form method="POST" action="admin-settings.php">
                        <input type="hidden" name="action" value="update_mess_details">
                        <input type="hidden" name="mess_id" value="<?php echo htmlspecialchars($current_mess_details['mess_id']); ?>">
                        <div class="form-group">
                            <label for="mess_name">Mess Name</label>
                            <input type="text" class="form-control" id="mess_name" name="mess_name" value="<?php echo htmlspecialchars($current_mess_details['mess_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="mess_location">Location</label>
                            <input type="text" class="form-control" id="mess_location" name="mess_location" value="<?php echo htmlspecialchars($current_mess_details['mess_location']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="mess_contact">Contact Info</label>
                            <input type="text" class="form-control" id="mess_contact" name="mess_contact" value="<?php echo htmlspecialchars($current_mess_details['mess_contact']); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Mess Details</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">No mess selected or details not found.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
