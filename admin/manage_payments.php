<?php
include '../includes/config.php';
session_name("admin_session");
session_start();

$conn = getDBConnection();

if (isset($_POST['verify_payment'])) {
    $payment_id = $_POST['payment_id'];
    $action = $_POST['action']; // 'verified' or 'rejected'
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $admin_id = $_SESSION['user_id'] ?? 0;
    $admin_username = $_SESSION['username'] ?? 'System';

    // --- Update payment status ---
    $stmt = $conn->prepare("
        UPDATE payments 
        SET payment_status = ?, verified_by = ?, verified_at = NOW(), notes = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("sisi", $action, $admin_id, $notes, $payment_id);

    if ($stmt->execute()) {

        // --- Fetch payment + customer info ---
        $stmt2 = $conn->prepare("
            SELECT 
                p.reservation_id,
                c.email AS customer_email,
                c.name AS customer_name,
                r.reservation_date,
                r.reservation_time
            FROM payments p
            JOIN reservations r ON p.reservation_id = r.id
            JOIN customers c ON r.customer_id = c.id
            WHERE p.id = ?
        ");
        $stmt2->bind_param("i", $payment_id);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $payment = $result->fetch_assoc();

        // --- If verified, confirm reservation ---
        if ($action === 'verified' && $payment) {
            $update_reservation = $conn->prepare("UPDATE reservations SET status = 'confirmed' WHERE id = ?");
            $update_reservation->bind_param("i", $payment['reservation_id']);
            $update_reservation->execute();

            // --- Send email notification ---
            if (function_exists('sendEmail')) {
                $to = $payment['customer_email'];
                $name = htmlspecialchars($payment['customer_name']);
                $date = htmlspecialchars($payment['reservation_date']);
                $time = htmlspecialchars($payment['reservation_time']);
                $subject = "Payment Verified - Reservation Confirmed";
                $body_html = "
                    <p>Dear <b>$name</b>,</p>
                    <p>Your payment for your reservation on <b>$date</b> at <b>$time</b> has been <b>verified</b>.</p>
                    <p>Your reservation is now <b>confirmed</b>. We look forward to serving you!</p>
                    <p>Thank you,<br><b>AutoRepair Center</b></p>
                ";
                $body_text = "Dear $name,\n\nYour payment for your reservation on $date at $time has been verified.\nYour reservation is now confirmed.\n\nThank you,\nAutoRepair Center";

                list($sent, $err) = sendEmail($to, $subject, $body_html, $body_text);
                if (!$sent) {
                    error_log("Failed to send payment verification email to $to: $err");
                }
            } else {
                error_log("sendEmail() function not found — unable to notify customer.");
            }
        }

        // --- Clean readable audit trail entry ---
        $desc = "Admin '$admin_username' " . ($action === 'verified' ? "verified" : "rejected") . " payment #$payment_id.";
        logAudit('PAYMENT_' . strtoupper($action), $desc, $admin_id, $admin_username);

        // --- Success message ---
        $_SESSION['notif'] = [
            'message' => "Payment " . ($action === 'verified' ? 'verified' : 'rejected') . " successfully!",
            'type' => $action === 'verified' ? 'success' : 'error'
        ];
        } else {
            $_SESSION['notif'] = [
                'message' => "Error updating payment status.",
                'type' => 'error'
            ];
        }

        // Redirect to avoid form resubmission and show toast
        header("Location: manage_payments.php");
        exit();
}

// --- Fetch all payments with reservation details ---
// --- Handle filtering ---
$status = $_GET['status'] ?? 'all';
$validStatuses = ['pending', 'verified', 'rejected'];

$baseQuery = "
    SELECT p.*, 
           r.reservation_date, r.reservation_time, 
           r.vehicle_make, r.vehicle_model,
           c.name AS customer_name, c.email, c.phone,
           u.username AS verified_by_name
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.id
    JOIN customers c ON r.customer_id = c.id
    LEFT JOIN users u ON p.verified_by = u.id
";

if (in_array($status, $validStatuses)) {
    $query = $baseQuery . " WHERE p.payment_status = '$status' ORDER BY p.created_at DESC";
} else {
    $query = $baseQuery . " ORDER BY p.created_at DESC";
}

$payments = mysqli_query($conn, $query);


$payments = mysqli_query($conn, $query);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: var(--light-gray);
        }
        .navbar {
            background: white;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            padding: 15px 30px;
            align-items: center;
            border-bottom: 3px solid var(--primary-red);
        }
        
        .navbar .logo {
            color: var(--primary-red);
            font-size: 1.8rem;
            font-weight: 800;
        }
        
        .navbar ul {
            list-style: none;
            display: flex;
            gap: 15px;
            margin: 0;
            padding: 0;
            flex-wrap: wrap;
        }
        
        .navbar ul li a {
            color: var(--dark-gray);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 15px;
            border-radius: var(--radius-md);
            transition: var(--transition-fast);
        }
        
        .navbar ul li a.active, .navbar ul li a:hover {
            background: var(--gradient-primary);
            color: white;
        }
        .payment-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-md);
            border: 2px solid rgba(220, 20, 60, 0.1);
            transition: var(--transition-normal);
        }
        .payment-card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-red);
        }
        .status-badge {
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .status-pending {
            background: #FFF3E0;
            color: #E65100;
        }
        .status-verified {
            background: #E8F5E9;
            color: #2E7D32;
        }
        .status-rejected {
            background: #FFEBEE;
            color: var(--primary-red);
        }
        .payment-proof {
            max-width: 300px;
            border-radius: 8px;
            cursor: pointer;
        }
        .modal-img {
            max-width: 100%;
            border-radius: 8px;
        }

        .notif-toast {
            position: fixed;
            top: 25px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            color: white;
            padding: 15px 35px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            opacity: 0;
            transition: opacity 0.4s ease, transform 0.4s ease;
            z-index: 9999;
        }
        .notif-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        .filter-bar {
            border-left: 5px solid #0d6efd;
            border-radius: 10px;
            background: #f8f9fa;
        }

        .filter-bar {
            border-left: 5px solid #0d6efd;
            border-radius: 10px;
            background: #f8f9fa;
            transition: none !important; /* disable animation */
        }

        .filter-bar:hover {
            background: #f8f9fa !important; /* keep same background on hover */
            box-shadow: none !important;    /* no shadow change */
            transform: none !important;     /* no lift or scale */
        }


        .filter-btn {
            min-width: 140px;
            font-weight: 600;
            transition: all 0.25s ease;
            border-radius: 50px;
            text-decoration: none !important;
        }

        .filter-btn:hover {
            text-decoration: none !important;
        }

        .filter-btn i {
            transition: transform 0.25s ease;
        }

        .filter-btn:hover i {
            transform: scale(1.15);
        }

        .filter-btn.active {
            box-shadow: 0 0 10px rgba(13, 110, 253, 0.4);
        }

        .filter-btn .badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
<?php if (isset($_SESSION['notif'])): 
    $type = $_SESSION['notif']['type'];
    $message = $_SESSION['notif']['message'];
    $color = $type === 'success' ? '#28a745' : '#dc3545'; // green or red
?>
<div class="notif-toast" id="notifToast" style="background: <?php echo $color; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php unset($_SESSION['notif']); endif; ?>
<!-- Navbar -->
<nav class="navbar">
    <div class="logo"> Papsi Paps Admin</div>
    <ul>
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="index.php">Dashboard</a></li>
        <?php endif; ?>
        <!-- Always visible -->
        <li><a href="walk_in.php">Manage Walk-In</a></li>
        <li><a href="manage_payments.php" class="active">Payments</a></li>
                          <?php if ($_SESSION['role'] === 'superadmin'): ?>

        <li><a href="manage_services.php">Manage Services</a></li>
                <?php endif; ?>

        <li><a href="manage_reservations.php">Reservations</a></li>

        <!-- Only visible to Super Admin -->
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="audit_trail.php">Audit Trail</a></li>
        <?php endif; ?>

        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>
</nav>

<div class="container mt-4">
    <h2 class="mb-4">
        <i class="fas fa-money-bill-wave me-2"></i>Payment Management
    </h2>
<!-- Payment Filter Bar -->
<div class="filter-bar card shadow-sm p-3 mb-4 text-center">
    <h5 class="mb-3 text-secondary fw-bold">
        <i class="fas fa-filter me-2"></i>Filter Payments by Status
    </h5>
    <div class="d-flex justify-content-center flex-wrap gap-2">
        <a href="?status=all" 
           class="filter-btn btn <?= (!isset($_GET['status']) || $_GET['status'] === 'all') ? 'btn-primary active' : 'btn-outline-primary' ?>">
            <i class="fas fa-list me-1"></i> All
        </a>

        <a href="?status=pending" 
           class="filter-btn btn <?= ($_GET['status'] ?? '') === 'pending' ? 'btn-warning active' : 'btn-outline-warning' ?>">
            <i class="fas fa-hourglass-half me-1"></i> Pending
        </a>

        <a href="?status=verified" 
           class="filter-btn btn <?= ($_GET['status'] ?? '') === 'verified' ? 'btn-success active' : 'btn-outline-success' ?>">
            <i class="fas fa-check-circle me-1"></i> Verified
        </a>

        <a href="?status=rejected" 
           class="filter-btn btn <?= ($_GET['status'] ?? '') === 'rejected' ? 'btn-danger active' : 'btn-outline-danger' ?>">
            <i class="fas fa-times-circle me-1"></i> Rejected
        </a>
    </div>
</div>


    <?php if (mysqli_num_rows($payments) > 0): ?>
        <?php while ($payment = mysqli_fetch_assoc($payments)): ?>
            <div class="payment-card">
                <div class="row">
                    <div class="col-md-8">
                        <h5>
                            Reservation #<?php echo $payment['reservation_id']; ?> - 
                            <?php echo htmlspecialchars($payment['customer_name']); ?>
                        </h5>
                        <p class="mb-2">
                            <strong>Vehicle:</strong> <?php echo htmlspecialchars($payment['vehicle_make'] . ' ' . $payment['vehicle_model']); ?><br>
                            <strong>Date:</strong> <?php echo date('F d, Y', strtotime($payment['reservation_date'])); ?> at <?php echo date('h:i A', strtotime($payment['reservation_time'])); ?><br>
                            <strong>Contact:</strong> <?php echo htmlspecialchars($payment['phone']); ?> | <?php echo htmlspecialchars($payment['email']); ?>
                        </p>
                        <hr>
                        <p class="mb-2">
                            <strong>GCash Account Name:</strong> <?php echo htmlspecialchars($payment['account_name']); ?><br>
                            <strong>Amount Paid:</strong> ₱<?php echo number_format($payment['amount_paid'], 2); ?><br>
                            <strong>Submitted:</strong> <?php echo date('F d, Y h:i A', strtotime($payment['created_at'])); ?>
                        </p>
                        
                        <?php if ($payment['payment_status'] !== 'pending'): ?>
                            <p class="mb-0">
                                <!-- <strong>Verified By:</strong> <?php echo htmlspecialchars($payment['verified_by_name'] ?? 'N/A'); ?><br> -->
                                <strong>Verified At:</strong> <?php echo $payment['verified_at'] ? date('F d, Y h:i A', strtotime($payment['verified_at'])) : 'N/A'; ?>
                                <?php if ($payment['notes']): ?>
                                    <br><strong>Notes:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-center">
                        <p><strong>Payment Proof:</strong></p>
                        <img src="../uploads/payments/<?php echo htmlspecialchars($payment['payment_proof']); ?>" 
                             class="payment-proof img-thumbnail" 
                             data-bs-toggle="modal" 
                             data-bs-target="#imageModal<?php echo $payment['id']; ?>"
                             alt="Payment Proof">
                        
                        <p class="mt-3">
                            <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                <?php echo strtoupper($payment['payment_status']); ?>
                            </span>
                        </p>
                        
                        <?php if ($payment['payment_status'] === 'pending'): ?>
                            <button class="btn btn-success btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#verifyModal<?php echo $payment['id']; ?>">
                                <i class="fas fa-check me-1"></i>Verify
                            </button>
                            <button class="btn btn-danger btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $payment['id']; ?>">
                                <i class="fas fa-times me-1"></i>Reject
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Image Modal -->
            <div class="modal fade" id="imageModal<?php echo $payment['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Payment Proof - Reservation #<?php echo $payment['reservation_id']; ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="../uploads/payments/<?php echo htmlspecialchars($payment['payment_proof']); ?>" class="modal-img" alt="Payment Proof">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verify Modal -->
            <div class="modal fade" id="verifyModal<?php echo $payment['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title">Verify Payment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                <input type="hidden" name="action" value="verified">
                                <p>Are you sure you want to verify this payment?</p>
                                <div class="mb-3">
                                    <label class="form-label">Notes (Optional)</label>
                                    <textarea name="notes" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="verify_payment" class="btn btn-success">Verify Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reject Modal -->
            <div class="modal fade" id="rejectModal<?php echo $payment['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject Payment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                <input type="hidden" name="action" value="rejected">
                                <p>Are you sure you want to reject this payment?</p>
                                <div class="mb-3">
                                    <label class="form-label">Reason (Required)</label>
                                    <textarea name="notes" class="form-control" rows="3" required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="verify_payment" class="btn btn-danger">Reject Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No payments found.
        </div>
    <?php endif; ?>
</div>

<?php include "logout-modal.php" ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
    const toast = document.getElementById("notifToast");
    if (toast) {
        setTimeout(() => toast.classList.add("show"), 100); // fade in
        setTimeout(() => {
            toast.classList.remove("show"); // fade out
            setTimeout(() => toast.remove(), 400); // remove after fade
        }, 3000); // visible for 3s
    }
});
</script>
</body>
</html>
