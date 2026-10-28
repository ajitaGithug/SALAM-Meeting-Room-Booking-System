<?php
session_start();
if (!isset($_SESSION['staff_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

require "session_check.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && !csrf_valid()) { 
    csrf_fail(); 
}

if (($_SESSION['role'] ?? '') !== 'admin') {   // role is now refreshed from the database
    header("Location: homepage.php");
    exit;
}
require_once "config.php";

$register_message = "";
$reset_message = "";
$toggle_message = "";
$register_ok = $reset_ok = $toggle_ok = false;   // green box when true, red box when false

// Handle new Account registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['action'] ?? '') === 'register') {
    $staff_name = trim($_POST['staff_name'] ?? '');
    $staff_id   = trim($_POST['staff_id'] ?? '');
    $plain_pw   = $_POST['password'] ?? '';

    if ($staff_name === '' || $staff_id === '' || mb_strlen($staff_name) > 100 || mb_strlen($staff_id) > 20) {
        $register_message = "Error: name and account ID are required (name max 100, ID max 20 characters).";
    } elseif (strlen($plain_pw) < 8) {
        $register_message = "Error: password must be at least 8 characters.";
    } else {
        $password = password_hash($plain_pw, PASSWORD_DEFAULT);

        $check = $conn->prepare("SELECT id FROM users WHERE staff_id = ?");
        $check->bind_param("s", $staff_id);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $register_message = "Error: Staff ID '$staff_id' is already registered.";
        } else {
            $sql = "INSERT INTO users (staff_name, staff_id, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $staff_name, $staff_id, $password);

            if ($stmt->execute()) {
                $register_message = "Account created for $staff_name ($staff_id).";
                $register_ok = true;
            } else {
                $register_message = "Error: could not create the account.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['action'] ?? '') === 'reset') {
    $target_staff_id = $_POST['staff_id'] ?? '';
    $new_password    = $_POST['new_password'] ?? '';

    if (strlen($new_password) < 8) {
        $reset_message = "Password must be at least 8 characters.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ?, failed_logins = 0, locked_until = NULL WHERE staff_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $hashed, $target_staff_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $reset_message = "Password updated for $target_staff_id.";
            $reset_ok = true;
        } else {
            $reset_message = "Staff ID not found or no change made.";
        }
        $stmt->close();
    }
}

// Handle activate/deactivate toggle
if ($_SERVER["REQUEST_METHOD"] == "POST" && ($_POST['action'] ?? '') === 'toggle_status') {
    $target_staff_id = $_POST['staff_id'] ?? '';
    $new_status      = $_POST['new_status'] ?? '';

    if (strcasecmp($target_staff_id, $_SESSION['staff_id']) === 0) {
        $toggle_message = "You cannot deactivate your own account.";
    } elseif (!in_array($new_status, ['active', 'inactive'], true)) {
        $toggle_message = "Invalid status.";
    } else {
        $sql = "UPDATE users SET status = ? WHERE staff_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $new_status, $target_staff_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $toggle_message = "Staff ID $target_staff_id is now $new_status.";
            $toggle_ok = true;
        } else {
            $toggle_message = "Staff ID not found or no change made.";
        }
        $stmt->close();
    }
}

// Fetch staff list for the reset dropdown
$users_result = $conn->query("SELECT staff_id, staff_name, status FROM users ORDER BY staff_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Account - SALAM</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <?php include 'header.php'; ?>

        <div class="container">
        <h2>Manage Account</h2>

        <div class="dashboard">

            <div class="card">
                <h3>Add New Account</h3>
                <?php if ($register_message): ?>
                    <div class="<?php echo $register_ok ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($register_message); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <?php echo csrf_field(); ?>

                    <label>Name:</label>
                    <input type="text" name="staff_name" required>

                    <label>Account ID:</label>
                    <input type="text" name="staff_id" required>

                    <label>Password:</label>
                    <input type="password" name="password" required minlength="8">
                    <small class="field-help">At least 8 characters.</small>

                    <button type="submit">Register Account</button>
                </form>
            </div>

            <div class="card">
                <h3>Reset Account Password</h3>
                <?php if ($reset_message): ?>
                    <div class="<?php echo $reset_ok ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($reset_message); ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="reset">

                    <?php echo csrf_field(); ?>

                    <label>Select Account:</label>
                    <select name="staff_id" required>
                        <?php
                        $users_result->data_seek(0);
                        while ($u = $users_result->fetch_assoc()):
                        ?>
                            <option value="<?php echo htmlspecialchars($u['staff_id']); ?>">
                                <?php echo htmlspecialchars($u['staff_name']) . " (" . htmlspecialchars($u['staff_id']) . ")"; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <label>New Password:</label>
                    <input type="password" name="new_password" required minlength="8">
                    <small class="field-help">At least 8 characters. This also unlocks a locked account.</small>

                    <button type="submit">Update Password</button>
                </form>
            </div>

            <div class="card">
                <h3>Manage Account Status</h3>
                <?php if ($toggle_message): ?>
                    <div class="<?php echo $toggle_ok ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($toggle_message); ?></div>
                <?php endif; ?>

                <table>
                    <tr><th>Account</th><th>Status</th><th>Action</th></tr>
                    <?php
                    $users_result->data_seek(0);
                    while ($u = $users_result->fetch_assoc()):
                        $is_active = $u['status'] === 'active';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['staff_name']) . " (" . htmlspecialchars($u['staff_id']) . ")"; ?></td>
                            <td><span class="<?php echo $is_active ? 'status-active' : 'status-inactive'; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                            <td>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('<?php echo $is_active ? 'Deactivate' : 'Reactivate'; ?> this staff account?');">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($u['staff_id']); ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $is_active ? 'inactive' : 'active'; ?>">
                                    <button type="submit" class="action-btn <?php echo $is_active ? 'cancel-btn' : 'edit-btn'; ?>">
                                        <?php echo $is_active ? 'Deactivate' : 'Reactivate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>

        </div>
    </div>

    <?php $conn->close(); ?>
</body>
</html>