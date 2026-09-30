<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    http_response_code(403);
    exit;
}
require "config.php";
header('Content-Type: application/json');

$room = $_GET['room'] ?? '';
$date = $_GET['date'] ?? '';

if ($room === '' || $date === '') {
    echo json_encode([]);
    exit;
}

$sql = "SELECT start_time, end_time FROM bookings WHERE room = ? AND booking_date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $room, $date);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = ["start" => substr($row['start_time'], 0, 5), "end" => substr($row['end_time'], 0, 5)];
}

echo json_encode($bookings);
$stmt->close();
$conn->close();
?>