<?php
session_name("customer_session");
session_start();

include '../includes/config.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION['reservation_data'])) {
    header("Location: reservation.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$data = $_SESSION['reservation_data'];
$services = $_SESSION['selected_services'] ?? [];
$total_amount = $_SESSION['total_amount'] ?? 0;

// 🧩 Convert service IDs to full service info if needed
if (!empty($services) && is_array($services) && is_numeric(reset($services))) {
    $placeholders = implode(',', array_fill(0, count($services), '?'));
    $types = str_repeat('i', count($services));
    $stmt = $conn->prepare("SELECT id, service_name, price, duration FROM services WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$services);
    $stmt->execute();
    $result = $stmt->get_result();
    $services = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Extract service IDs for reservation_services insert
$selected_services = array_column($services, 'id');

// 🧮 Calculate total duration to compute end_time
$total_duration = 0;
foreach ($services as $srv) {
    $total_duration += (int)$srv['duration'];
}
$reservation_time = $data['reservation_time'];
$end_time = date("H:i:s", strtotime($reservation_time) + ($total_duration * 60));

function debugLog($msg) {
    if (!file_exists('logs')) mkdir('logs', 0777, true);
    file_put_contents('logs/payment_debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// Get or create customer
$stmt = $conn->prepare("SELECT id FROM customers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $customer_id = $result->fetch_assoc()['id'];
} else {
    $stmt2 = $conn->prepare("INSERT INTO customers (user_id, name, phone, email) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("isss", $user_id, $data['name'], $data['phone'], $data['email']);
    $stmt2->execute();
    $customer_id = $conn->insert_id;
    $stmt2->close();
}
$stmt->close();

$vehicle_make = $data['vehicle_make'];
$vehicle_model = $data['vehicle_model'];
$vehicle_year = $data['vehicle_year'];
$reservation_date = $data['reservation_date'];

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {

    $account_name = sanitizeInput($_POST['account_name']);
    $amount_paid = floatval($_POST['amount_paid']);

    $upload_dir = '../uploads/payments/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            $payment_proof = uniqid('proof_') . '.' . $ext;
            $path = $upload_dir . $payment_proof;
            move_uploaded_file($_FILES['payment_proof']['tmp_name'], $path);

            // 🧾 Insert reservation (✅ with end_time)
            $stmt = $conn->prepare("
                INSERT INTO reservations 
                (customer_id, vehicle_make, vehicle_model, vehicle_year, reservation_date, reservation_time, end_time, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_verification')
            ");
            $stmt->bind_param(
                "issssss",
                $customer_id,
                $vehicle_make,
                $vehicle_model,
                $vehicle_year,
                $reservation_date,
                $reservation_time,
                $end_time
            );
            $stmt->execute();
            $reservation_id = $conn->insert_id;
            $stmt->close();

            if ($reservation_id) {

                // 💡 Insert selected services
                if (!empty($selected_services)) {
                    $stmt2 = $conn->prepare("INSERT INTO reservation_services (reservation_id, service_id) VALUES (?, ?)");
                    foreach ($selected_services as $sid) {
                        $stmt2->bind_param("ii", $reservation_id, $sid);
                        $stmt2->execute();
                    }
                    $stmt2->close();
                }

                // 💰 Insert payment
                $stmt3 = $conn->prepare("
                    INSERT INTO payments (reservation_id, account_name, amount_paid, payment_proof, payment_status)
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt3->bind_param("isds", $reservation_id, $account_name, $amount_paid, $payment_proof);
                $stmt3->execute();
                $stmt3->close();

                unset($_SESSION['reservation_data'], $_SESSION['selected_services'], $_SESSION['total_amount']);

                $message = "✅ Payment submitted successfully! Your reservation is pending verification.";
                $messageType = 'success';

            } else {
                $message = "❌ Failed to create reservation. Please try again.";
                $messageType = 'danger';
                debugLog("Reservation insert failed: " . $conn->error);
            }
        } else {
            $message = "Invalid file type. Please upload JPG, PNG, or GIF.";
            $messageType = 'danger';
        }
    } else {
        $message = "Please upload your payment proof.";
        $messageType = 'danger';
    }
}

if (isset($_POST['cancel_payment'])) {
    unset($_SESSION['reservation_data'], $_SESSION['selected_services'], $_SESSION['total_amount']);
    header("Location: reservation.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - AutoFix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
        background: url('../bg/rbg.jpg') no-repeat center center fixed;
        background-size: cover;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 30px 0;
        color: #222;
        min-height: 100vh;
        }

        /* Optional overlay for better text contrast */
        body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.55); /* semi-transparent dark */
        backdrop-filter: blur(5px); /* <-- adds the blur */
        -webkit-backdrop-filter: blur(5px); /* for Safari support */
        z-index: -1;
        }
        
        .payment-container {
            max-width: 900px;
            margin: 50px auto;
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid rgba(184, 97, 97, 0.86);
        }
        
        .payment-header {
            background: var(--gradient-primary);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .payment-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 20px solid var(--primary-red-dark);
        }
        
        .payment-header h2 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 10px 0;
            color: white;
        }
        
        .payment-header p {
            margin: 0;
            opacity: 0.95;
            font-size: 1.1rem;
            color: white;
        }
        
        .payment-body {
            padding: 50px 40px 40px;
        }
        
        .qr-placeholder {
            background: #FFEBEE;
            border: 3px dashed var(--primary-red);
            border-radius: var(--radius-lg);
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: var(--transition-normal);
        }
        
        .qr-placeholder:hover {
            border-color: var(--primary-red-dark);
            box-shadow: var(--shadow-md);
        }
        
        .qr-placeholder h4 {
            color: var(--primary-red);
            font-weight: 700;
        }
        
        .service-summary {
            background: var(--light-gray);
            padding: 25px;
            border-radius: var(--radius-lg);
            margin-bottom: 25px;
            border: 2px solid rgba(220, 20, 60, 0.1);
        }
        
        .service-summary h4 {
            color: var(--primary-red);
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .service-summary h5 {
            color: var(--primary-red);
            font-weight: 700;
            margin-top: 20px;
        }
        
        .service-summary hr {
            border-color: var(--primary-red);
            opacity: 0.3;
        }
        
        .total-amount {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            padding: 20px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            text-align: center;
            margin: 25px 0;
            box-shadow: var(--shadow-md);
        }
        
        .upload-area {
            border: 3px dashed var(--primary-red);
            border-radius: var(--radius-lg);
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition-normal);
            background: #FFEBEE;
        }
        
        .upload-area:hover {
            background: white;
            border-color: var(--primary-red-dark);
            box-shadow: var(--shadow-md);
        }
        
        .upload-area.dragover {
            background: white;
            border-color: var(--primary-red-dark);
            box-shadow: var(--shadow-lg);
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            margin-top: 15px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }
        
        .btn-submit {
            background: var(--gradient-primary);
            border: none;
            padding: 15px 50px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: var(--radius-md);
            transition: var(--transition-normal);
            letter-spacing: 0.5px;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .instruction-box {
            background: #FFF3E0;
            border-left: 4px solid #FF9800;
            padding: 20px;
            border-radius: var(--radius-md);
            margin: 25px 0;
        }
        
        .instruction-box h5 {
            color: #E65100;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .form-control {
            border-radius: var(--radius-md);
            border: 2px solid #e0e0e0;
            transition: var(--transition-fast);
            padding: 12px 15px;
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
        
        .alert {
            border-radius: var(--radius-md);
            border: none;
            padding: 15px 20px;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h2>Payment</h2>
        </div>
        
        <div class="payment-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Reservation Summary -->
            <div class="service-summary">
                <h4>📋 Reservation Details</h4>
                <hr>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <?= htmlspecialchars($data['name'] ?? '') ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($data['email'] ?? '') ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($data['phone'] ?? '') ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Vehicle:</strong>
                        <?= htmlspecialchars(($data['vehicle_make'] ?? '') . ' ' . ($data['vehicle_model'] ?? '') . ' (' . ($data['vehicle_year'] ?? '') . ')') ?>
                    </p>
                    <p><strong>Date:</strong>
                        <?= !empty($data['reservation_date']) ? date('F d, Y', strtotime($data['reservation_date'])) : '' ?>
                    </p>
                    <p><strong>Time:</strong>
                        <?= !empty($data['reservation_time']) ? date('h:i A', strtotime($data['reservation_time'])) : '' ?>
                    </p>
                </div>
            </div>

                
                <h5 class="mt-3">Selected Services:</h5>
                <ul>
                <?php if (!empty($services)): ?>
                    <?php foreach ($services as $service): ?>
                        <li>
                            <?php echo htmlspecialchars($service['service_name']); ?> - 
                            ₱<?php echo number_format($service['price'], 2); ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No selected services found.</li>
                <?php endif; ?>

                </ul>
            </div>

            <div class="total-amount">
                Total Amount: ₱<?php echo number_format($total_amount, 2); ?>
            </div>

            <!-- Payment Instructions -->
            <div class="instruction-box">
                <h5>📱 Payment Instructions:</h5>
                <ol>
                    <li>Scan the QR code below using your GCash app</li>
                    <li>Send the exact amount: <strong>₱<?php echo number_format($total_amount, 2); ?></strong></li>
                    <li>Take a screenshot of the payment confirmation</li>
                    <li>Upload the screenshot below</li>
                    <li>Fill in your GCash account name and amount paid</li>
                </ol>
            </div>

            <!-- QR Code Placeholder -->
            <div class="qr-placeholder">
                <h4>📱 GCash QR Code</h4>
                <p class="text-muted">Scan this QR code with your GCash app</p>
                <div style="background: white; padding: 20px; display: inline-block; border-radius: 10px; margin: 20px 0;">
                    <div style="width: 200px; height: 200px; background: #e0e0e0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                        <span style="font-size: 14px; color: #666;">QR Code Placeholder<br>(Will be replaced with actual QR)</span>
                    </div>
                </div>
                <p class="mt-3"><strong>Note:</strong> The actual GCash QR code will be displayed here</p>
            </div>

            <!-- Payment Form -->
            <form method="post" enctype="multipart/form-data" id="paymentForm">
                <h5 class="mt-4">📤 Upload Payment Proof</h5>
                
                <div class="upload-area" id="uploadArea">
                    <input type="file" name="payment_proof" id="payment_proof" accept="image/*" style="display: none;">
                    <div id="uploadText">
                        <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" fill="#DC143C" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/>
                        </svg>
                        <p class="mt-3"><strong>Click to upload</strong> or drag and drop</p>
                        <p class="text-muted">PNG, JPG, GIF up to 10MB</p>
                    </div>
                    <img id="imagePreview" class="preview-image" style="display: none;">
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <label class="form-label"><strong>GCash Account Name</strong></label>
                        <input type="text" name="account_name" class="form-control" placeholder="Enter your GCash account name" required>
                        <small class="text-muted">Enter the name registered on your GCash account</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><strong>Amount Paid</strong></label>
                        <input type="number" name="amount_paid" class="form-control" step="0.01" value="<?php echo $total_amount; ?>" placeholder="Enter amount paid" required>
                        <small class="text-muted">Enter the exact amount you sent via GCash</small>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" name="submit_payment" class="btn btn-primary btn-submit">Submit Payment</button>
                    <a href="../index.php" class="btn btn-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('payment_proof');
        const imagePreview = document.getElementById('imagePreview');
        const uploadText = document.getElementById('uploadText');

        // Click to upload
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            handleFile(e.target.files[0]);
        });

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            fileInput.files = e.dataTransfer.files;
            handleFile(file);
        });

        function handleFile(file) {
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    uploadText.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', (e) => {
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please upload payment proof screenshot');
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const alertBox = document.querySelector('.alert');
            if (alertBox) {
                let seconds = 5; // duration before closing + redirect
                const countdown = document.createElement('small');
                countdown.classList.add('text-muted', 'ms-2');
                countdown.innerHTML = `(Redirecting in ${seconds}s...)`;
                alertBox.appendChild(countdown);

                const timer = setInterval(() => {
                    seconds--;
                    countdown.innerHTML = `(Redirecting in ${seconds}s...)`;
                    if (seconds <= 0) {
                        clearInterval(timer);
                        const bsAlert = new bootstrap.Alert(alertBox);
                        bsAlert.close();

                        // Redirect after fade
                        setTimeout(() => {
                            window.location.href = "../index.php"; // ← change target here if needed
                        }, 500);
                    }
                }, 1000);
            }
        });
    </script>
</body>
</html>
