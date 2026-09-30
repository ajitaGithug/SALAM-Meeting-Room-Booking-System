<?php
session_start(); //continue user session value ($_SESSION)

//Block access if not logged in
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php"; // session timer
require "config.php"; //connection with database
?>
<!DOCTYPE html>
<html>
<head>
    <title>SALAM Meeting Room Booking</title>
    <link rel="stylesheet" href="style.css">
    
</head>
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
<body>

    <?php include 'header.php'; ?>

    <h2>Book a Meeting Room</h2>
    <form action="save_booking.php" method="POST">

        <label>Name:</label><br>
        <input type="text" name="staff_name" value="<?php echo htmlspecialchars($_SESSION['staff_name']); ?>" readonly><br><br>

        <label>Staff ID:</label><br>
        <input type="text" name="staff_id" value="<?php echo htmlspecialchars($_SESSION['staff_id']); ?>" readonly><br><br>

        <label>Purpose:</label><br>
        <input type="text" name="purpose" required><br><br>

        <label>Date:</label><br>
        <input type="date" name="booking_date" required><br><br>

        <label>Start Time:</label><br>
        <select name="start_time" required>
            <?php
            $time = strtotime("08:30");
            $end = strtotime("16:30"); // last valid start time
            while ($time <= $end) {
                $formatted = date("H:i", $time);
                echo "<option value=\"$formatted\">" . date("g:i A", $time) . "</option>";
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
                echo "<option value=\"$formatted\">" . date("g:i A", $time) . "</option>";
                $time = strtotime("+30 minutes", $time);
            }
            ?>
        </select><br><br>

        <label>Room:</label><br>
        <select name="room" required>
            <option value="Bilik Meeting">Meeting Room</option>
            <option value="Bilik Seminar">Bilik Seminar</option>
        </select><br><br>

        <button type="submit">Book Now</button>
    </form>
</body>
</html>
