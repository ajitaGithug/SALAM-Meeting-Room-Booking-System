<?php
session_start();
if (!isset($_SESSION['staff_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
require "config.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $target_staff_id = $_POST['staff_id'];
    $new_password     = $_POST['new_password'];
    $hashed           = password_hash($new_password, PASSWORD_DEFAULT);

    $sql = "UPDATE users SET password = ? WHERE staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed, $target_staff_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = "Password updated for $target_staff_id.";
    } else {
        $message = "Staff ID not found or no change made.";
    }
    $stmt->close();
}

$users_result = $conn->query("SELECT staff_id, staff_name FROM users ORDER BY staff_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - SALAM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2>Reset Staff Password</h2>
        <?php if ($message): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="POST">
            <label>Select Staff:</label>
            <select name="staff_id" required>
                <?php while ($u = $users_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($u['staff_id']); ?>">
                        <?php echo htmlspecialchars($u['staff_name']) . " (" . $u['staff_id'] . ")"; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>New Password:</label>
            <input type="password" name="new_password" required minlength="6">

            <button type="submit">Update Password</button>
        </form>
    </div>
</body>
</html>