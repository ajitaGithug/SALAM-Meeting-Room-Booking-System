<?php
session_start();
require "config.php";

$error = "";
$staff_id_val = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $staff_id = trim($_POST['staff_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $staff_id_val = $staff_id;

    if (!empty($staff_id) && !empty($password)) {
        $sql = "SELECT staff_id, staff_name, role, status, password FROM users WHERE staff_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] === 'inactive') {
                $error = "This account has been deactivated. Please contact an admin.";
            } elseif (password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['staff_id']      = $user['staff_id'];
                $_SESSION['staff_name']    = $user['staff_name'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['last_activity'] = time();

                $stmt->close();
                $conn->close();

                header("Location: homepage.php");
                exit;
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "No account found with that Staff ID.";
        }
        $stmt->close();
    } else {
        $error = "Please enter both your Staff ID and password.";
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SALAM Booking System</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="login-page">

    <!-- Ambient glowing background accents -->
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>

    <div class="login-wrapper">
        <div class="login-card <?php echo !empty($error) ? 'has-error' : ''; ?>">
            
            <!-- Header & Branding -->
            <div class="login-header">
                <div class="logo-container">
                    <img src="assets/Salam-logo.png" alt="SALAM Logo" class="login-logo" onerror="this.style.display='none'">
                </div>
                <h2>Welcome</h2>
                <p class="login-subtitle">Sign in to manage your meeting room bookings</p>
            </div>

            <!-- Inactivity Logout Alert -->
            <?php if (isset($_GET['timeout'])): ?>
                <div class="alert-banner alert-warning">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>Logged out due to inactivity. Please sign in again.</span>
                </div>
            <?php endif; ?>

            <!-- Error Banner -->
            <?php if (!empty($error)): ?>
                <div class="alert-banner alert-danger">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" id="login-form" autocomplete="on">
                
                <!-- Staff ID Field -->
                <div class="input-group">
                    <label for="staff_id">Staff ID</label>
                    <div class="input-field-wrapper">
                        <span class="field-icon">
                            <!-- User ID Badge Icon -->
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            name="staff_id" 
                            id="staff_id" 
                            placeholder="e.g. SALAM1" 
                            value="<?php echo htmlspecialchars($staff_id_val); ?>" 
                            required 
                            autofocus
                            autocomplete="username"
                            spellcheck="false"
                        >
                    </div>
                </div>

                <!-- Password Field -->
                <div class="input-group">
                    <div class="label-row">
                        <label for="password">Password</label>
                    </div>
                    <div class="input-field-wrapper">
                        <span class="field-icon">
                            <!-- Lock Icon -->
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            placeholder="Enter your password" 
                            required
                            autocomplete="current-password"
                        >
                        <!-- Password Visibility Toggle -->
                        <button type="button" class="toggle-password" id="toggle-password" aria-label="Toggle password visibility">
                            <svg id="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Caps Lock Live Warning -->
                    <div id="caps-warning" class="caps-warning">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M12 2l8 8h-5v10h-6v-10h-5z"/>
                        </svg>
                        Caps Lock is active
                    </div>
                </div>

                <!-- Remember Staff ID Checkbox -->
                <!--
                <div class="form-utilities">
                    <label class="checkbox-container">
                        <input type="checkbox" id="remember-me">
                        <span class="checkmark"></span>
                        <span class="checkbox-text">Remember my Staff ID</span>
                    </label>
                </div>
                -->

                <!-- Submit Button -->
                <button type="submit" id="login-btn" class="login-btn">
                    <span class="btn-loader" id="btn-loader"></span>
                    <span id="btn-text">Sign In</span>
                </button>
            </form>
        </div>

        <p class="login-footer">SALAM Meeting/Seminar Room Booking System &bull;</p>
    </div>

    <!-- Intuitive Interactions Script -->
    <script>
    // 1. Password Visibility Toggle
    const toggleBtn = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eye-icon');

    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        
        // Switch between Open Eye and Eye with Slash
        eyeIcon.innerHTML = isPassword
            ? '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        
        passwordInput.focus();
    });

    // 2. Caps Lock Live Detection
    const capsWarning = document.getElementById('caps-warning');
    passwordInput.addEventListener('keyup', function(e) {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
            capsWarning.classList.add('visible');
        } else {
            capsWarning.classList.remove('visible');
        }
    });

    // 3. Auto-Capitalize Staff ID (e.g. salam1 -> SALAM1)
    const staffIdInput = document.getElementById('staff_id');
    staffIdInput.addEventListener('input', function() {
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(start, end);
    });

    // 4. Remember Staff ID (via localStorage)
    const rememberCheckbox = document.getElementById('remember-me');
    const savedStaffId = localStorage.getItem('salam_saved_staff_id');
    if (savedStaffId && !staffIdInput.value) {
        staffIdInput.value = savedStaffId;
        rememberCheckbox.checked = true;
    }

    // 5. Submit Loading State & Animation
    document.getElementById('login-form').addEventListener('submit', function() {
        const btn = document.getElementById('login-btn');
        const btnText = document.getElementById('btn-text');
        const loader = document.getElementById('btn-loader');

        // Save or remove Staff ID in localStorage
        if (rememberCheckbox.checked) {
            localStorage.setItem('salam_saved_staff_id', staffIdInput.value.trim());
        } else {
            localStorage.removeItem('salam_saved_staff_id');
        }

        btn.disabled = true;
        btn.classList.add('is-loading');
        loader.style.display = 'inline-block';
        btnText.textContent = 'Verifying...';
    });
    </script>
</body>
</html>