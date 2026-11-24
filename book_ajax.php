<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "not_logged_in", "message" => "Not logged in."]);
    exit;
}
define('SALAM_JSON', true);   // tells session_check.php to answer in JSON, not redirect
require "session_check.php";
require_once "config.php";
require_once "helpers.php";

if (!csrf_valid()) {
    csrf_fail();
}

function json_fail($msg) {
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}

$is_admin  = (($_SESSION['role'] ?? '') === 'admin');
$booked_by = (isset($_POST['booked_by']) && is_string($_POST['booked_by'])) ? trim($_POST['booked_by']) : '';

if ($booked_by === '')           json_fail("Please enter your name.");
if (mb_strlen($booked_by) > 100) json_fail("Name must be 100 characters or fewer.");

[$b, $validation_error] = validate_booking($_POST, $is_admin);
if ($validation_error !== null) json_fail($validation_error);

$staff_name = $_SESSION['staff_name'];
$staff_id   = $_SESSION['staff_id'];

try {
    if (!booking_lock($conn)) {
        json_fail("The system is busy. Please try again in a moment.");
    }

    if (booking_conflict($conn, $b['room'], $b['booking_date'], $b['start_time'], $b['end_time'])) {
        json_fail("Sorry, " . room_label($b['room']) . " is already booked during that time on " . $b['booking_date'] . ".");
    }

    $insert_sql = "INSERT INTO bookings (staff_name, staff_id, purpose, booking_date, start_time, end_time, room, booked_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ssssssss", $staff_name, $staff_id, $b['purpose'], $b['booking_date'],
                      $b['start_time'], $b['end_time'], $b['room'], $booked_by);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    booking_unlock($conn);
} catch (Throwable $e) {
    error_log("SALAM booking error: " . $e->getMessage());
    json_fail("Could not save the booking. Please check My Bookings before trying again.");
}

echo json_encode([
    "success" => true,
    "message" => "Booking confirmed for " . room_label($b['room']) . " on " . $b['booking_date'] .
                 " from " . $b['start_time'] . " to " . $b['end_time'] . ".",
    "booking" => [
        "id" => $new_id, "purpose" => $b['purpose'], "date" => $b['booking_date'],
        "start" => $b['start_time'], "end" => $b['end_time'], "room" => $b['room']
    ]
]);

$conn->close();
