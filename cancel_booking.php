<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php";
require_once "config.php";

$from = $_POST['from'] ?? 'my';
$redirect_page = ($from === 'view') ? "view_bookings.php" : "my_bookings.php";
if ($from === 'home') {
    // Go back to the same week on the booking grid
    $ws = $_POST['week_start'] ?? '';
    $redirect_page = (is_string($ws) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ws))
        ? "homepage.php?week_start=$ws" : "homepage.php";
}
$sep = (strpos($redirect_page, '?') === false) ? '?' : '&';

// Opening this page by link no longer deletes anything
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: $redirect_page");
    exit;
}
if (!csrf_valid()) {
    csrf_fail();
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    header("Location: {$redirect_page}{$sep}msg=error");
    exit;
}

$staff_id = $_SESSION['staff_id'];

if (($_SESSION['role'] ?? '') === 'admin') {
    // Admin can cancel any booking
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $id);
} else {
    // Regular staff can only cancel their own booking, and not one from a past day
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ? AND staff_id = ? AND booking_date >= CURDATE()");
    $stmt->bind_param("is", $id, $staff_id);
}
$stmt->execute();

if ($stmt->affected_rows > 0) {
    header("Location: {$redirect_page}{$sep}msg=cancelled");
} else {
    header("Location: {$redirect_page}{$sep}msg=error");
}
exit;