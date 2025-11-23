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
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $cpassword = mysqli_real_escape_string($conn, $_POST['cpassword']);
    if ($password !== $cpassword) {
        $errors[] = "Confirm password not matched!";
    } else {
        $email = $_SESSION['email'];
        $encpass = password_hash($password, PASSWORD_DEFAULT);
        $update_pass = "UPDATE users SET code = 0, password = '$encpass' WHERE email = '$email'";
        $run_query = mysqli_query($conn, $update_pass);
        if ($run_query) {
            $_SESSION['info'] = "Your password changed. Now you can login with your new password.";
            header('Location: password_changed.php');
            exit();
        } else {
            $errors[] = "Failed to change your password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create a New Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container">
    <h2 class="text-center">New Password</h2>
    <?php if(isset($_SESSION['info'])): ?>
        <div class="alert alert-success text-center"><?php echo $_SESSION['info']; ?></div>
    <?php endif; ?>
    <?php if(count($errors) > 0): ?>
        <div class="alert alert-danger text-center">
            <?php foreach($errors as $error){ echo $error; } ?>
        </div>
    <?php endif; ?>
    <form action="" method="POST">
        <input class="form-control" type="password" name="password" placeholder="Create new password" required>
        <input class="form-control" type="password" name="cpassword" placeholder="Confirm your password" required>
        <input class="form-control button" type="submit" name="change-password" value="Change">
    </form>
</div>
</body>
</html>