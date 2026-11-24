<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php";
require_once "config.php";
require_once "helpers.php";

$staff_id = $_SESSION['staff_id'];
$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
$from = $_GET['from'] ?? $_POST['from'] ?? 'my';
$from = in_array($from, ['my', 'view', 'home'], true) ? $from : 'my';
$week_start = $_GET['week_start'] ?? $_POST['week_start'] ?? '';
$week_start = (is_string($week_start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) ? $week_start : '';
$error = "";

// Where "Cancel" and a successful save go back to
if ($from === 'view') {
    $back_page = "view_bookings.php";
} elseif ($from === 'home') {
    $back_page = "homepage.php" . ($week_start !== '' ? "?week_start=$week_start" : "");
} else {
    $back_page = "my_bookings.php";
}
$back_sep = (strpos($back_page, '?') === false) ? '?' : '&';

if ($id === false || $id === null) {
    header("Location: my_bookings.php?msg=error");
    exit;
}

// Fetch the existing booking, but only if it belongs to this staff member
if ($is_admin) {
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
      header("Location: {$back_page}{$back_sep}msg=error");
  exit;
}
$booking = $result->fetch_assoc();

// Handle the update when the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_valid()) { csrf_fail(); }
    $post_str = function ($key) {
        return (isset($_POST[$key]) && is_string($_POST[$key])) ? $_POST[$key] : '';
    };
    $purpose      = $post_str('purpose');
    $booking_date = $post_str('booking_date');
    $start_time   = $post_str('start_time');
    $end_time     = $post_str('end_time');
    $room         = $post_str('room');

    [$b, $validation_error] = validate_booking($_POST, $is_admin);

    if (!$is_admin && strtotime($booking['booking_date'] . ' ' . $booking['end_time']) <= time()) {
        $error = "Past bookings can't be changed.";
    } elseif ($validation_error !== null) {
        $error = $validation_error;
    } elseif (!booking_lock($conn)) {
        $error = "The system is busy. Please try again in a moment.";
    } else {
        $purpose      = $b['purpose'];
        $booking_date = $b['booking_date'];
        $start_time   = $b['start_time'];
        $end_time     = $b['end_time'];
        $room         = $b['room'];

        // Conflict check — EXCLUDE this booking's own id
        if (booking_conflict($conn, $room, $booking_date, $start_time, $end_time, $id)) {
            booking_unlock($conn);
            $error = "Sorry, " . room_label($room) . " is already booked during that time on $booking_date.";
        } else {
            // No conflict — update the booking (admin can update all)
            if ($is_admin) {
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

            try {
                $saved = $stmt3->execute();
            } catch (Throwable $e) {
                error_log("SALAM edit error: " . $e->getMessage());
                $saved = false;
            }
            booking_unlock($conn);

            if ($saved) {
                if ($from === 'home') {
                    // Show the week the booking is now in
                    $redirect_page = "homepage.php?week_start=$booking_date&";
                } else {
                    $redirect_page = $back_page . $back_sep;
                }
                header("Location: {$redirect_page}msg=updated");
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo (int)$booking['id']; ?>">
                <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
                <input type="hidden" name="week_start" value="<?php echo htmlspecialchars($week_start); ?>">

                <label>Purpose</label>
                <input type="text" name="purpose" value="<?php echo htmlspecialchars($booking['purpose']); ?>" required>

                <label>Date</label>
                <input type="date" name="booking_date" id="booking_date" value="<?php echo htmlspecialchars($booking['booking_date']); ?>"
                    <?php if (!$is_admin): ?>min="<?php echo date('Y-m-d'); ?>"<?php endif; ?> required>

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
                    <option value="Bilik Meeting" <?php if ($booking['room'] === 'Bilik Meeting') echo 'selected'; ?>>Meeting</option>
                    <option value="Bilik Seminar" <?php if ($booking['room'] === 'Bilik Seminar') echo 'selected'; ?>>Seminar</option>
                </select>

                <div class="form-actions">
                    <button type="submit">Save Changes</button>
                    <a href="<?php echo htmlspecialchars($back_page); ?>" class="cancel-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    const startSelect = document.getElementById('start_time');
    const endSelect = document.getElementById('end_time');
    const preview = document.getElementById('time-preview');

    const dateInput = document.getElementById('booking_date');

    // Grey out end times that are not after the chosen start time
    function syncEndOptions() {
        Array.from(endSelect.options).forEach(o => { o.disabled = o.value <= startSelect.value; });
        if (endSelect.value <= startSelect.value) {
            const next = Array.from(endSelect.options).find(o => !o.disabled);
            if (next) endSelect.value = next.value;
        }
    }

    function updatePreview() {
        const startText = startSelect.options[startSelect.selectedIndex].text;
        const endText = endSelect.options[endSelect.selectedIndex].text;
        const valid = endSelect.value > startSelect.value;
        const day = dateInput.value ? new Date(dateInput.value + 'T00:00:00').getDay() : 1;
        const weekend = (day === 0 || day === 6);

        const toMin = hm => { const [h, m] = hm.split(':').map(Number); return h * 60 + m; };
        const mins = toMin(endSelect.value) - toMin(startSelect.value);
        const h = Math.floor(mins / 60), m = mins % 60;
        const length = (h ? h + 'h' : '') + (h && m ? ' ' : '') + (m ? m + 'm' : '');

        if (weekend) {
            preview.textContent = 'Bookings are only available Monday to Friday';
        } else {
            preview.textContent = valid
                ? `Meeting runs from ${startText} to ${endText} (${length})`
                : `End time must be after start time`;
        }
        preview.className = (valid && !weekend) ? 'time-preview valid' : 'time-preview invalid';
    }

    startSelect.addEventListener('change', syncEndOptions);
    dateInput.addEventListener('change', updatePreview);
    syncEndOptions();
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