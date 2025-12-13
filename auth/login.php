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
            }
        } else {
            $error = "User not found.";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(6px);
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
    color: #ff3b3f;
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

/* Enhanced Modal Styling */
.modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-header {
    background: linear-gradient(135deg, #e63946 0%, #d90429 100%);
    color: white;
    border-radius: 16px 16px 0 0;
    padding: 20px 30px;
    border: none;
}

.modal-title {
    font-weight: 700;
    font-size: 1.5rem;
}

.btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
}

.btn-close:hover {
    opacity: 1;
}

.modal-body {
    padding: 30px;
}

#fpAlert {
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 20px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-body .form-control {
    padding: 12px 16px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    font-size: 1rem;
}

.modal-body .form-control:focus {
    border-color: #e63946;
    box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
}

.modal-body .btn {
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.modal-body .btn-primary {
    background: linear-gradient(135deg, #e63946 0%, #d90429 100%);
}

.modal-body .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(230, 57, 70, 0.3);
}

.modal-body .btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
}

.modal-body .btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.modal-body .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
}

/* Step indicators */
#stepEmail, #stepOtp, #stepReset {
    animation: fadeIn 0.4s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

/* OTP input styling */
#fpOtp {
    text-align: center;
    font-size: 1.5rem;
    letter-spacing: 8px;
    font-weight: 600;
}
    </style>
