<?php
include '../includes/config.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch user info
$query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile - Papsi Repair Shop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="card shadow p-4">
      <h3 class="mb-4 text-center text-danger">👤 My Profile</h3>

      <!-- <p><strong>Name:</strong> <?= htmlspecialchars($user['fullname']); ?></p> -->
      <p><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
      <p><strong>Username:</strong> <?= htmlspecialchars($user['username']); ?></p>

      <a href="auth/logout.php" class="btn btn-danger mt-3">Logout</a>
      <a href="../index.php" class="btn btn-secondary mt-3">Back</a>
    </div>
  </div>
</body>
</html>
