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
    header("Location: $redirect_page?msg=error");
    exit;
}

$staff_id = $_SESSION['staff_id'];

if (($_SESSION['role'] ?? '') === 'admin') {
    // Admin can cancel any booking
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $id);
} else {
    // Regular staff can only cancel their own booking
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ? AND staff_id = ?");
    $stmt->bind_param("is", $id, $staff_id);
}
$stmt->execute();

if ($stmt->affected_rows > 0) {
    header("Location: $redirect_page?msg=cancelled");
} else {
    header("Location: $redirect_page?msg=error");
}
exit;