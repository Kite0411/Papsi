<?php
include '../includes/config.php';
session_start();

$conn = getDBConnection();
$message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check if username already exists
    $checkStmt = $conn->prepare("SELECT id FROM admin WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $message = '<div class="alert alert-danger">Username or Email already exists.</div>';
    } else {
        // Insert new admin
        $stmt = $conn->prepare("INSERT INTO admin (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $email, $hashedPassword, $role, $status);
        $stmt->execute();

        // Get new admin ID
        $newAdminId = $stmt->insert_id;

        // Log action
        if (isset($_SESSION['user_id'])) {
            logAudit(
                'CREATE_ADMIN',
                "Created new admin: $username",
                $_SESSION['user_id'],
                $_SESSION['username']
            );
        } else {
        }

        $message = '<div class="alert alert-success">Admin account created successfully!</div>';
        $stmt->close();
    }

    $checkStmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .create-form {
            background: white;
            padding: 40px 35px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        .btn-primary {
            background: linear-gradient(45deg, #e63946, #d90429);
            border: none;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="create-form">
        <h2>Create Admin Account</h2>

        <?= $message ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="staff">Staff</option>
                    <option value="superadmin">Super Admin</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100">Create Admin</button>
        </form>

        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-link">⬅ Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
