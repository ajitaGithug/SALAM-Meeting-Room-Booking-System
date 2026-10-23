<?php
session_start(); //required as PHP can access current session to destroy it

// Only a POST with a valid token can log out; anything else goes back to the app
$token_ok = isset($_SESSION['csrf_token'], $_POST['csrf_token'])
    && is_string($_POST['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$token_ok) {
    header("Location: homepage.php");
    exit;
}

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie itself (extra safety)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session on the server
session_destroy();

// Send them back to login
header("Location: login.php");
exit;