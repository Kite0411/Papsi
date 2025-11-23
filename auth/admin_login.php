<?php
include '../includes/config.php';
session_name("admin_session");
session_start();

$conn = getDBConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Both username and password are required.";
    } else {
        // ✅ Include role in query
        $stmt = $conn->prepare("SELECT id, username, email, password, role, status FROM admin WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // ✅ Check account status
            if ($user['status'] !== 'active') {
                $error = "Your account is inactive. Please contact the Super Admin.";
            } elseif (password_verify($password, $user['password'])) {
                // ✅ Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role']; // 👈 store role here
                $_SESSION['email'] = $user['email'];

                // ✅ Log successful login
                logAudit('USER_LOGIN', "User logged in: {$user['username']}", $user['id'], $user['username']);

                // ✅ Redirect to dashboard (you can customize this later)
                // ✅ Redirect based on role
                    if ($user['role'] === 'superadmin') {
                        header("Location: ../admin/index.php");
                    } elseif ($user['role'] === 'staff') {
                        header("Location: ../admin/walk_in.php");
                    } else {
                        // fallback (in case of undefined role)
                        header("Location: ../auth/login.php");
                    }
                    exit();

            } else {
                // ❌ Invalid password
                $error = "Invalid password.";
            }
        } else {
            // ❌ No user found
            $error = "User not found.";
        }

        $stmt->close();
    }
}


$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AutoFix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
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
        
        .login-form {
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
        
.login-header {
  text-align: center;
  color: #fff;
  margin-bottom: 30px;
  animation: fadeInDown 0.8s ease;
}

.login-header .brand {
  font-size: 2.8rem;
  font-weight: 700;
  color: #ff3b3f; /* your primary red */
  letter-spacing: 1px;
  text-shadow: 0 2px 10px rgba(255, 59, 63, 0.4);
}

.login-header h2 {
  font-size: 1.8rem;
  color: #727272ff;
  margin-top: 10px;
}

.login-header .subtitle {
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
    </style>
</head>
<body>
    <div class="login-form">
    <div class="login-header text-center">
            <h1><span class="brand">Papsi Paps</span></h1>
            <h2>Welcome Admin!</h2>
            <p>Login to access your Admin account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Username or Email</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    </script>
</body>
</html>

