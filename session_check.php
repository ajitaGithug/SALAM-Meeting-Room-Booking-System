<?php
// Runs on every protected request. session_start() must already have been called.
require_once "config.php";

$timeout_duration = 15 * 60; // 15 minutes of inactivity

function salam_end_session($reason) {
    $json = defined('SALAM_JSON');

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();

    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false,
            "error"   => $reason,
            "message" => "Your session has ended. Please log in again."
        ]);
    } else {
        header("Location: login.php" . ($reason === 'session_expired' ? "?timeout=1" : ""));
    }
    exit;
}

// 1. Inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    salam_end_session('session_expired');
}

// 2. Re-check the account in the database on every request
$sc_stmt = $conn->prepare("SELECT role, status FROM users WHERE staff_id = ?");
$sc_stmt->bind_param("s", $_SESSION['staff_id']);
$sc_stmt->execute();
$sc_user = $sc_stmt->get_result()->fetch_assoc();
$sc_stmt->close();

if (!$sc_user || $sc_user['status'] !== 'active') {
    salam_end_session('account_inactive');
}

// 3. Use the role stored in the database, not the one saved at login
$_SESSION['role'] = $sc_user['role'];
$_SESSION['last_activity'] = time();