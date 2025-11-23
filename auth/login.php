<?php
include '../includes/config.php';
session_name("customer_session");
session_start();

$conn = getDBConnection();

$error = '';

$successMessage = '';

// Check if signup success message is passed in the URL
if (isset($_GET['signup']) && $_GET['signup'] == 'success') {
    $successMessage = "Your account has been created successfully! You can now login.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Both username and password are required.";
    } else {
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Log both simple activity and detailed audit trail
                logActivity('user_login', "User logged in: " . $user['username']);
                
                header("Location: ../index.php");
                exit();
            } else {
                $error = "Invalid password.";
                // Log failed login attempt
            }
        } else {
            $error = "User not found.";
            // Log failed login attempt
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
    <title>Login - AutoFix</title>
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
         .password-toggle-icon {
            cursor: pointer;
            position: absolute;
            top: 70%;
            right: 15px;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>
    <div class="login-form">
    <div class="login-header text-center">
    <h1><span class="brand">Papsi Paps</span></h1>
    <p class="subtitle">Sign in to manage your reservations and services</p>
    </div>

         <!-- Validation Messages: Show success or error messages here -->
        <div id="validationMessages">
            <?php if ($successMessage): ?>
                <div class="alert alert-success" id="successMessage"><?php echo $successMessage; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" id="errorMessage"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </div>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Username or Email</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            
          
             <div class="mb-3 position-relative">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" id="passwordField" required>
                <!-- Show password icon -->
                <i class="fas fa-eye password-toggle-icon" id="togglePassword"></i>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        
        <div class="divider">
            <span>OR</span>
        </div>
        
        <div class="text-center">
            <p style="color: var(--dark-gray); margin-bottom: 10px;">Don't have an account? <a href="signup.php" class="form-link">Sign up here</a></p>
            <a href="#" class="form-link" data-bs-toggle="modal" data-bs-target="#forgotModal">Forgot Password?</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    setTimeout(function() {
    var successMessage = document.getElementById('successMessage');
    var errorMessage = document.getElementById('errorMessage');
    if (successMessage) successMessage.style.display = 'none';
    if (errorMessage) errorMessage.style.display = 'none';
}, 2000);

 const togglePassword = document.getElementById('togglePassword');
            const passwordField = document.getElementById('passwordField');
            
            togglePassword.addEventListener('click', function () {
                const type = passwordField.type === 'password' ? 'text' : 'password';
                passwordField.type = type;
                // Toggle the icon
                togglePassword.classList.toggle('fa-eye-slash');
                togglePassword.classList.toggle('fa-eye');
            });

document.addEventListener('DOMContentLoaded', () => {
    const stepEmail = document.getElementById('stepEmail');
    const stepOtp = document.getElementById('stepOtp');
    const stepReset = document.getElementById('stepReset');
    const emailInput = document.getElementById('fpEmail');
    const otpInput = document.getElementById('fpOtp');
    const pass1 = document.getElementById('fpPass1');
    const pass2 = document.getElementById('fpPass2');
    const alertBox = document.getElementById('fpAlert');

    function showAlert(msg, type='danger') {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
    }

    function hideAlert() {
        alertBox.style.display = 'none';
    }

    // Helper: safely parse JSON
    async function safeJSON(res) {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch {
            throw new Error('Invalid JSON: ' + text.slice(0, 100));
        }
    }

    // --- 1️⃣ REQUEST CODE ---
    document.getElementById('btnRequest').addEventListener('click', async (e) => {
        hideAlert();
        const btn = e.currentTarget;
        const email = emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!email) return showAlert('Enter your email');
        if (!emailRegex.test(email)) return showAlert('Enter a valid email');

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Sending...`;

        try {
            const form = new FormData();
            form.append('action', 'request_code');
            form.append('email', email);
            const res = await fetch('auth_api.php', { method: 'POST', body: form });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await safeJSON(res);

            btn.disabled = false;
            btn.textContent = 'Send Code';

            if (!data.ok) return showAlert(data.error || 'Failed to send code');

            stepEmail.style.display = 'none';
            stepOtp.style.display = 'block';
            otpInput.focus();
            showAlert('Code sent successfully! Check your email.', 'success');
        } catch (err) {
            console.error(err);
            btn.disabled = false;
            btn.textContent = 'Send Code';
            showAlert('Network error: ' + err.message);
        }
    });

    // --- 2️⃣ VERIFY CODE ---
    document.getElementById('btnVerify').addEventListener('click', async (e) => {
        hideAlert();
        const btn = e.currentTarget;
        const code = otpInput.value.trim();
        if (code.length !== 6) return showAlert('Enter the 6-digit code');

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Verifying...`;

        try {
            const form = new FormData();
            form.append('action', 'verify_code');
            form.append('email', emailInput.value.trim());
            form.append('code', code);
            const res = await fetch('auth_api.php', { method: 'POST', body: form });
            const data = await safeJSON(res);

            btn.disabled = false;
            btn.textContent = 'Verify Code';

            if (!data.ok) return showAlert(data.error || 'Incorrect code');

            stepOtp.style.display = 'none';
            stepReset.style.display = 'block';
            pass1.focus();
            showAlert('Code verified successfully!', 'success');
        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Verify Code';
            showAlert('Network error: ' + err.message);
        }
    });

    // --- 3️⃣ RESET PASSWORD ---
    document.getElementById('btnReset').addEventListener('click', async (e) => {
        hideAlert();
        const btn = e.currentTarget;

        if (pass1.value !== pass2.value)
            return showAlert('Passwords do not match');

        if (pass1.value.length < <?php echo PASSWORD_MIN_LENGTH; ?>)
            return showAlert('Password too short');

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Saving...`;

        try {
            const form = new FormData();
            form.append('action', 'reset_password');
            form.append('email', emailInput.value.trim());
            form.append('password', pass1.value);
            const res = await fetch('auth_api.php', { method: 'POST', body: form });
            const data = await safeJSON(res);

            btn.disabled = false;
            btn.textContent = 'Reset Password';

            if (!data.ok) return showAlert(data.error || 'Failed to reset password');

            showAlert('Password changed successfully. You may login now.', 'success');
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('forgotModal'));
                if (modal) modal.hide();
            }, 1200);
        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Reset Password';
            showAlert('Network error: ' + err.message);
        }
    });
});
</script>


    <div class="modal fade" id="forgotModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Forgot Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="fpAlert" class="alert" style="display:none"></div>
            <div id="stepEmail">
              <label class="form-label">Email</label>
              <input type="email" id="fpEmail" class="form-control" placeholder="Enter your email" />
              <button id="btnRequest" class="btn btn-primary w-100 mt-3">Send Code</button>
            </div>
            <div id="stepOtp" style="display:none">
              <label class="form-label">Enter OTP</label>
              <input type="text" id="fpOtp" class="form-control" maxlength="6" placeholder="6-digit code" />
              <button id="btnVerify" class="btn btn-primary w-100 mt-3">Verify Code</button>
            </div>
            <div id="stepReset" style="display:none">
              <label class="form-label">New Password</label>
              <input type="password" id="fpPass1" class="form-control" />
              <label class="form-label mt-2">Confirm Password</label>
              <input type="password" id="fpPass2" class="form-control" />
              <button id="btnReset" class="btn btn-success w-100 mt-3">Change Password</button>
            </div>
          </div>
        </div>
      </div>
    </div>
</body>
</html>

