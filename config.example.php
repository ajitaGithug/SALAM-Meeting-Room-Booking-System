<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

$host = "localhost";
$db   = "salam_meeting_room_booking";
$user = "your_db_user";      // create a dedicated MySQL user, not root
$pass = "your_db_password";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log("SALAM DB connection failed: " . $conn->connect_error);
    die("Service temporarily unavailable. Please contact the administrator.");
}

$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");

function room_label($room) {
    $labels = [
        'Bilik Meeting' => 'Meeting',
        'Bilik Seminar' => 'Seminar',
    ];
    return $labels[$room] ?? $room;
}