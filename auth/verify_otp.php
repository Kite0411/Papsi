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
    $otp = $_POST['otp'];
    if (!isset($_SESSION['reset_otp']) || !isset($_SESSION['otp_expiry']) || time() > $_SESSION['otp_expiry']) {
        $error = "OTP expired. Please try again.";
    } elseif ($otp == $_SESSION['reset_otp']) {
        $success = "OTP verified. You may now reset your password.";
    } else {
        $error = "Invalid OTP.";
    }
}

if ($success) {
    // Show password reset form
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
        <form method="post" action="reset_password.php">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify OTP - AutoFix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="login-form">
    <h2 class="text-center mb-4">Verify OTP</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Enter OTP</label>
            <input type="text" name="otp" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
    </form>
</div>
</body>
</html>