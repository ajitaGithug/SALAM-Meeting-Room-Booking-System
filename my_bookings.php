<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php"; // session timer
require_once "config.php"; // establish connection with database

$staff_id = $_SESSION['staff_id'];

$sql = "SELECT * FROM bookings WHERE staff_id = ? ORDER BY booking_date ASC, start_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$today = date('Y-m-d');
$now_ts = time();

// Split into upcoming (still to finish) and past (already ended)
$upcoming = [];
$past = [];
while ($row = $result->fetch_assoc()) {
    if (strtotime($row['booking_date'] . ' ' . $row['end_time']) > $now_ts) {
        $upcoming[] = $row;
    } else {
        $past[] = $row;
    }
}
$past = array_reverse($past); // most recent first

// One table row. Staff can only edit/cancel bookings that haven't ended yet.
function my_booking_row($row, $show_actions, $today) {
    $date_label = date('d M Y', strtotime($row['booking_date']));
    ?>
    <tr<?php echo $row['booking_date'] === $today ? ' class="row-today"' : ''; ?>>
        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
        <td><?php echo htmlspecialchars($row['booked_by'] ?? ''); ?></td>
        <td>
            <?php echo date('D', strtotime($row['booking_date'])) . ', ' . $date_label; ?>
            <?php if ($row['booking_date'] === $today): ?><span class="today-badge">Today</span><?php endif; ?>
        </td>
        <td><?php echo date('g:i A', strtotime($row['start_time'])) . " – " . date('g:i A', strtotime($row['end_time'])); ?></td>
        <td><span class="room-tag room-<?php echo strtolower(room_label($row['room'])); ?>"><?php echo htmlspecialchars(room_label($row['room'])); ?></span></td>
        <td class="actions">
            <?php if ($show_actions): ?>
                <a href="edit_booking.php?id=<?php echo $row['id']; ?>&from=my" class="action-btn edit-btn">Edit</a>
                <form method="POST" action="cancel_booking.php" class="inline-form"
                    onsubmit="return confirm(<?php echo htmlspecialchars(json_encode('Cancel "' . $row['purpose'] . '" on ' . $date_label . '?'), ENT_QUOTES); ?>);">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                    <input type="hidden" name="from" value="my">
                    <button type="submit" class="action-btn cancel-btn">Cancel</button>
                </form>
            <?php else: ?>
                <span class="muted">Ended</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Bookings - SALAM</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

        <div class="list-toolbar">
            <p class="result-count">
                <?php echo count($upcoming); ?> upcoming booking<?php echo count($upcoming) === 1 ? '' : 's'; ?>
            </p>
            <a href="homepage.php" class="btn">+ New Booking</a>
        </div>

        <?php if (count($upcoming) > 0): ?>
            <div class="table-scroll-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Purpose</th>
                        <th>Booked By</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $row) { my_booking_row($row, true, $today); } ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>You have no upcoming bookings.</p>
                <a href="homepage.php" class="btn">Book a Meeting Room</a>
            </div>
        <?php endif; ?>

        <?php if (count($past) > 0): ?>
            <details class="past-bookings">
                <summary>Past bookings (<?php echo count($past); ?>)</summary>
                <div class="table-scroll-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Purpose</th>
                            <th>Booked By</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Room</th>
                            <th><?php echo $is_admin ? 'Actions' : 'Status'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past as $row) { my_booking_row($row, $is_admin, $today); } ?>
                    </tbody>
                </table>
                </div>
            </details>
        <?php endif; ?>
    </div>

    <?php $stmt->close(); $conn->close(); ?>
</body>
</html>