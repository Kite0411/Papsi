<?php
include '../includes/config.php';
session_start();
$conn = getDBConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!validateEmail($email)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($insert_stmt->execute()) {
                // Account created successfully, redirect to login page with success message
                header("Location: login.php?signup=success");  // Pass success message as query parameter
                exit;
            } else {
                $error = "Error creating account. Please try again.";
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - AutoFix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> <!-- Font Awesome for icons -->

    <style>
        body {
            background: url('../bg/lbg.jpg') center/cover no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 30px 0;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55); /* dark tint */
            backdrop-filter: blur(6px); /* blur effect */
            z-index: -1;
        }

        .signup-form {
            background: white;
            padding: 50px 40px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .signup-header {
            text-align: center;
            color: #fff;
            margin-bottom: 30px;
            animation: fadeInDown 0.8s ease;
        }

        .signup-header .brand {
            font-size: 2.8rem;
            font-weight: 700;
            color: #ff3b3f; /* your primary red */
            letter-spacing: 1px;
            text-shadow: 0 2px 10px rgba(255, 59, 63, 0.4);
        }

        .signup-header h2 {
            font-size: 1.8rem;
            color: #727272ff;
            margin-top: 10px;
        }

        .signup-header .subtitle {
            font-size: 1rem;
            color: #646464ff;
            margin-top: 5px;
            font-style: italic;
        }

        /* Smooth entrance animation */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-control {
            border-radius: var(--radius-md);
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            transition: var(--transition-fast);
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
        }

        .form-label {
            font-weight: 600;
            color: var(--black);
            margin-bottom: 8px;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: var(--radius-md);
            padding: 14px;
            font-weight: 700;
            transition: var(--transition-normal);
            font-size: 1.05rem;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e0e0e0;
        }

        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .form-link {
            color: var(--primary-red);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition-fast);
        }

        .form-link:hover {
            color: var(--primary-red-dark);
            text-decoration: underline;
        }

        .alert {
            border-radius: var(--radius-md);
            border: none;
            padding: 15px 20px;
        }
           .password-toggle-icon {
            cursor: pointer;
            position: absolute;
            top: 55%;
            right: 20px;
            transform: translateY(-50%);
        }
        .password-toggle-icon2 {
            cursor: pointer;
            position: absolute;
            top: 70%;
            right: 20px;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>
    <div class="signup-form">
        <div class="signup-header text-center">
            <h1><span class="brand">Papsi Paps</span></h1>
            <p>New here? Create your account and join the ride!</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
           <div class="mb-3 position-relative">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" id="passwordField" required>
                <i class="fas fa-eye password-toggle-icon" id="togglePassword"></i> <!-- Eye Icon -->
                <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
            </div>
            
            <div class="mb-3 position-relative">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control"  id="passwordField2" required>
                <i class="fas fa-eye password-toggle-icon2" id="togglePassword2"></i> <!-- Eye Icon -->

            </div>
            
            <button type="submit" class="btn btn-primary w-100">Create Account</button>
        </form>
        
        <div class="divider">
            <span>OR</span>
        </div>
        
        <div class="text-center">
            <p style="color: var(--dark-gray); margin-bottom: 0;">Already have an account? <a href="login.php" class="form-link">Login here</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script> 
            const togglePassword = document.getElementById('togglePassword', 'togglePassword2');
            const passwordField = document.getElementById('passwordField','passwordField2');
            
            togglePassword.addEventListener('click', function () {
                const type = passwordField.type === 'password' ? 'text' : 'password';
                passwordField.type = type;
                // Toggle the icon
                togglePassword.classList.toggle('fa-eye-slash');
                togglePassword.classList.toggle('fa-eye');
            });
            togglePassword2.addEventListener('click', function () {
                const type = passwordField2.type === 'password' ? 'text' : 'password';
                passwordField2.type = type;
                // Toggle the icon
                togglePassword2.classList.toggle('fa-eye-slash');
                togglePassword2.classList.toggle('fa-eye');
            });
    </script>
</body>
</html>
