<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php"; // session timer
require "config.php"; // establish connection with database

$staff_id = $_SESSION['staff_id'];

$sql = "SELECT * FROM bookings WHERE staff_id = ? ORDER BY booking_date ASC, start_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Bookings - SALAM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="container">
        <h2>My Bookings</h2>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'cancelled'): ?>
                <div class="success">Booking cancelled successfully.</div>
            <?php elseif ($_GET['msg'] === 'updated'): ?>
                <div class="success">Booking updated successfully.</div>
            <?php elseif ($_GET['msg'] === 'error'): ?>
                <div class="error">Something went wrong. Please try again.</div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($result->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Purpose</th>
                    <th>Booked By</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th>Actions</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($row['booked_by'] ?? ''); ?></td>
                        <td><?php echo date('d M Y', strtotime($row['booking_date'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($row['start_time'])) . " – " . date('g:i A', strtotime($row['end_time'])); ?></td>
                        <td><span class="room-tag"><?php echo htmlspecialchars($row['room']); ?></span></td>
                        <td class="actions">
                            <a href="edit_booking.php?id=<?php echo $row['id']; ?>&from=my" class="action-btn edit-btn">Edit</a>
                            <a href="cancel_booking.php?id=<?php echo $row['id']; ?>&from=my" class="action-btn cancel-btn" onclick="return confirm('Cancel this booking?');">Cancel</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>You have no bookings yet.</p>
                <a href="homepage.php" class="btn">Book a Meeting Room</a>
            </div>
        <?php endif; ?>
    </div>

    <?php $stmt->close(); $conn->close(); ?>
</body>
</html>