<?php
require "config.php"; // establish connection with database

//Get form data
$staff_name   = $_POST['staff_name'];
$staff_id     = $_POST['staff_id'];
$purpose      = $_POST['purpose'];
$booking_date = $_POST['booking_date'];
$start_time   = $_POST['start_time'];
$end_time     = $_POST['end_time'];
$room         = $_POST['room'];

//server-side validation (end time must be after start time)
if ($end_time <= $start_time) {
    die("Error: End time must be after start time.");
}

// Check conflict bookings (same room, same date, overlapping time)
$check_sql = "SELECT * FROM bookings
              WHERE room = ?
              AND booking_date = ?
              AND start_time < ?
              AND end_time > ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ssss", $room, $booking_date, $end_time, $start_time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    die("Sorry, $room is already booked during that time on $booking_date. Please choose another avaiable time or room");
}

// NO CONFLICT - insert the booking
$insert_sql = "INSERT INTO bookings (staff_name, staff_id, purpose, booking_date, start_time, end_time, room)
               VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_sql);
$stmt-> bind_param("sssssss", $staff_name, $staff_id, $purpose, $booking_date, $start_time, $end_time, $room);

if($stmt->execute()) {
    echo "Booking confirmed for $room on $booking_date from $start_time until $end_time.";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>