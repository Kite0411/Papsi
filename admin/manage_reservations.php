<?php
include '../includes/config.php';
session_name("admin_session");
session_start();
$conn = getDBConnection();

// --- Ensure admin is logged in ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// --- APPROVE Reservation (moves to completed) ---
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    
    $stmt = $conn->prepare("
        SELECT r.*, c.email AS customer_email, c.name AS customer_name
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.id = ? 
        AND r.archived = 0
        AND r.status != 'declined'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        // Move to completed (archived = 1, status = 'approved')
        $update = $conn->prepare("UPDATE reservations SET status = 'approved', archived = 1 WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();

        // Log audit trail
        $desc = "Reservation #{$res['id']} for {$res['vehicle_make']} {$res['vehicle_model']} approved and moved to completed by admin '{$_SESSION['username']}'";
        logAudit('RESERVATION_APPROVED', $desc, $_SESSION['user_id'], $_SESSION['username']);
        
        // Send styled email notification
        if (function_exists('sendEmail') && !empty($res['customer_email'])) {
            $to = $res['customer_email'];
            $name = htmlspecialchars($res['customer_name']);
            $date = htmlspecialchars(date("F d, Y", strtotime($res['reservation_date'])));
            $time = htmlspecialchars(date("g:i A", strtotime($res['reservation_time'])));
            $vehicle = htmlspecialchars($res['vehicle_make'] . ' ' . $res['vehicle_model'] . ' (' . $res['vehicle_year'] . ')');
            $subject = "✅ Reservation Approved - Papsi Paps Auto Repair";
            
            $body_html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
                    .container { max-width: 600px; margin: 0 auto; background: white; }
                    .header { background: linear-gradient(135deg, #DC143C, #B71C1C); padding: 40px 20px; text-align: center; }
                    .header h1 { color: white; margin: 0; font-size: 28px; }
                    .header p { color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px; }
                    .content { padding: 40px 30px; }
                    .success-badge { background: #E8F5E9; color: #2E7D32; padding: 15px 20px; border-radius: 8px; text-align: center; margin-bottom: 30px; border-left: 4px solid #2E7D32; }
                    .success-badge h2 { margin: 0; color: #2E7D32; font-size: 24px; }
                    .info-box { background: #f9f9f9; border-left: 4px solid #DC143C; padding: 20px; margin: 20px 0; border-radius: 4px; }
                    .info-box h3 { margin: 0 0 15px 0; color: #333; font-size: 18px; }
                    .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
                    .info-row:last-child { border-bottom: none; }
                    .info-label { font-weight: 600; color: #666; min-width: 120px; }
                    .info-value { color: #333; }
                    .footer { background: #333; color: white; padding: 30px 20px; text-align: center; }
                    .footer p { margin: 5px 0; font-size: 14px; }
                    .button { display: inline-block; background: linear-gradient(135deg, #DC143C, #B71C1C); color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: 600; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔧 Papsi Paps Auto Repair</h1>
                        <p>Your Trusted Auto Care Partner</p>
                    </div>
                    
                    <div class='content'>
                        <div class='success-badge'>
                            <h2>✅ Reservation Approved!</h2>
                        </div>
                        
                        <p style='font-size: 16px; line-height: 1.6; color: #333;'>
                            Dear <strong>$name</strong>,
                        </p>
                        
                        <p style='font-size: 16px; line-height: 1.6; color: #333;'>
                            Great news! Your reservation has been <strong>approved</strong> and confirmed. We're looking forward to serving you!
                        </p>
                        
                        <div class='info-box'>
                            <h3>📋 Reservation Details</h3>
                            <div class='info-row'>
                                <div class='info-label'>🚗 Vehicle:</div>
                                <div class='info-value'>$vehicle</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>📅 Date:</div>
                                <div class='info-value'>$date</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>🕐 Time:</div>
                                <div class='info-value'>$time</div>
                            </div>
                        </div>
                        
                        <p style='font-size: 14px; line-height: 1.6; color: #666; background: #FFF3E0; padding: 15px; border-radius: 5px; border-left: 4px solid #FF9800;'>
                            <strong>⚠️ Important:</strong> Please arrive 10 minutes early. If you need to reschedule or cancel, please contact us at least 24 hours in advance.
                        </p>
                    </div>
                    
                    <div class='footer'>
                        <p><strong>Papsi Paps Auto Repair Center</strong></p>
                        <p>📞 Contact: [Your Phone Number]</p>
                        <p>📧 Email: [Your Email]</p>
                        <p style='margin-top: 20px; opacity: 0.8;'>© " . date('Y') . " Papsi Paps Auto Repair. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $body_text = "Dear $name,\n\nYour reservation has been approved!\n\nReservation Details:\n- Vehicle: $vehicle\n- Date: $date\n- Time: $time\n\nWe look forward to serving you on your scheduled date and time.\n\nThank you,\nPapsi Paps Auto Repair Center";

            list($sent, $err) = sendEmail($to, $subject, $body_html, $body_text);
            if (!$sent) {
                error_log("Failed to send reservation approval email to $to: $err");
            }
        }
        
        $_SESSION['notif'] = ['message' => 'Reservation approved and moved to completed!', 'type' => 'success'];
    }
    
    header("Location: manage_reservations.php");
    exit();
}

// --- DECLINE Reservation (moves to declined list) ---
if (isset($_GET['decline'])) {
    $id = intval($_GET['decline']);

    $stmt = $conn->prepare("
        SELECT r.*, c.email AS customer_email, c.name AS customer_name
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.id = ? AND r.archived = 0
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        // Mark as declined and archived
        $update = $conn->prepare("UPDATE reservations SET status = 'declined', archived = 1 WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();

        $desc = "Reservation #{$res['id']} for {$res['vehicle_make']} {$res['vehicle_model']} declined by admin '{$_SESSION['username']}'";
        logAudit('RESERVATION_DECLINED', $desc, $_SESSION['user_id'], $_SESSION['username']);
        
        // Send styled decline email
        if (function_exists('sendEmail') && !empty($res['customer_email'])) {
            $to = $res['customer_email'];
            $name = htmlspecialchars($res['customer_name']);
            $date = htmlspecialchars(date("F d, Y", strtotime($res['reservation_date'])));
            $time = htmlspecialchars(date("g:i A", strtotime($res['reservation_time'])));
            $vehicle = htmlspecialchars($res['vehicle_make'] . ' ' . $res['vehicle_model'] . ' (' . $res['vehicle_year'] . ')');
            $subject = "❌ Reservation Update - Papsi Paps Auto Repair";
            
            $body_html = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
                    .container { max-width: 600px; margin: 0 auto; background: white; }
                    .header { background: linear-gradient(135deg, #DC143C, #B71C1C); padding: 40px 20px; text-align: center; }
                    .header h1 { color: white; margin: 0; font-size: 28px; }
                    .header p { color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px; }
                    .content { padding: 40px 30px; }
                    .decline-badge { background: #FFEBEE; color: #C62828; padding: 15px 20px; border-radius: 8px; text-align: center; margin-bottom: 30px; border-left: 4px solid #C62828; }
                    .decline-badge h2 { margin: 0; color: #C62828; font-size: 24px; }
                    .info-box { background: #f9f9f9; border-left: 4px solid #DC143C; padding: 20px; margin: 20px 0; border-radius: 4px; }
                    .info-box h3 { margin: 0 0 15px 0; color: #333; font-size: 18px; }
                    .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
                    .info-row:last-child { border-bottom: none; }
                    .info-label { font-weight: 600; color: #666; min-width: 120px; }
                    .info-value { color: #333; }
                    .footer { background: #333; color: white; padding: 30px 20px; text-align: center; }
                    .footer p { margin: 5px 0; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔧 Papsi Paps Auto Repair</h1>
                        <p>Your Trusted Auto Care Partner</p>
                    </div>
                    
                    <div class='content'>
                        <div class='decline-badge'>
                            <h2>❌ Reservation Update</h2>
                        </div>
                        
                        <p style='font-size: 16px; line-height: 1.6; color: #333;'>
                            Dear <strong>$name</strong>,
                        </p>
                        
                        <p style='font-size: 16px; line-height: 1.6; color: #333;'>
                            We regret to inform you that we are unable to confirm your reservation at this time. This may be due to scheduling conflicts or other operational constraints.
                        </p>
                        
                        <div class='info-box'>
                            <h3>📋 Reservation Details</h3>
                            <div class='info-row'>
                                <div class='info-label'>🚗 Vehicle:</div>
                                <div class='info-value'>$vehicle</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>📅 Date:</div>
                                <div class='info-value'>$date</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>🕐 Time:</div>
                                <div class='info-value'>$time</div>
                            </div>
                        </div>
                        
                        <p style='font-size: 14px; line-height: 1.6; color: #666; background: #E3F2FD; padding: 15px; border-radius: 5px; border-left: 4px solid #1976D2;'>
                            <strong>💡 Alternative Options:</strong> Please feel free to book another time slot or contact us directly to discuss alternative arrangements. We apologize for any inconvenience.
                        </p>
                    </div>
                    
                    <div class='footer'>
                        <p><strong>Papsi Paps Auto Repair Center</strong></p>
                        <p>📞 Contact: [Your Phone Number]</p>
                        <p>📧 Email: [Your Email]</p>
                        <p style='margin-top: 20px; opacity: 0.8;'>© " . date('Y') . " Papsi Paps Auto Repair. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $body_text = "Dear $name,\n\nWe regret to inform you that we are unable to confirm your reservation at this time.\n\nReservation Details:\n- Vehicle: $vehicle\n- Date: $date\n- Time: $time\n\nPlease feel free to book another time slot or contact us directly.\n\nThank you for your understanding,\nPapsi Paps Auto Repair Center";

            list($sent, $err) = sendEmail($to, $subject, $body_html, $body_text);
            if (!$sent) {
                error_log("Failed to send reservation decline email to $to: $err");
            }
        }
        
        $_SESSION['notif'] = ['message' => 'Reservation declined and moved to declined list.', 'type' => 'info'];
    }

    header("Location: manage_reservations.php");
    exit();
}

// --- FETCH Active Reservations (not completed/archived and not declined) ---
$reservations_stmt = $conn->prepare("
    SELECT r.id, c.name AS customer_name, r.vehicle_make, r.vehicle_model, 
           r.reservation_date, r.reservation_time, r.status, r.method
    FROM reservations r
    JOIN customers c ON r.customer_id = c.id
    WHERE r.archived = 0 AND r.status != 'declined'
    ORDER BY r.reservation_date DESC, r.reservation_time DESC
");
$reservations_stmt->execute();
$reservations_result = $reservations_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Reservations - Auto Repair Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin-mobile-responsive.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    background: var(--light-gray); 
    margin: 0;
    padding-top: 80px;
    padding-bottom: 30px;
}

.navbar { 
    background: white; 
    display: flex; 
    justify-content: space-between; 
    padding: 15px 30px; 
    align-items: center; 
    border-bottom: 3px solid var(--primary-red); 
    box-shadow: var(--shadow-md);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
}

.navbar .logo { 
    color: var(--primary-red); 
    font-size: 1.8rem; 
    font-weight: 800; 
}

.navbar-toggle {
    display: none;
    background: var(--primary-red);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1.2rem;
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

.navbar ul li a.active, 
.navbar ul li a:hover { 
    background: var(--gradient-primary); 
    color: white; 
}

.container { 
    max-width: 1100px; 
    margin: 20px auto; 
    padding: 0 20px; 
}

h1 { 
    text-align: center; 
    margin-bottom: 30px; 
    font-weight: 800; 
}

.card { 
    background: white; 
    padding: 30px; 
    border-radius: var(--radius-lg); 
    box-shadow: var(--shadow-md); 
    border: 2px solid rgba(220,20,60,0.1); 
    margin-bottom: 25px; 
}

table { 
    width: 100%; 
    border-collapse: collapse; 
}

table th, 
table td { 
    padding: 15px; 
    text-align: left; 
}

table th { 
    background: var(--gradient-primary); 
    color: white; 
    font-weight: 700; 
}

table tr:nth-child(even) { 
    background: var(--light-gray); 
}

table tr:hover { 
    background: #FFEBEE; 
}

table a { 
    text-decoration: none; 
    color: var(--primary-red); 
    font-weight: 600; 
    margin-right: 10px; 
    transition: var(--transition-fast); 
}

table a:hover { 
    color: var(--primary-red-dark); 
    text-decoration: underline; 
}

.notif-toast { 
    position: fixed; 
    top: 90px; 
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
    max-width: 90%;
}

.notif-toast.show { 
    opacity: 1; 
    transform: translateX(-50%) translateY(0); 
}

.confirm-modal { 
    display: none; 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0,0,0,0.6); 
    justify-content: center; 
    align-items: center;
    z-index: 9999;
}

.confirm-box { 
    background: white; 
    padding: 25px 30px; 
    border-radius: 10px; 
    text-align: center; 
    max-width: 400px; 
    width: 90%; 
    box-shadow: 0 5px 20px rgba(0,0,0,0.2); 
}

.confirm-box h3 { 
    margin-bottom: 15px; 
}

.confirm-box button { 
    border: none; 
    padding: 10px 20px; 
    border-radius: 5px; 
    margin: 0 10px; 
    cursor: pointer; 
    font-weight: bold;
    min-height: 44px;
    min-width: 44px;
}

.confirm-yes { 
    background: #2e7d32; 
    color: white; 
}

.confirm-no { 
    background: #ccc; 
}

.action-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 14px;
    text-align: center;
    min-height: 44px;
    display: inline-block;
}

.approve-btn {
    background-color: #28a745;
    color: white;
}

.approve-btn:hover {
    background-color: #218838;
}

.decline-btn {
    background-color: #c62828;
    color: white;
    margin-left: 10px;
}

.decline-btn:hover {
    background-color: #b71c1c;
}

.info-badge {
    background: #E3F2FD;
    color: #1976D2;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #1976D2;
}

.view-declined-btn {
    background: linear-gradient(135deg, #FF5722, #E64A19);
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    text-decoration: none;
    display: inline-block;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.view-declined-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 87, 34, 0.3);
    text-decoration: none;
    color: white;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .navbar-toggle {
        display: block;
    }
    
    .navbar.collapsed ul {
        display: none;
    }
    
    body {
        padding-top: 70px;
    }
    
    .container {
        padding: 0 15px;
    }
    
    .card { 
        padding: 20px; 
    }
    
    .notif-toast {
        top: 80px;
        font-size: 0.9rem;
        padding: 12px 20px;
    }
    
    h1 {
        font-size: 1.5rem;
    }
    
    table, thead, tbody, th, tr { 
        display: block; 
    }
    
    table thead { 
        position: absolute; 
        width: 1px; 
        height: 1px; 
        overflow: hidden; 
        clip: rect(0 0 0 0); 
    }
    
    table tr { 
        background: white; 
        margin-bottom: 15px; 
        border-radius: var(--radius-md); 
        box-shadow: var(--shadow-sm, 0 2px 6px rgba(0,0,0,0.1)); 
        padding: 10px 0; 
    }
    
    table td { 
        display: flex; 
        justify-content: space-between; 
        align-items: flex-start; 
        padding: 10px 15px; 
        border-bottom: 1px solid var(--light-gray); 
    }
    
    table td:last-child { 
        border-bottom: none; 
    }
    
    table td::before { 
        content: attr(data-label); 
        font-weight: 700; 
        padding-right: 10px; 
        flex-basis: 45%; 
        color: var(--dark-gray); 
    }
    
    table td span { 
        display: block; 
    }
    
    .action-btn { 
        width: 100%; 
        margin: 6px 0; 
    }
    
    .decline-btn { 
        margin-left: 0; 
    }
}

@media (max-width: 400px) {
    .navbar .logo {
        font-size: 1.4rem;
    }
    
    .navbar {
        padding: 12px 20px;
    }
}
</style>
</head>
<body>

<?php if(isset($_SESSION['notif'])): 
$type = $_SESSION['notif']['type'];
$message = $_SESSION['notif']['message'];
$color = $type === 'success' ? '#28a745' : ($type === 'info' ? '#17a2b8' : '#dc3545');
?>
<div class="notif-toast" id="notifToast" style="background: <?php echo $color; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php unset($_SESSION['notif']); endif; ?>

<!-- Navbar with mobile toggle -->
<nav class="navbar collapsed" id="adminNavbar">
    <div class="logo">🔧 Papsi Paps Admin</div>
    
    <button class="navbar-toggle" onclick="toggleNav()">
        <i class="fas fa-bars"></i>
    </button>
    
    <ul id="navMenu">
        <?php if($_SESSION['role']==='superadmin'): ?>
            <li><a href="index.php">Dashboard</a></li>
        <?php endif; ?>
        <li><a href="walk_in.php">Manage Walk-In</a></li>
        <li><a href="manage_payments.php">Payments</a></li>
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="manage_services.php">Manage Services</a></li>
        <?php endif; ?>
        <li><a href="manage_reservations.php" class="active">Reservations</a></li>
        <li><a href="completed_reservations.php">Completed</a></li>
        <?php if($_SESSION['role']==='superadmin'): ?>
            <li><a href="audit_trail.php">Audit Trail</a></li>
        <?php endif; ?>
        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>
</nav>

<div class="container">
<h1>Manage Pending Reservations</h1>

<a href="declined_reservations.php" class="view-declined-btn">
    <i class="fas fa-ban me-2"></i>View Declined Reservations
</a>

<div class="info-badge">
    <strong><i class="fas fa-info-circle"></i> Info:</strong> Approving a reservation will move it to completed list. Declining will move it to declined reservations where you can restore or permanently delete it.
</div>

<div class="card">
<table class="reservations-table">
<thead>
<tr>
<th>Customer</th>
<th>Service</th>
<th>Duration</th>
<th>Price</th>
<th>Date</th>
<th>Time</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php 
if ($reservations_result->num_rows === 0) {
    echo '<tr><td colspan="8" style="text-align:center; padding: 40px;">
            <i class="fas fa-inbox fa-3x" style="color: #ccc; margin-bottom: 15px;"></i>
            <p style="color: #999; font-size: 1.1rem;">No pending reservations.</p>
          </td></tr>';
} else {
    while($row = $reservations_result->fetch_assoc()):
    $res_id = $row['id'];
    $services_stmt = $conn->prepare("
        SELECT s.service_name, s.duration, s.price
        FROM reservation_services rs
        JOIN services s ON rs.service_id = s.id
        WHERE rs.reservation_id = ?
    ");
    $services_stmt->bind_param("i", $res_id);
    $services_stmt->execute();
    $services_result = $services_stmt->get_result();
    $services = [];
    while($s = $services_result->fetch_assoc()){ $services[] = $s; }
?>
<tr>
<td data-label="Customer"><?php echo htmlspecialchars($row['customer_name']); ?></td>
<td data-label="Service">
<?php if(empty($services)){ echo "<em style='color:gray;'>No linked services</em>"; } else { foreach($services as $s){ echo htmlspecialchars($s['service_name'])."<br>"; } } ?>
</td>
<td data-label="Duration"><?php foreach($services as $s){ echo $s['duration']." minutes<br>"; } ?></td>
<td data-label="Price"><?php foreach($services as $s){ echo "₱".number_format($s['price'],2)."<br>"; } ?></td>
<td data-label="Date"><?php echo date("M j, Y", strtotime($row['reservation_date'])); ?></td>
<td data-label="Time"><?php echo date("g:i A", strtotime($row['reservation_time'])); ?></td>
<td data-label="Status">
    <span style="
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-block;
        <?php 
        switch($row['status']) {
            case 'pending_verification':
            case 'pending':
                echo 'background: #FFF3E0; color: #F57C00;';
                break;
            case 'confirmed':
                echo 'background: #E3F2FD; color: #1976D2;';
                break;
            default:
                echo 'background: #F5F5F5; color: #666;';
        }
        ?>
    ">
        <?php echo ucwords(str_replace('_', ' ', $row['status'])); ?>
    </span>
</td>
<td data-label="Action">
    <button class="action-btn approve-btn" onclick="confirmAction(<?php echo $row['id']; ?>, 'approve')">✅ Approve</button>
    <button class="action-btn decline-btn" onclick="confirmAction(<?php echo $row['id']; ?>, 'decline')">⛔ Decline</button>
</td>
</tr>
<?php 
    endwhile;
}
?>
</tbody>
</table>
</div>
</div>

<div id="confirmModal" class="confirm-modal">
<div class="confirm-box">
<h3 id="modalTitle">Confirm Action?</h3>
<p id="modalMessage">Please confirm this action.</p>
<button class="confirm-yes" id="confirmYes">Confirm</button>
<button class="confirm-no" onclick="closeModal()">Cancel</button>
</div>
</div>

<?php include "logout-modal.php"; ?>

<script>
// Mobile navbar toggle
function toggleNav() {
    const navbar = document.getElementById('adminNavbar');
    const icon = document.querySelector('.navbar-toggle i');
    
    if (navbar.classList.contains('collapsed')) {
        navbar.classList.remove('collapsed');
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
    } else {
        navbar.classList.add('collapsed');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
    }
}

// Auto-collapse on window resize
window.addEventListener('resize', function() {
    const navbar = document.getElementById('adminNavbar');
    if (window.innerWidth > 768) {
        navbar.classList.remove('collapsed');
        const icon = document.querySelector('.navbar-toggle i');
        if (icon) {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
});

// Action confirmation
let pendingAction = { id: null, type: null };

function confirmAction(id, action){
    pendingAction = { id, type: action };
    const modal = document.getElementById('confirmModal');
    const title = document.getElementById('modalTitle');
    const message = document.getElementById('modalMessage');
    const yesBtn = document.getElementById('confirmYes');
    
    if (action === 'approve') {
        title.innerText = '✅ Approve this reservation?';
        message.innerText = 'This will move the reservation to completed and notify the customer via email.';
        yesBtn.innerText = 'Approve';
        yesBtn.style.background = '#2e7d32';
    } else if (action === 'decline') {
        title.innerText = '⛔ Decline this reservation?';
        message.innerText = 'This will move the reservation to declined list and notify the customer via email.';
        yesBtn.innerText = 'Decline';
        yesBtn.style.background = '#c62828';
    }
    
    modal.style.display = 'flex';
}

document.getElementById('confirmYes').onclick = function(){
    if(!pendingAction.id || !pendingAction.type) return;
    
    if(pendingAction.type === 'approve'){
        window.location.href = "manage_reservations.php?approve=" + pendingAction.id;
    } else if(pendingAction.type === 'decline'){
        window.location.href = "manage_reservations.php?decline=" + pendingAction.id;
    }
};

function closeModal(){ 
    document.getElementById('confirmModal').style.display='none'; 
}

// Notification toast
document.addEventListener("DOMContentLoaded", function(){
    const toast = document.getElementById("notifToast");
    if(toast){
        setTimeout(()=>toast.classList.add("show"),100);
        setTimeout(()=>{
            toast.classList.remove("show");
            setTimeout(()=>toast.remove(),400);
        },3000);
    }
});
</script>
</body>
</html>
