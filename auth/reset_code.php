<?php
// Place this BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

session_start();
include '../includes/config.php';
$conn = getDBConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_code = mysqli_real_escape_string($conn, $_POST['otp']);
    $check_code = "SELECT * FROM users WHERE code = $otp_code";
    $code_res = mysqli_query($conn, $check_code);
    if (mysqli_num_rows($code_res) > 0) {
        $fetch_data = mysqli_fetch_assoc($code_res);
        $email = $fetch_data['email'];
        $_SESSION['email'] = $email;
        $_SESSION['info'] = "Please create a new password.";
        header('location: new_password.php');
        exit();
    } else {
        $errors[] = "You've entered incorrect code!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Code Verification</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container">
    <h2 class="text-center">Code Verification</h2>
    <?php if(isset($_SESSION['info'])): ?>
        <div class="alert alert-success text-center"><?php echo $_SESSION['info']; ?></div>
    <?php endif; ?>
    <?php if(count($errors) > 0): ?>
        <div class="alert alert-danger text-center">
            <?php foreach($errors as $error){ echo $error; } ?>
        </div>
    <?php endif; ?>
    <form action="" method="POST">
        <input class="form-control" type="number" name="otp" placeholder="Enter code" required>
        <input class="form-control button" type="submit" name="check-reset-otp" value="Submit">
    </form>
</div>
</body>
</html>