</head>
<body>
    <div class="login-form">
        <div class="login-header text-center">
            <h1><span class="brand">Papsi Paps</span></h1>
            <p class="subtitle">Sign in to manage your reservations and services</p>
        </div>

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

    <!-- Enhanced Forgot Password Modal -->
    <div class="modal fade" id="forgotModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">🔐 Reset Your Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="fpAlert" class="alert" style="display:none"></div>
                    
                    <!-- Step 1: Email -->
                    <div id="stepEmail">
                        <p class="text-muted mb-3">Enter your email address and we'll send you a verification code.</p>
                        <label class="form-label">Email Address</label>
                        <input type="email" id="fpEmail" class="form-control" placeholder="your.email@example.com" />
                        <button id="btnRequest" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-paper-plane me-2"></i>Send Verification Code
                        </button>
                    </div>
                    
                    <!-- Step 2: OTP -->
                    <div id="stepOtp" style="display:none">
                        <p class="text-muted mb-3">We've sent a 6-digit code to your email. Please enter it below.</p>
                        <label class="form-label">Verification Code</label>
                        <input type="text" id="fpOtp" class="form-control" maxlength="6" placeholder="000000" inputmode="numeric" pattern="[0-9]*" />
                        <small class="text-muted d-block mt-2">Code expires in 10 minutes</small>
                        <button id="btnVerify" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-check-circle me-2"></i>Verify Code
                        </button>
                        <button id="btnResend" class="btn btn-outline-secondary w-100 mt-2" style="display:none">
                            <i class="fas fa-redo me-2"></i>Resend Code
                        </button>
                    </div>
                    
                    <!-- Step 3: Reset Password -->
                    <div id="stepReset" style="display:none">
                        <p class="text-success mb-3"><i class="fas fa-check-circle me-2"></i>Verified! Now create your new password.</p>
                        <label class="form-label">New Password</label>
                        <input type="password" id="fpPass1" class="form-control mb-3" placeholder="Enter new password" />
                        <label class="form-label">Confirm Password</label>
                        <input type="password" id="fpPass2" class="form-control" placeholder="Confirm new password" />
                        <small class="text-muted d-block mt-2">Must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                        <button id="btnReset" class="btn btn-success w-100 mt-3">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </button>
                    </div>
                </div>
            </div>
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
        const icons = {
            danger: '❌',
            warning: '⚠️',
            success: '✅',
            info: 'ℹ️'
        };
        alertBox.className = 'alert alert-' + type;
        alertBox.innerHTML = `${icons[type] || ''} ${msg}`;
        alertBox.style.display = 'block';
    }

    function hideAlert() {
        alertBox.style.display = 'none';
    }

    async function safeJSON(res) {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch {
            throw new Error('Server returned invalid response. Please try again.');
        }
    }

    function getErrorMessage(error, code) {
        const errorMessages = {
            'EMAIL_NOT_FOUND': 'No account found with this email address.',
            'INVALID_EMAIL': 'Please enter a valid email address.',
            'CODE_EXPIRED': 'Your verification code has expired. Please request a new one.',
            'INCORRECT_CODE': 'Incorrect code. Please check and try again.',
            'PASSWORD_TOO_SHORT': 'Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.',
            'OTP_NOT_VERIFIED': 'Please verify your email first.',
            'EMAIL_SERVICE_UNAVAILABLE': 'Email service is temporarily unavailable. Please try again later.',
            'EMAIL_SEND_ERROR': 'Failed to send email. Please check your email address and try again.',
            'DB_CONNECTION_ERROR': 'Database connection error. Please try again later.',
            'CONFIG_ERROR': 'Service temporarily unavailable. Please try again later.'
        };
        return errorMessages[code] || error || 'An unexpected error occurred. Please try again.';
    }

    // REQUEST CODE
    document.getElementById('btnRequest').addEventListener('click', async (e) => {
        hideAlert();
        const btn = e.currentTarget;
        const email = emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!email) return showAlert('Please enter your email address', 'warning');
        if (!emailRegex.test(email)) return showAlert('Please enter a valid email address', 'warning');

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Sending code...`;

        try {
            const form = new FormData();
            form.append('action', 'request_code');
            form.append('email', email);
            const res = await fetch('auth_api.php', { method: 'POST', body: form });
            const data = await safeJSON(res);

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Verification Code';

            if (!data.ok) {
                return showAlert(getErrorMessage(data.error, data.code), 'danger');
            }

            stepEmail.style.display = 'none';
            stepOtp.style.display = 'block';
            otpInput.focus();
            showAlert('Verification code sent! Please check your email.', 'success');
        } catch (err) {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Verification Code';
            showAlert('Network error. Please check your connection and try again.', 'danger');
        }
    });

    // VERIFY CODE
    document.getElementById('btnVerify').addEventListener('click', async (e) => {
        hideAlert();
        const btn = e.currentTarget;
        const code = otpInput.value.trim();
        if (code.length !== 6) return showAlert('Please enter the complete 6-digit code', 'warning');

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Verifying...`;

        try {
            const form = new FormData();
            form.append('action', 'verify_code');
            form.append('email', emailInput.value.trim());
            form.append('code', code);
            const res = await fetch('auth_api.php', { method: 'POST', body: form });
            const data = await safeJSON(res);

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Verify Code';

            if (!data.ok) {
                return showAlert(getErrorMessage(data.error, data.code), 'danger');
            }

            stepOtp.style.display = 'none';
            stepReset.style.display = 'block';
            pass1.focus();
            showAlert('Code verified successfully!', 'success');
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Verify Code';
            showAlert('Network error. Please check your connection and try again.', 'danger');
        }
    });

    // RESET PASSWORD
    document.getElementById('btnReset').addEventListener('click', async (e) => {
        hideAlert();
        const btn = e.currentTarget;

        if (pass1.value !== pass2.value)
            return showAlert('Passwords do not match', 'warning');

        if (pass1.value.length < <?php echo PASSWORD_MIN_LENGTH; ?>)
            return showAlert('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters', 'warning');

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Saving...`;

        try {
            const form = new FormData();
            form.append('action', 'reset_password');
            form.append('email', emailInput.value.trim());
            form.append('password', pass1.value);
            const res = await fetch('auth_api.php', { method: 'POST', body: form });
            const data = await safeJSON(res);

            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-key me-2"></i>Reset Password';

            if (!data.ok) {
                return showAlert(getErrorMessage(data.error, data.code), 'danger');
            }

            showAlert('Password changed successfully! Redirecting to login...', 'success');
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('forgotModal'));
                if (modal) modal.hide();
                window.location.reload();
            }, 2000);
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-key me-2"></i>Reset Password';
            showAlert('Network error. Please check your connection and try again.', 'danger');
        }
    });

    // Auto-format OTP input
    otpInput.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
    });

    // Reset modal on close
    document.getElementById('forgotModal').addEventListener('hidden.bs.modal', () => {
        stepEmail.style.display = 'block';
        stepOtp.style.display = 'none';
        stepReset.style.display = 'none';
        emailInput.value = '';
        otpInput.value = '';
        pass1.value = '';
        pass2.value = '';
        hideAlert();
    });
});
    </script>
</body>
</html>
