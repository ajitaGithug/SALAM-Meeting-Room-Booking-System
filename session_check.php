<?php
// Session timeout duration (in seconds) - e.g. 15 minutes
$timeout_duration = 5 * 60; 

if (isset($_SESSION['last_activity'])) {  //check if we've recorded a previous activity time
    $elapsed = time() - $_SESSION['last_activity'];  //calcualte how many seconds have past since last page load
    if ($elapsed > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit;
    }
}

// Update last activity time on every page load
$_SESSION['last_activity'] = time();
?>