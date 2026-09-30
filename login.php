<?php
require "config.php";
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $staff_id = $_POST['staff_id'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($user['status'] === 'inactive') {
            $error = "This account has been deactivated. Please contact an admin.";
        } elseif (password_verify($password, $user['password'])) {
            $_SESSION['staff_id']   = $user['staff_id'];
            $_SESSION['staff_name'] = $user['staff_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            header("Location: homepage.php");
            exit;
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "No account found with that Staff ID.";
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - SALAM</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="login-page">

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <img src="assets/Salam-logo.png" alt="SALAM Logo" class="login-logo">
                <h2>Welcome Back</h2>
                <p class="login-subtitle">Sign in to manage your meeting room bookings</p>
            </div>

            <?php if (isset($_GET['timeout'])): ?>
                <div class="error">You were logged out due to inactivity. Please log in again.</div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="login-form">
                <div class="input-group">
                    <label for="staff_id">Staff ID</label>
                    <input type="text" name="staff_id" id="staff_id" placeholder="e.g. SALAM1" required autofocus>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" id="toggle-password" aria-label="Show password">
                            <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" id="login-btn" class="login-btn">
                    <span id="btn-text">Login</span>
                </button>
            </form>
        </div>

        <p class="login-footer">SALAM Meeting Room Booking System</p>
    </div>

    <script>
    // Toggle password visibility
    const toggleBtn = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');

    toggleBtn.addEventListener('click', function() {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        eyeIcon.innerHTML = isPassword
            ? '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    });

    // Loading state on submit
    document.getElementById('login-form').addEventListener('submit', function() {
        const btn = document.getElementById('login-btn');
        btn.disabled = true;
        document.getElementById('btn-text').textContent = 'Signing in...';
    });
    </script>
</body>
</html>