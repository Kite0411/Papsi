<?php
// Place this BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

session_start();
include '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Changed</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container">
    <h2 class="text-center">Password Changed</h2>
    <?php if(isset($_SESSION['info'])): ?>
        <div class="alert alert-success text-center"><?php echo $_SESSION['info']; ?></div>
    <?php endif; ?>
    <a href="login.php" class="btn btn-success w-100 mt-3">Go to Login</a>
</div>
</body>
</html>