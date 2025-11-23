<?php
// Place this BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

session_start();
include '../includes/config.php';
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        $error = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters.";
    } elseif (isset($_SESSION['reset_email'])) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed, $_SESSION['reset_email']);
        if ($stmt->execute()) {
            $success = "Password reset successful! You may now log in.";
            unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['otp_expiry']);
        } else {
            $error = "Failed to reset password.";
        }
        $stmt->close();
    } else {
        $error = "Session expired. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - AutoFix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="login-form">
    <h2 class="text-center mb-4">Reset Password</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <a href="login.php" class="btn btn-success w-100 mt-3">Go to Login</a>
    <?php endif; ?>
    <?php if (!$success): ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>