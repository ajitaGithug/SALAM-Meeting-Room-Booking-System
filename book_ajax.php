<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['staff_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit;
}
require "config.php";

$staff_name   = $_SESSION['staff_name'];
$staff_id     = $_SESSION['staff_id'];
$purpose      = $_POST['purpose'];
$booking_date = $_POST['booking_date'];
$start_time   = $_POST['start_time'];
$end_time     = $_POST['end_time'];
$room         = $_POST['room'];
$booked_by    = $_POST['booked_by'] ?? '';

if ($end_time <= $start_time) {
    echo json_encode(["success" => false, "message" => "End time must be after start time."]);
    exit;
}

$check_sql = "SELECT * FROM bookings WHERE room = ? AND booking_date = ? AND start_time < ? AND end_time > ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ssss", $room, $booking_date, $end_time, $start_time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Sorry, $room is already booked during that time on $booking_date."]);
    exit;
}

$insert_sql = "INSERT INTO bookings (staff_name, staff_id, purpose, booking_date, start_time, end_time, room, booked_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("ssssssss", $staff_name, $staff_id, $purpose, $booking_date, $start_time, $end_time, $room, $booked_by);
$stmt->execute();

echo json_encode([
    "success" => true,
    "message" => "Booking confirmed for $room on $booking_date from $start_time to $end_time.",
    "booking" => ["purpose" => $purpose, "date" => $booking_date, "start" => $start_time, "end" => $end_time, "room" => $room]
]);

$stmt->close();
$conn->close();
?>