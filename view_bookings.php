<?php
session_start();

// Block access if not logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php"; // session timer
require_once "config.php"; // establish connection with database

// Read filter values from the URL (if any were submitted)
$filter_date = $_GET['filter_date'] ?? '';
$filter_room = $_GET['filter_room'] ?? '';
$filter_q    = trim($_GET['q'] ?? '');
$when        = $_GET['when'] ?? 'upcoming';   // upcoming | today | week | all

if (!is_string($filter_date) || !DateTime::createFromFormat('!Y-m-d', $filter_date)) $filter_date = '';
if (!in_array($filter_room, ['Bilik Meeting', 'Bilik Seminar'], true)) $filter_room = '';
if (!is_string($filter_q)) $filter_q = '';
if (!in_array($when, ['upcoming', 'today', 'week', 'all'], true)) $when = 'upcoming';
if ($filter_date !== '') $when = 'all';   // a specific date overrides the quick ranges

$today       = date('Y-m-d');
$week_monday = date('Y-m-d', strtotime('monday this week'));
$week_friday = date('Y-m-d', strtotime("$week_monday +4 days"));
$is_admin    = ($_SESSION['role'] === 'admin');
$my_staff_id = $_SESSION['staff_id'];

// Build query dynamically based on filters
$sql = "SELECT * FROM bookings WHERE 1=1";
$params = [];
$types = "";

if ($filter_date !== '') {
    $sql .= " AND booking_date = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if ($when === 'upcoming') {
    $sql .= " AND booking_date >= ?";
    $params[] = $today;
    $types .= "s";
} elseif ($when === 'today') {
    $sql .= " AND booking_date = ?";
    $params[] = $today;
    $types .= "s";
} elseif ($when === 'week') {
    $sql .= " AND booking_date BETWEEN ? AND ?";
    $params[] = $week_monday;
    $params[] = $week_friday;
    $types .= "ss";
}

if ($filter_room !== '') {
    $sql .= " AND room = ?";
    $params[] = $filter_room;
    $types .= "s";
}

if ($filter_q !== '') {
    $sql .= " AND (purpose LIKE ? OR booked_by LIKE ? OR staff_name LIKE ?)";
    $like = '%' . $filter_q . '%';
    array_push($params, $like, $like, $like);
    $types .= "sss";
}

// Link for a quick range, keeping the room and search filters
function when_link($value, $room, $q) {
    return 'view_bookings.php?' . http_build_query(array_filter(['when' => $value, 'filter_room' => $room, 'q' => $q]));
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <?php include 'header.php'; ?>

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

        <!-- Quick ranges -->
        <div class="quick-filters">
            <?php foreach (['upcoming' => 'Upcoming', 'today' => 'Today', 'week' => 'This Week', 'all' => 'All (incl. past)'] as $val => $label): ?>
                <a href="<?php echo htmlspecialchars(when_link($val, $filter_room, $filter_q)); ?>"
                   class="quick-filter<?php echo ($when === $val && $filter_date === '') ? ' active' : ''; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Filter bar -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="when" value="<?php echo htmlspecialchars($when); ?>">
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($filter_q); ?>" placeholder="Purpose or name">
            </div>

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
            <?php if ($when === 'upcoming' && $filter_date === ''): ?>(from today onwards)<?php endif; ?>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()):
                        $ended = strtotime($row['booking_date'] . ' ' . $row['end_time']) <= time();
                        $mine  = ($row['staff_id'] === $my_staff_id);
                        // Admins can change anything; staff only their own bookings that haven't ended
                        $can_manage = $is_admin || ($mine && !$ended);
                        $date_label = date('d M Y', strtotime($row['booking_date']));
                    ?>
                        <tr class="<?php echo trim(($row['booking_date'] === $today ? 'row-today ' : '') . ($ended ? 'row-past' : '')); ?>">
                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($row['booked_by'] ?? ''); ?></td>
                            <td>
                                <?php echo date('D', strtotime($row['booking_date'])) . ', ' . $date_label; ?>
                                <?php if ($row['booking_date'] === $today): ?><span class="today-badge">Today</span><?php endif; ?>
                            </td>
                            <td><?php echo date('g:i A', strtotime($row['start_time'])) . " – " . date('g:i A', strtotime($row['end_time'])); ?></td>
                            <td><span class="room-tag room-<?php echo strtolower(room_label($row['room'])); ?>"><?php echo htmlspecialchars(room_label($row['room'])); ?></span></td>
                            <td class="actions">
                                <?php if ($can_manage): ?>
                                    <a href="edit_booking.php?id=<?php echo $row['id']; ?>&from=view" class="action-btn edit-btn">Edit</a>
                                    <form method="POST" action="cancel_booking.php" class="inline-form"
                                        onsubmit="return confirm(<?php echo htmlspecialchars(json_encode('Cancel "' . $row['purpose'] . '" on ' . $date_label . '?'), ENT_QUOTES); ?>);">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="from" value="view">
                                        <button type="submit" class="action-btn cancel-btn">Cancel</button>
                                    </form>
                                <?php elseif ($mine): ?>
                                    <span class="muted">Ended</span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
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