<?php
session_start();

// Block access if not logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php"; // session timer
require "config.php"; // establish connection with database

// Read filter values from the URL (if any were submitted)
$filter_date = $_GET['filter_date'] ?? '';
$filter_room = $_GET['filter_room'] ?? '';

// Build query dynamically based on filters
$sql = "SELECT * FROM bookings WHERE 1=1";
$params = [];
$types = "";

if ($filter_date !== '') {
    $sql .= " AND booking_date = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if ($filter_room !== '') {
    $sql .= " AND room = ?";
    $params[] = $filter_room;
    $types .= "s";
}

$sql .= " ORDER BY booking_date ASC, start_time ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Bookings - SALAM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <?php $is_admin = ($_SESSION['role'] === 'admin'); ?>

    <div class="container">
        <h2><?php echo $is_admin ? 'All Bookings (Admin)' : 'All Bookings'; ?></h2>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'cancelled'): ?>
                <div class="success">Booking cancelled successfully.</div>
            <?php elseif ($_GET['msg'] === 'updated'): ?>
                <div class="success">Booking updated successfully.</div>
            <?php elseif ($_GET['msg'] === 'error'): ?>
                <div class="error">Something went wrong. Please try again.</div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Filter bar -->
        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Date</label>
                <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
            </div>

            <div class="filter-group">
                <label>Room</label>
                <select name="filter_room">
                    <option value="">All Rooms</option>
                    <option value="Bilik Meeting" <?php if ($filter_room === 'Bilik Meeting') echo 'selected'; ?>>Meeting</option>
                    <option value="Bilik Seminar" <?php if ($filter_room === 'Bilik Seminar') echo 'selected'; ?>>Seminar</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit">Filter</button>
                <a href="view_bookings.php" class="clear-link">Clear</a>
            </div>
        </form>

        <p class="result-count">
            <?php echo $result->num_rows; ?> booking<?php echo $result->num_rows === 1 ? '' : 's'; ?> found
        </p>

        <?php if ($result->num_rows > 0): ?>
            <div class="table-scroll-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Purpose</th>
                        <th>Booked By</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Room</th>
                        <?php if ($is_admin): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($row['booked_by'] ?? ''); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['booking_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($row['start_time'])) . " – " . date('g:i A', strtotime($row['end_time'])); ?></td>
                            <td><span class="room-tag"><?php echo htmlspecialchars(room_label($row['room'])); ?></span></td>
                            <?php if ($is_admin): ?>
                                <td class="actions">
                                    <a href="edit_booking.php?id=<?php echo $row['id']; ?>&from=view" class="action-btn edit-btn">Edit</a>
                                    <a href="cancel_booking.php?id=<?php echo $row['id']; ?>&from=view" class="action-btn cancel-btn" onclick="return confirm('Cancel this booking?');">Cancel</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No bookings match your filters.</p>
                <a href="view_bookings.php" class="btn">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <?php $stmt->close(); $conn->close(); ?>
</body>
</html>