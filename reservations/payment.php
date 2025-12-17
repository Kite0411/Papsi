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
$vehicles = $data['vehicles'] ?? [];
$total_amount = $_SESSION['total_amount'] ?? 0;

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

$reservation_date = $data['reservation_date'];
$reservation_time = $data['reservation_time'];

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

            // 🧾 Insert reservation for EACH vehicle with THEIR services
            $reservation_ids = [];
            
            foreach ($vehicles as $index => $vehicle) {
                $vehicle_make = $vehicle['make'];
                $vehicle_model = $vehicle['model'];
                $vehicle_year = $vehicle['year'];
                $vehicle_services = $vehicle['services'];
                
                // Calculate duration for THIS vehicle's services
                $placeholders = implode(',', array_fill(0, count($vehicle_services), '?'));
                $types = str_repeat('i', count($vehicle_services));
                $duration_stmt = $conn->prepare("SELECT SUM(CAST(duration AS UNSIGNED)) AS total_duration FROM services WHERE id IN ($placeholders)");
                $duration_stmt->bind_param($types, ...$vehicle_services);
                $duration_stmt->execute();
                $total_duration = (int)$duration_stmt->get_result()->fetch_assoc()['total_duration'];
                $end_time = date("H:i:s", strtotime($reservation_time) + ($total_duration * 60));
                
                // Insert reservation
                $stmt = $conn->prepare("
                    INSERT INTO reservations 
                    (customer_id, vehicle_make, vehicle_model, vehicle_year, reservation_date, reservation_time, end_time, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_verification')
                ");
                $stmt->bind_param("issssss", $customer_id, $vehicle_make, $vehicle_model, $vehicle_year, $reservation_date, $reservation_time, $end_time);
                $stmt->execute();
                $reservation_id = $conn->insert_id;
                $stmt->close();
                
                if ($reservation_id) {
                    $reservation_ids[] = $reservation_id;
                    
                    // Insert services for THIS vehicle
                    $stmt2 = $conn->prepare("INSERT INTO reservation_services (reservation_id, service_id) VALUES (?, ?)");
                    foreach ($vehicle_services as $sid) {
                        $stmt2->bind_param("ii", $reservation_id, $sid);
                        $stmt2->execute();
                    }
                    $stmt2->close();
                }
            }

            if (!empty($reservation_ids)) {
                // Payment linked to first reservation
                $main_reservation_id = $reservation_ids[0];
                
                $stmt3 = $conn->prepare("
                    INSERT INTO payments (reservation_id, account_name, amount_paid, payment_proof, payment_status)
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt3->bind_param("isds", $main_reservation_id, $account_name, $amount_paid, $payment_proof);
                $stmt3->execute();
                $stmt3->close();

                unset($_SESSION['reservation_data'], $_SESSION['total_amount']);

                $message = "✅ Payment submitted successfully! Your reservation is pending verification.";
                $messageType = 'success';
            }
        }
    } else {
        $message = "Please upload your payment proof.";
        $messageType = 'danger';
    }
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
            padding: 30px 0;
            min-height: 100vh;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(5px);
            z-index: -1;
        }
        .payment-container {
            max-width: 900px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .payment-header {
            background: linear-gradient(135deg, #DC143C, #B71C1C);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .payment-body {
            padding: 40px 30px;
        }
        .vehicle-service-breakdown {
            background: #f8f9fa;
            border-left: 4px solid #DC143C;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        .vehicle-service-breakdown h6 {
            color: #DC143C;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .total-amount {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            padding: 20px;
            background: linear-gradient(135deg, #DC143C, #B71C1C);
            border-radius: 10px;
            text-align: center;
            margin: 25px 0;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h2>💳 Payment</h2>
            <p>Complete your multi-vehicle booking</p>
        </div>
        
        <div class="payment-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Reservation Summary -->
            <h5>📋 Reservation Summary</h5>
            <p><strong>Name:</strong> <?= htmlspecialchars($data['name']) ?></p>
            <p><strong>Date:</strong> <?= date('F d, Y', strtotime($reservation_date)) ?> at <?= date('h:i A', strtotime($reservation_time)) ?></p>
            
            <hr>
            
            <h5>🚗 Vehicles & Services Breakdown</h5>
            <?php 
            $grand_total = 0;
            foreach ($vehicles as $index => $vehicle): 
                // Get services for this vehicle
                $service_ids = $vehicle['services'];
                $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
                $types = str_repeat('i', count($service_ids));
                $stmt = $conn->prepare("SELECT service_name, price FROM services WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$service_ids);
                $stmt->execute();
                $result = $stmt->get_result();
                $vehicle_services = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                $vehicle_total = array_sum(array_column($vehicle_services, 'price'));
                $grand_total += $vehicle_total;
            ?>
                <div class="vehicle-service-breakdown">
                    <h6>Vehicle #<?= $index + 1 ?>: <?= htmlspecialchars($vehicle['make']) ?> <?= htmlspecialchars($vehicle['model']) ?> (<?= htmlspecialchars($vehicle['year']) ?>)</h6>
                    <ul>
                        <?php foreach ($vehicle_services as $svc): ?>
                            <li><?= htmlspecialchars($svc['service_name']) ?> - ₱<?= number_format($svc['price'], 2) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <strong>Subtotal: ₱<?= number_format($vehicle_total, 2) ?></strong>
                </div>
            <?php endforeach; ?>

            <div class="total-amount">
                Grand Total: ₱<?php echo number_format($grand_total, 2); ?>
            </div>

            <!-- Payment Form -->
            <form method="post" enctype="multipart/form-data">
                <h5>📤 Upload Payment Proof</h5>
                <input type="file" name="payment_proof" class="form-control mb-3" accept="image/*" required>
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">GCash Account Name</label>
                        <input type="text" name="account_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount Paid</label>
                        <input type="number" name="amount_paid" class="form-control" step="0.01" value="<?php echo $grand_total; ?>" required>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" name="submit_payment" class="btn btn-primary">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
