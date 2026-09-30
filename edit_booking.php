<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php"; // session timer
require "config.php"; // establish connection with database

$staff_id = $_SESSION['staff_id'];
$id = $_GET['id'] ?? $_POST['id'] ?? null;
$from = $_GET['from'] ?? $_POST['from'] ?? 'my';

if ($id === null) {
    die("No booking specified.");
}

// Fetch the existing booking, but only if it belongs to this staff member
if ($_SESSION['role'] === 'admin') {
    $sql = "SELECT * FROM bookings WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
} else {
    $sql = "SELECT * FROM bookings WHERE id = ? AND staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $id, $staff_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Booking not found or you don't have permission to edit it.");
}
$booking = $result->fetch_assoc();

// Handle the update when the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $purpose      = $_POST['purpose'];
    $booking_date = $_POST['booking_date'];
    $start_time   = $_POST['start_time'];
    $end_time     = $_POST['end_time'];
    $room         = $_POST['room'];

    if ($end_time <= $start_time) {
        die("Error: End time must be after start time.");
    }

    // Conflict check — same as before, but EXCLUDE this booking's own id
    $check_sql = "SELECT * FROM bookings 
                  WHERE room = ? AND booking_date = ? 
                  AND start_time < ? AND end_time > ?
                  AND id != ?";
    $stmt2 = $conn->prepare($check_sql);
    $stmt2->bind_param("ssssi", $room, $booking_date, $end_time, $start_time, $id);
    $stmt2->execute();
    $conflict_result = $stmt2->get_result();

    if ($conflict_result->num_rows > 0) {
        die("Sorry, $room is already booked during that time on $booking_date.");
    }

    // No conflict — update the booking (admin can update all)
    if ($_SESSION['role'] === 'admin') {
    $update_sql = "UPDATE bookings 
                   SET purpose = ?, booking_date = ?, start_time = ?, end_time = ?, room = ?
                   WHERE id = ?";
    $stmt3 = $conn->prepare($update_sql);
    $stmt3->bind_param("sssssi", $purpose, $booking_date, $start_time, $end_time, $room, $id);
} else {
    $update_sql = "UPDATE bookings 
                   SET purpose = ?, booking_date = ?, start_time = ?, end_time = ?, room = ?
                   WHERE id = ? AND staff_id = ?";
    $stmt3 = $conn->prepare($update_sql);
    $stmt3->bind_param("sssssis", $purpose, $booking_date, $start_time, $end_time, $room, $id, $staff_id);
}

// Decide where to send the user back, based on where the request came from
    $redirect_page = ($from === 'view') ? "view_bookings.php" : "my_bookings.php";
    
    if ($stmt3->execute()) {
        header("Location: $redirect_page?msg=updated");
        exit;
    } else {
        echo "Error: " . $stmt3->error;
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="style.css">
    <title>Edit Booking - SALAM</title>
</head>
<body>
    <h2>Edit Booking</h2>
    <form method="POST">
        <input type="hidden" name="id" value="<?php echo $booking['id']; ?>">
        <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">

        <label>Purpose:</label><br>
        <input type="text" name="purpose" value="<?php echo htmlspecialchars($booking['purpose']); ?>" required><br><br>

        <label>Date:</label><br>
        <input type="date" name="booking_date" value="<?php echo $booking['booking_date']; ?>" required><br><br>

        <label>Start Time:</label><br>
        <select name="start_time" required>
            <?php
            $time = strtotime("08:30");
            $end = strtotime("16:30");
            while ($time <= $end) {
                $formatted = date("H:i", $time);
                $selected = ($formatted === substr($booking['start_time'], 0, 5)) ? "selected" : "";
                echo "<option value=\"$formatted\" $selected>" . date("g:i A", $time) . "</option>";
                $time = strtotime("+30 minutes", $time);
            }
            ?>
        </select><br><br>

        <label>End Time:</label><br>
        <select name="end_time" required>
            <?php
            $time = strtotime("09:00");
            $end = strtotime("17:00");
            while ($time <= $end) {
                $formatted = date("H:i", $time);
                $selected = ($formatted === substr($booking['end_time'], 0, 5)) ? "selected" : "";
                echo "<option value=\"$formatted\" $selected>" . date("g:i A", $time) . "</option>";
                $time = strtotime("+30 minutes", $time);
            }
            ?>
        </select><br><br>

        <label>Room:</label><br>
        <select name="room" required>
            <option value="Bilik Meeting" <?php if ($booking['room'] === 'Bilik Meeting') echo 'selected'; ?>>Bilik Meeting</option>
            <option value="Bilik Seminar" <?php if ($booking['room'] === 'Bilik Seminar') echo 'selected'; ?>>Bilik Seminar</option>
        </select><br><br>

        <button type="submit">Save Changes</button>
    </form>
</body>
</html>

<script>
document.querySelector("form").addEventListener("submit", function(e) {
    const start = document.querySelector("[name='start_time']").value;
    const end = document.querySelector("[name='end_time']").value;
    if (end <= start) {
        alert("End time must be after start time.");
        e.preventDefault();
    }
});
</script>