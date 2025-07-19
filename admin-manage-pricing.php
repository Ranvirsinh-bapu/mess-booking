<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'];
$conn = getDBConnection();

$message = '';
$message_type = ''; // 'success' or 'danger'

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_global_prices' || $action === 'update_time_slots') {
        $updates = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'price_') === 0 || strpos($key, 'time_') === 0) {
                $updates[$key] = trim($value);
            }
        }

        $conn->begin_transaction();
        try {
            foreach ($updates as $key => $value) {
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->bind_param("sss", $key, $value, $value);
                $stmt->execute();
            }
            $conn->commit();
            $message = 'Settings updated successfully!';
            $message_type = 'success';
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = 'Error updating settings: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } elseif ($action === 'add_special_price' || $action === 'edit_special_price') {
        $date = trim($_POST['date']);
        $meal_type = trim($_POST['meal_type']);
        $price = floatval($_POST['price']);

        if (empty($date) || empty($meal_type) || $price <= 0) {
            $message = 'Date, Meal Type, and Price are required for special pricing.';
            $message_type = 'danger';
        } else {
            if ($action === 'add_special_price') {
                $stmt = $conn->prepare("INSERT INTO special_pricing (date, meal_type, price) VALUES (?, ?, ?)");
                $stmt->bind_param("ssd", $date, $meal_type, $price);
                if ($stmt->execute()) {
                    $message = 'Special price added successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error adding special price (might already exist for this date/meal type): ' . $conn->error;
                    $message_type = 'danger';
                }
            } elseif ($action === 'edit_special_price') {
                $special_price_id = intval($_POST['special_price_id']);
                $stmt = $conn->prepare("UPDATE special_pricing SET date = ?, meal_type = ?, price = ? WHERE id = ?");
                $stmt->bind_param("ssdi", $date, $meal_type, $price, $special_price_id);
                if ($stmt->execute()) {
                    $message = 'Special price updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating special price: ' . $conn->error;
                    $message_type = 'danger';
                }
            }
        }
    } elseif ($action === 'delete_special_price') {
        $special_price_id = intval($_POST['special_price_id']);
        $stmt = $conn->prepare("DELETE FROM special_pricing WHERE id = ?");
        $stmt->bind_param("i", $special_price_id);
        if ($stmt->execute()) {
            $message = 'Special price deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting special price: ' . $conn->error;
            $message_type = 'danger';
        }
    }
    // Store message in session to display after redirect
    $_SESSION['admin_message'] = ['text' => $message, 'type' => $message_type];
    header('Location: admin-manage-pricing.php');
    exit;
}

// Retrieve messages from session
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message']['text'];
    $message_type = $_SESSION['admin_message']['type'];
    unset($_SESSION['admin_message']);
}

// --- Fetch Data for Display ---
// Fetch all settings
$settings_data = [];
$settings_result = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $settings_result->fetch_assoc()) {
    $settings_data[$row['setting_key']] = $row['setting_value'];
}

// Fetch all special pricing
$special_pricing_list_query = $conn->query("SELECT * FROM special_pricing ORDER BY date DESC, meal_type ASC");

$conn->close();

