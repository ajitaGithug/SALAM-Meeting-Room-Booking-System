<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "config.php";

$id = $_GET['id'] ?? null;
$staff_id = $_SESSION['staff_id'];

if ($id === null) {
    die("No booking specified.");
}

// IMPORTANT: only delete if it belongs to the logged-in staff but admin can delete all
if ($_SESSION['role'] === 'admin') {
    // Admin can cancel any booking
    $sql = "DELETE FROM bookings WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
} else {
    //regular staff can only delete their own booking
    $sql = "DELETE FROM bookings WHERE id = ? AND staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $id, $staff_id);
}
$stmt->execute();

// Decide where to send the user back
$from = $_GET['from'] ?? 'my';
$redirect_page = ($from === 'view') ? "view_bookings.php" : "my_bookings.php";

if ($stmt->affected_rows > 0) {
    header("Location: $redirect_page?msg=cancelled");
} else {
    header("Location: $redirect_page?msg=error");
}
exit;
?>