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


$my_id = $_SESSION['staff_id'];

// Message shown at the top of each card: [true = green / false = red, text]
$msg = ['register' => null, 'reset' => null, 'own' => null, 'accounts' => null];

// Look up one account by ID (null if it doesn't exist)
function find_user($conn, $staff_id) {
    $stmt = $conn->prepare("SELECT staff_id, staff_name, role, status FROM users WHERE staff_id = ?");
    $stmt->bind_param("s", $staff_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

$action = ($_SERVER["REQUEST_METHOD"] === "POST") ? ($_POST['action'] ?? '') : '';
$post = function ($key) {
    return (isset($_POST[$key]) && is_string($_POST[$key])) ? $_POST[$key] : '';
};

// Handle new Account registration
if ($action === 'register') {
    $staff_name = trim($post('staff_name'));
    $staff_id   = trim($post('staff_id'));
    $plain_pw   = $post('password');

    if ($staff_name === '' || $staff_id === '' || mb_strlen($staff_name) > 100 || mb_strlen($staff_id) > 20) {
        $msg['register'] = [false, "Name and account ID are required (name max 100, ID max 20 characters)."];
    } elseif (strlen($plain_pw) < 8) {
        $msg['register'] = [false, "Password must be at least 8 characters."];
    } elseif ($plain_pw !== $post('password_confirm')) {
        $msg['register'] = [false, "The two passwords don't match."];
    } elseif (find_user($conn, $staff_id)) {
        $msg['register'] = [false, "Account ID '$staff_id' is already registered."];
    } else {
        // New accounts are always staff. Admin accounts are created in phpMyAdmin.
        $password = password_hash($plain_pw, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (staff_name, staff_id, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $staff_name, $staff_id, $password);
        $msg['register'] = $stmt->execute()
            ? [true, "Account created for $staff_name ($staff_id)."]
            : [false, "Could not create the account."];
        $stmt->close();
    }
}

// Handle password reset (staff accounts only)
if ($action === 'reset') {
    $target = find_user($conn, $post('staff_id'));
    $new_password = $post('new_password');

    if (!$target) {
        $msg['reset'] = [false, "Account not found."];
    } elseif ($target['role'] === 'admin') {
        $msg['reset'] = [false, "Admin passwords can't be reset here. Each admin uses Change My Password."];
    } elseif (strlen($new_password) < 8) {
        $msg['reset'] = [false, "Password must be at least 8 characters."];
    } elseif ($new_password !== $post('new_password_confirm')) {
        $msg['reset'] = [false, "The two passwords don't match."];
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, failed_logins = 0, locked_until = NULL WHERE staff_id = ? AND role = 'staff'");
        $stmt->bind_param("ss", $hashed, $target['staff_id']);
        $stmt->execute();
        $msg['reset'] = ($stmt->affected_rows > 0)
            ? [true, "Password updated for {$target['staff_name']} ({$target['staff_id']})."]
            : [false, "No change made."];
        $stmt->close();
    }
}

// Handle the logged-in admin changing their own password
if ($action === 'change_own') {
    $stmt = $conn->prepare("SELECT password FROM users WHERE staff_id = ?");
    $stmt->bind_param("s", $my_id);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $current = $post('current_password');
    $new     = $post('new_password');

    if (!$me || !password_verify($current, $me['password'])) {
        $msg['own'] = [false, "Current password is incorrect."];
    } elseif (strlen($new) < 8) {
        $msg['own'] = [false, "New password must be at least 8 characters."];
    } elseif ($new !== $post('new_password_confirm')) {
        $msg['own'] = [false, "The two new passwords don't match."];
    } elseif ($new === $current) {
        $msg['own'] = [false, "The new password must be different from the current one."];
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE staff_id = ?");
        $stmt->bind_param("ss", $hashed, $my_id);
        $stmt->execute();
        $stmt->close();
        $msg['own'] = [true, "Your password has been changed."];
    }
}

// Handle activate/deactivate and unlock (staff accounts only, never yourself)
if ($action === 'toggle_status' || $action === 'unlock') {
    $target = find_user($conn, $post('staff_id'));

    if (!$target) {
        $msg['accounts'] = [false, "Account not found."];
    } elseif (strcasecmp($target['staff_id'], $my_id) === 0) {
        $msg['accounts'] = [false, "You cannot change your own account here."];
    } elseif ($target['role'] === 'admin') {
        $msg['accounts'] = [false, "Admin accounts are protected and can't be changed here."];
    } elseif ($action === 'unlock') {
        $stmt = $conn->prepare("UPDATE users SET failed_logins = 0, locked_until = NULL WHERE staff_id = ? AND role = 'staff'");
        $stmt->bind_param("s", $target['staff_id']);
        $stmt->execute();
        $stmt->close();
        $msg['accounts'] = [true, "{$target['staff_name']} ({$target['staff_id']}) is unlocked."];
    } else {
        $new_status = $post('new_status');
        if (!in_array($new_status, ['active', 'inactive'], true)) {
            $msg['accounts'] = [false, "Invalid status."];
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE staff_id = ? AND role = 'staff'");
            $stmt->bind_param("ss", $new_status, $target['staff_id']);
            $stmt->execute();
            $msg['accounts'] = ($stmt->affected_rows > 0)
                ? [true, "{$target['staff_name']} ({$target['staff_id']}) is now $new_status."]
                : [false, "No change made."];
            $stmt->close();
        }
    }
}

// All accounts: admins first, then staff by name
$users = $conn->query(
    "SELECT staff_id, staff_name, role, status, locked_until,
            (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked
     FROM users ORDER BY (role = 'admin') DESC, staff_name"
)->fetch_all(MYSQLI_ASSOC);

$staff_users = array_values(array_filter($users, fn($u) => $u['role'] === 'staff'));
$active_count = count(array_filter($staff_users, fn($u) => $u['status'] === 'active'));

function show_msg($m) {
    if ($m) {
        echo '<div class="' . ($m[0] ? 'success' : 'error') . '">' . htmlspecialchars($m[1]) . '</div>';
    }
}
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
                <?php show_msg($msg['register']); ?>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="register">
                    <?php echo csrf_field(); ?>

                    <label>Department / Name:</label>
                    <input type="text" name="staff_name" maxlength="100" placeholder="e.g. Human Resources" required>

                    <label>Account ID:</label>
                    <input type="text" name="staff_id" maxlength="20" placeholder="Used to log in, e.g. HR" required>

                    <label>Password:</label>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                    <label>Confirm Password:</label>
                    <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
                    <small class="field-help">At least 8 characters. New accounts are always staff accounts.</small>

                    <button type="submit">Register Account</button>
                </form>
            </div>

            <div class="card">
                <h3>Reset Staff Password</h3>
                <?php show_msg($msg['reset']); ?>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="reset">
                    <?php echo csrf_field(); ?>

                    <label>Select Account:</label>
                    <select name="staff_id" required>
                        <option value="">— Choose a staff account —</option>
                        <?php foreach ($staff_users as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['staff_id']); ?>">
                                <?php echo htmlspecialchars($u['staff_name']) . " (" . htmlspecialchars($u['staff_id']) . ")"
                                    . ($u['status'] === 'inactive' ? ' – inactive' : '')
                                    . ($u['is_locked'] ? ' – locked' : ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>New Password:</label>
                    <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
                    <label>Confirm New Password:</label>
                    <input type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password">
                    <small class="field-help">At least 8 characters. This also unlocks a locked account.</small>

                    <button type="submit">Update Password</button>
                </form>
            </div>

            <div class="card">
                <h3>Change My Password</h3>
                <?php show_msg($msg['own']); ?>
                <form method="POST">
                    <input type="hidden" name="action" value="change_own">
                    <?php echo csrf_field(); ?>

                    <label>Current Password:</label>
                    <input type="password" name="current_password" required autocomplete="current-password">
                    <label>New Password:</label>
                    <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
                    <label>Confirm New Password:</label>
                    <input type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password">
                    <small class="field-help">For your own admin account (<?php echo htmlspecialchars($my_id); ?>).</small>

                    <button type="submit">Change Password</button>
                </form>
            </div>

        </div>

        <div class="card accounts-card">
            <div class="accounts-head">
                <h3>Accounts</h3>
                <span class="muted"><?php echo count($staff_users); ?> staff accounts · <?php echo $active_count; ?> active</span>
                <input type="text" id="account-search" placeholder="Search name or ID..." aria-label="Search accounts">
            </div>
            <?php show_msg($msg['accounts']); ?>

            <div class="table-scroll-wrapper">
            <table id="accounts-table">
                <thead>
                    <tr><th>Account</th><th>Role</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $is_active = $u['status'] === 'active';
                    $is_me     = strcasecmp($u['staff_id'], $my_id) === 0;
                    $is_admin_row = $u['role'] === 'admin';
                    $label = $u['staff_name'] . ' (' . $u['staff_id'] . ')';
                ?>
                    <tr<?php echo $is_me ? ' class="row-me"' : ''; ?>>
                        <td>
                            <?php echo htmlspecialchars($label); ?>
                            <?php if ($is_me): ?><span class="today-badge">You</span><?php endif; ?>
                        </td>
                        <td><span class="role-tag role-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                        <td>
                            <span class="<?php echo $is_active ? 'status-active' : 'status-inactive'; ?>"><?php echo ucfirst($u['status']); ?></span>
                            <?php if ($u['is_locked']): ?>
                                <span class="status-locked" title="Too many wrong passwords">Locked until <?php echo date('g:i A', strtotime($u['locked_until'])); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if ($is_me || $is_admin_row): ?>
                                <span class="muted" title="Admin accounts can't be deactivated from this page">&#128274; Protected</span>
                            <?php else: ?>
                                <form method="POST" class="inline-form"
                                      onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(($is_active ? 'Deactivate ' : 'Reactivate ') . $label . '?' . ($is_active ? ' They will no longer be able to log in.' : '')), ENT_QUOTES); ?>);">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($u['staff_id']); ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $is_active ? 'inactive' : 'active'; ?>">
                                    <button type="submit" class="action-btn <?php echo $is_active ? 'cancel-btn' : 'edit-btn'; ?>">
                                        <?php echo $is_active ? 'Deactivate' : 'Reactivate'; ?>
                                    </button>
                                </form>
                                <?php if ($u['is_locked']): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="unlock">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($u['staff_id']); ?>">
                                        <button type="submit" class="action-btn unlock-btn">Unlock</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <script>
    // Filter the accounts table as you type
    document.getElementById('account-search').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('#accounts-table tbody tr').forEach(function (tr) {
            tr.style.display = tr.cells[0].textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
    </script>

    <?php $conn->close(); ?>
</body>
</html>