// Helper to get setting value for display
function getSetting($key, $default = '') {
    global $settings_data;
    return htmlspecialchars($settings_data[$key] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pricing & Time - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f6;
        }
        .navbar {
            background-color: #2c3e50;
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #34495e;
            padding-top: 56px; /* Height of navbar */
            color: white;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 15px 20px;
            transition: background-color 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #2c3e50;
            color: white;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        .card-dashboard {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">PU Mess Admin</a>
            <div class="d-flex">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user-shield"></i> Welcome, <?php echo htmlspecialchars($admin_username); ?>
                </span>
                <a href="logout.php?type=admin" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="sidebar">
        <div class="d-flex flex-column p-3">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="admin-dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link text-white">
                        <i class="fas fa-book me-2"></i> Manage Bookings
                    </a>
                </li>
                <li>
                    <a href="admin-manage-messes.php" class="nav-link text-white">
                        <i class="fas fa-utensils me-2"></i> Manage Messes
                    </a>
                </li>
                <li>
                    <a href="admin-manage-pricing.php" class="nav-link active">
                        <i class="fas fa-dollar-sign me-2"></i> Manage Pricing
                    </a>
                </li>
                <li>
                    <a href="admin-manage-staff.php" class="nav-link text-white">
                        <i class="fas fa-users-cog me-2"></i> Manage Staff
                    </a>
                </li>
                <li>
                    <a href="#" class="nav-link text-white">
                        <i class="fas fa-cogs me-2"></i> Settings
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="content">
        <h1 class="mb-4">Manage Pricing & Time Slots</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Global Pricing Settings -->
        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white">
                <h4 class="mb-0">Global Coupon Prices</h4>
            </div>
            <div class="card-body">
                <form action="admin-manage-pricing.php" method="post">
                    <input type="hidden" name="action" value="update_global_prices">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="price_in_campus_monthly" class="form-label">In Campus Monthly (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="price_in_campus_monthly" name="price_in_campus_monthly" value="<?php echo getSetting('price_in_campus_monthly'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="price_out_campus_monthly" class="form-label">Out Campus Monthly (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="price_out_campus_monthly" name="price_out_campus_monthly" value="<?php echo getSetting('price_out_campus_monthly'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="price_breakfast" class="form-label">Breakfast (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="price_breakfast" name="price_breakfast" value="<?php echo getSetting('price_breakfast'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="price_lunch_weekday" class="form-label">Weekday Lunch (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="price_lunch_weekday" name="price_lunch_weekday" value="<?php echo getSetting('price_lunch_weekday'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="price_dinner_weekday" class="form-label">Weekday Dinner (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="price_dinner_weekday" name="price_dinner_weekday" value="<?php echo getSetting('price_dinner_weekday'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="price_lunch_sunday" class="form-label">Sunday Lunch (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="price_lunch_sunday" name="price_lunch_sunday" value="<?php echo getSetting('price_lunch_sunday'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="price_dinner_sunday" class="form-label">Sunday Dinner (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="price_dinner_sunday" name="price_dinner_sunday" value="<?php echo getSetting('price_dinner_sunday'); ?>" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Update Prices</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Global Time Slot Settings -->
        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white">
                <h4 class="mb-0">Global Meal Time Slots</h4>
            </div>
            <div class="card-body">
                <form action="admin-manage-pricing.php" method="post">
                    <input type="hidden" name="action" value="update_time_slots">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="time_breakfast_start" class="form-label">Breakfast Start Time</label>
                            <input type="time" class="form-control" id="time_breakfast_start" name="time_breakfast_start" value="<?php echo getSetting('time_breakfast_start'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_breakfast_end" class="form-label">Breakfast End Time</label>
                            <input type="time" class="form-control" id="time_breakfast_end" name="time_breakfast_end" value="<?php echo getSetting('time_breakfast_end'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_lunch_weekday_start" class="form-label">Weekday Lunch Start Time</label>
                            <input type="time" class="form-control" id="time_lunch_weekday_start" name="time_lunch_weekday_start" value="<?php echo getSetting('time_lunch_weekday_start'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_lunch_weekday_end" class="form-label">Weekday Lunch End Time</label>
                            <input type="time" class="form-control" id="time_lunch_weekday_end" name="time_lunch_weekday_end" value="<?php echo getSetting('time_lunch_weekday_end'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_lunch_sunday_start" class="form-label">Sunday Lunch Start Time</label>
                            <input type="time" class="form-control" id="time_lunch_sunday_start" name="time_lunch_sunday_start" value="<?php echo getSetting('time_lunch_sunday_start'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_lunch_sunday_end" class="form-label">Sunday Lunch End Time</label>
                            <input type="time" class="form-control" id="time_lunch_sunday_end" name="time_lunch_sunday_end" value="<?php echo getSetting('time_lunch_sunday_end'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_dinner_weekday_start" class="form-label">Weekday Dinner Start Time</label>
                            <input type="time" class="form-control" id="time_dinner_weekday_start" name="time_dinner_weekday_start" value="<?php echo getSetting('time_dinner_weekday_start'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_dinner_weekday_end" class="form-label">Weekday Dinner End Time</label>
                            <input type="time" class="form-control" id="time_dinner_weekday_end" name="time_dinner_weekday_end" value="<?php echo getSetting('time_dinner_weekday_end'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_dinner_sunday_start" class="form-label">Sunday Dinner Start Time</label>
                            <input type="time" class="form-control" id="time_dinner_sunday_start" name="time_dinner_sunday_start" value="<?php echo getSetting('time_dinner_sunday_start'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_dinner_sunday_end" class="form-label">Sunday Dinner End Time</label>
                            <input type="time" class="form-control" id="time_dinner_sunday_end" name="time_dinner_sunday_end" value="<?php echo getSetting('time_dinner_sunday_end'); ?>" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Update Time Slots</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Special Pricing Management -->
        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Special Pricing Overrides</h4>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addSpecialPriceModal">
                    <i class="fas fa-plus-circle me-2"></i> Add Special Price
                </button>
            </div>
            <div class="card-body">
                <?php if ($special_pricing_list_query->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Meal Type</th>
                                <th>Price (₹)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($price = $special_pricing_list_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($price['id']); ?></td>
                                <td><?php echo htmlspecialchars($price['date']); ?></td>
                                <td><?php echo htmlspecialchars($price['meal_type']); ?></td>
                                <td><?php echo number_format($price['price'], 2); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info me-2" 
                                            data-bs-toggle="modal" data-bs-target="#editSpecialPriceModal"
                                            data-id="<?php echo $price['id']; ?>"
                                            data-date="<?php echo htmlspecialchars($price['date']); ?>"
                                            data-mealtype="<?php echo htmlspecialchars($price['meal_type']); ?>"
                                            data-price="<?php echo htmlspecialchars($price['price']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form action="admin-manage-pricing.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this special price?');">
                                        <input type="hidden" name="action" value="delete_special_price">
                                        <input type="hidden" name="special_price_id" value="<?php echo $price['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">No special pricing overrides found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Special Price Modal -->
    <div class="modal fade" id="addSpecialPriceModal" tabindex="-1" aria-labelledby="addSpecialPriceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="admin-manage-pricing.php" method="post">
                    <input type="hidden" name="action" value="add_special_price">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSpecialPriceModalLabel">Add New Special Price</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="addDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="addDate" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="addMealType" class="form-label">Meal Type</label>
                            <select class="form-select" id="addMealType" name="meal_type" required>
                                <option value="">-- Select Meal Type --</option>
                                <option value="breakfast">Breakfast</option>
                                <option value="lunch_weekday">Lunch (Weekday)</option>
                                <option value="dinner_weekday">Dinner (Weekday)</option>
                                <option value="lunch_sunday">Lunch (Sunday)</option>
                                <option value="dinner_sunday">Dinner (Sunday)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="addPrice" class="form-label">Price (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="addPrice" name="price" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Special Price</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Special Price Modal -->
    <div class="modal fade" id="editSpecialPriceModal" tabindex="-1" aria-labelledby="editSpecialPriceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="admin-manage-pricing.php" method="post">
                    <input type="hidden" name="action" value="edit_special_price">
                    <input type="hidden" name="special_price_id" id="editSpecialPriceId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSpecialPriceModalLabel">Edit Special Price</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="editDate" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="editMealType" class="form-label">Meal Type</label>
                            <select class="form-select" id="editMealType" name="meal_type" required>
                                <option value="">-- Select Meal Type --</option>
                                <option value="breakfast">Breakfast</option>
                                <option value="lunch_weekday">Lunch (Weekday)</option>
                                <option value="dinner_weekday">Dinner (Weekday)</option>
                                <option value="lunch_sunday">Lunch (Sunday)</option>
                                <option value="dinner_sunday">Dinner (Sunday)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editPrice" class="form-label">Price (₹)</label>
                            <input type="number" step="0.01" class="form-control" id="editPrice" name="price" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Populate edit special price modal
        var editSpecialPriceModal = document.getElementById('editSpecialPriceModal');
        editSpecialPriceModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var id = button.getAttribute('data-id');
            var date = button.getAttribute('data-date');
            var mealType = button.getAttribute('data-mealtype');
            var price = button.getAttribute('data-price');

            var modalIdInput = editSpecialPriceModal.querySelector('#editSpecialPriceId');
            var modalDateInput = editSpecialPriceModal.querySelector('#editDate');
            var modalMealTypeSelect = editSpecialPriceModal.querySelector('#editMealType');
            var modalPriceInput = editSpecialPriceModal.querySelector('#editPrice');

            modalIdInput.value = id;
            modalDateInput.value = date;
            modalMealTypeSelect.value = mealType;
            modalPriceInput.value = price;
        });
    </script>
</body>
</html>
