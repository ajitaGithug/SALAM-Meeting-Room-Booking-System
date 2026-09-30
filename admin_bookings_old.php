<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}

// Block non-admins from this page entirely
if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}
require "session_check.php"; // session timer
require "config.php"; //establish connection with database

$filter_date = $_GET['filter_date'] ?? '';
$filter_room = $_GET['filter_room'] ?? '';

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
    <title>Admin Panel - SALAM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'header.php'; ?>
    <h2>Admin Panel — All Bookings</h2>

    <form method="GET">
        <label>Filter by Date:</label>
        <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">

        <label>Filter by Room:</label>
        <select name="filter_room">
            <option value="">All Rooms</option>
            <option value="Meeting Room" <?php if ($filter_room === 'Meeting Room') echo 'selected'; ?>>Meeting Room</option>
            <option value="Bilik Seminar" <?php if ($filter_room === 'Bilik Seminar') echo 'selected'; ?>>Bilik Seminar</option>
        </select>

        <button type="submit">Filter</button>
        <a href="admin_bookings.php">Clear</a>
        <a href="register.php">Add New Staff Account</a>
    </form>

    <br>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Staff Name</th>
            <th>Staff ID</th>
            <th>Purpose</th>
            <th>Date</th>
            <th>Start</th>
            <th>End</th>
            <th>Room</th>
            <th>Actions</th>
        </tr>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['staff_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                    <td><?php echo htmlspecialchars($row['booking_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['start_time']); ?></td>
                    <td><?php echo htmlspecialchars($row['end_time']); ?></td>
                    <td><?php echo htmlspecialchars($row['room']); ?></td>
                    <td>
                        <a href="edit_booking.php?id=<?php echo $row['id']; ?>">Edit</a> |
                        <a href="cancel_booking.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Cancel this booking?');">Cancel</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8">No bookings found.</td></tr>
        <?php endif; ?>
    </table>

    <?php $stmt->close(); $conn->close(); ?>
</body>
</html>