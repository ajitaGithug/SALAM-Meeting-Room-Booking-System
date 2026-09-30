<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php";
require "config.php";

$staff_id = $_SESSION['staff_id'];
$id = $_GET['id'] ?? $_POST['id'] ?? null;
$from = $_GET['from'] ?? $_POST['from'] ?? 'my';
$error = "";

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
        $error = "End time must be after start time.";
    } else {
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
            $error = "Sorry, $room is already booked during that time on $booking_date.";
        } else {
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

            $redirect_page = ($from === 'view') ? "view_bookings.php" : "my_bookings.php";

            if ($stmt3->execute()) {
                header("Location: $redirect_page?msg=updated");
                exit;
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }

    // If we reach here, something failed — refresh $booking with the submitted
    // values so the form redisplays what the user just typed, not the old data
    $booking['purpose']      = $purpose;
    $booking['booking_date'] = $booking_date;
    $booking['start_time']   = $start_time;
    $booking['end_time']     = $end_time;
    $booking['room']         = $room;
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="style.css">
    <title>Edit Booking - SALAM</title>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container" style="max-width: 520px;">
        <h2>Edit Booking</h2>

        <?php if ($error): ?>
            <div class="error">
                <span class="error-icon">!</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" id="edit-form">
                <input type="hidden" name="id" value="<?php echo $booking['id']; ?>">
                <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">

                <label>Purpose</label>
                <input type="text" name="purpose" value="<?php echo htmlspecialchars($booking['purpose']); ?>" required>

                <label>Date</label>
                <input type="date" name="booking_date" value="<?php echo $booking['booking_date']; ?>" required>

                <div class="time-row">
                    <div>
                        <label>Start Time</label>
                        <select name="start_time" id="start_time" required>
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
                        </select>
                    </div>

                    <div>
                        <label>End Time</label>
                        <select name="end_time" id="end_time" required>
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
                        </select>
                    </div>
                </div>

                <p id="time-preview" class="time-preview"></p>

                <label>Room</label>
                <select name="room" required>
                    <option value="Bilik Meeting" <?php if ($booking['room'] === 'Bilik Meeting') echo 'selected'; ?>>Bilik Meeting</option>
                    <option value="Bilik Seminar" <?php if ($booking['room'] === 'Bilik Seminar') echo 'selected'; ?>>Bilik Seminar</option>
                </select>

                <div class="form-actions">
                    <button type="submit">Save Changes</button>
                    <a href="<?php echo $from === 'view' ? 'view_bookings.php' : 'my_bookings.php'; ?>" class="cancel-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    const startSelect = document.getElementById('start_time');
    const endSelect = document.getElementById('end_time');
    const preview = document.getElementById('time-preview');

    function updatePreview() {
        const startText = startSelect.options[startSelect.selectedIndex].text;
        const endText = endSelect.options[endSelect.selectedIndex].text;
        const valid = endSelect.value > startSelect.value;

        preview.textContent = valid
            ? `Meeting runs from ${startText} to ${endText}`
            : `End time must be after start time`;
        preview.className = valid ? 'time-preview valid' : 'time-preview invalid';
    }

    startSelect.addEventListener('change', updatePreview);
    endSelect.addEventListener('change', updatePreview);
    updatePreview();

    document.getElementById('edit-form').addEventListener('submit', function(e) {
        if (endSelect.value <= startSelect.value) {
            alert('End time must be after start time.');
            e.preventDefault();
        }
    });
    </script>
</body>
</html>