<?php
// Place this BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

session_start();
include '../includes/config.php';
$conn = getDBConnection();
$email = "";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $check_email = "SELECT * FROM users WHERE email='$email'";
    $run_sql = mysqli_query($conn, $check_email);
    if (mysqli_num_rows($run_sql) > 0) {
        $code = rand(111111, 999999);
        $insert_code = "UPDATE users SET code = $code WHERE email = '$email'";
        $run_query = mysqli_query($conn, $insert_code);
        if ($run_query) {
            $subject = "Password Reset Code";
            $message = "Your password reset code is $code";
            list($sent, $err) = sendEmail(
                $email,
                $subject,
                nl2br($message),
                $message
            );
            if ($sent) {
                $_SESSION['info'] = "We've sent a password reset OTP to your email - $email";
                $_SESSION['email'] = $email;
                header('location: reset_code.php');
                exit();
            } else {
                $errors[] = "Failed while sending code! " . $err;
            }
        } else {
            $errors[] = "Something went wrong!";
        }
    } else {
        $errors[] = "This email address does not exist!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container">
    <h2 class="text-center">Forgot Password</h2>
    <p class="text-center">Enter your email address</p>
    <?php if(count($errors) > 0): ?>
        <div class="alert alert-danger text-center">
            <?php foreach($errors as $error){ echo $error; } ?>
        </div>
    <?php endif; ?>
    <form action="" method="POST">
        <input class="form-control" type="email" name="email" placeholder="Enter email address" required value="<?php echo $email ?>">
        <input class="form-control button" type="submit" name="check-email" value="Continue">
    </form>
</div>
</body>
</html>