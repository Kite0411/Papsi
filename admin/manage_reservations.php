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

// --- MARK AS COMPLETED (replaces archive functionality) ---
if (isset($_GET['complete'])) {
    $id = intval($_GET['complete']);

    $stmt = $conn->prepare("
        SELECT r.id, c.name AS customer_name, r.vehicle_make, r.vehicle_model, r.reservation_date, r.reservation_time
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();

    if ($reservation) {
        // Mark as completed instead of archived
        $update = $conn->prepare("UPDATE reservations SET status = 'completed', archived = 1 WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();

        $desc = "Reservation #{$reservation['id']} ({$reservation['customer_name']} - {$reservation['vehicle_make']} {$reservation['vehicle_model']}) marked as completed by admin '{$_SESSION['username']}'.";
        logAudit('RESERVATION_COMPLETED', $desc, $_SESSION['user_id'], $_SESSION['username']);
    }

    $_SESSION['notif'] = ['message' => 'Reservation marked as completed!', 'type' => 'success'];
    header("Location: manage_reservations.php");
    exit();
}

// --- APPROVE Reservation ---
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    
    $stmt = $conn->prepare("
        SELECT r.*, c.email AS customer_email, c.name AS customer_name
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.id = ? 
        AND r.status NOT IN ('approved', 'declined', 'completed')
        AND (r.status = 'pending_verification' OR r.method = 'Walk-In')
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $update = $conn->prepare("UPDATE reservations SET status = 'approved' WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();

        $stmt = $conn->prepare("SELECT status FROM reservations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $status_result = $stmt->get_result()->fetch_assoc();

        if ($status_result['status'] === 'approved') {
            $desc = "Reservation #{$res['id']} for {$res['vehicle_make']} {$res['vehicle_model']} approved by admin '{$_SESSION['username']}'";
            logAudit('RESERVATION_APPROVED', $desc, $_SESSION['user_id'], $_SESSION['username']);
            
            if (function_exists('sendEmail') && !empty($res['customer_email'])) {
                $to = $res['customer_email'];
                $name = htmlspecialchars($res['customer_name']);
                $date = htmlspecialchars($res['reservation_date']);
                $time = htmlspecialchars(date("g:i A", strtotime($res['reservation_time'])));
                $vehicle = htmlspecialchars($res['vehicle_make'] . ' ' . $res['vehicle_model'] . ' (' . $res['vehicle_year'] . ')');
                $subject = "Reservation Approved - Auto Repair Center";
                $body_html = "
                    <p>Dear <b>$name</b>,</p>
                    <p>Your reservation has been <b>approved</b>!</p>
                    <p><b>Reservation Details:</b></p>
                    <ul>
                        <li><b>Vehicle:</b> $vehicle</li>
                        <li><b>Date:</b> $date</li>
                        <li><b>Time:</b> $time</li>
                    </ul>
                    <p>We look forward to serving you on your scheduled date and time.</p>
                    <p>Thank you,<br><b>AutoRepair Center</b></p>
                ";
                $body_text = "Dear $name,\n\nYour reservation has been approved!\n\nReservation Details:\n- Vehicle: $vehicle\n- Date: $date\n- Time: $time\n\nWe look forward to serving you on your scheduled date and time.\n\nThank you,\nAutoRepair Center";

                list($sent, $err) = sendEmail($to, $subject, $body_html, $body_text);
                if (!$sent) {
                    error_log("Failed to send reservation approval email to $to: $err");
                }
            }
            
            $_SESSION['notif'] = ['message' => 'Reservation approved successfully!', 'type' => 'success'];
        }
    }
    
    header("Location: manage_reservations.php");
    exit();
}

// --- DECLINE Reservation ---
if (isset($_GET['decline'])) {
    $id = intval($_GET['decline']);

    $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ? AND status IN ('pending_verification','approved')");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $update = $conn->prepare("UPDATE reservations SET status = 'declined' WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();

        $desc = "Reservation #{$res['id']} for {$res['vehicle_make']} {$res['vehicle_model']} declined by admin '{$_SESSION['username']}'";
        logAudit('RESERVATION_DECLINED', $desc, $_SESSION['user_id'], $_SESSION['username']);
        $_SESSION['notif'] = ['message' => 'Reservation declined.', 'type' => 'info'];
    }

    header("Location: manage_reservations.php");
    exit();
}

// --- FETCH Active Reservations (not completed/archived) ---
$reservations_stmt = $conn->prepare("
    SELECT r.id, c.name AS customer_name, r.vehicle_make, r.vehicle_model, 
           r.reservation_date, r.reservation_time, r.status, r.method
    FROM reservations r
    JOIN customers c ON r.customer_id = c.id
    WHERE r.archived = 0 AND r.status != 'completed'
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
    color: #2e7d32; 
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

.complete-btn {
    background-color: #7B1FA2;
    color: white;
    margin-left: 10px;
}

.complete-btn:hover {
    background-color: #6A1B9A;
}

.decline-btn {
    background-color: #c62828;
    color: white;
    margin-left: 10px;
}

.decline-btn:hover {
    background-color: #b71c1c;
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
    
    .complete-btn, .decline-btn { 
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
$color = $type === 'success' ? '#28a745' : '#dc3545';
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
<h1>Manage Active Reservations</h1>

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

<?php while($row = $reservations_result->fetch_assoc()):
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
                echo 'background: #FFF3E0; color: #F57C00;';
                break;
            case 'confirmed':
                echo 'background: #E3F2FD; color: #1976D2;';
                break;
            case 'approved':
                echo 'background: #E8F5E9; color: #388E3C;';
                break;
            case 'declined':
                echo 'background: #FFEBEE; color: #D32F2F;';
                break;
        }
        ?>
    ">
        <?php echo ucwords(str_replace('_', ' ', $row['status'])); ?>
    </span>
</td>
<td data-label="Action">
<?php 
$canApproveWalkIn = $row['method'] === 'Walk-In' && $row['status'] !== 'approved' && $row['status'] !== 'declined';
if ($row['status'] === 'pending_verification' || $canApproveWalkIn) {
    echo '<button class="action-btn approve-btn" onclick="window.location.href=\'manage_reservations.php?approve=' . $row['id'] . '\'">✅ Approve</button>';
    echo '<button class="action-btn decline-btn" onclick="openActionModal(' . $row['id'] . ', \'decline\')">⛔ Decline</button>';
} else if ($row['status'] === 'approved') {
    echo '<span style="color:green;">Approved</span>';
    echo '<button class="action-btn complete-btn" onclick="openActionModal(' . $row['id'] . ', \'complete\')">✔️ Mark Complete</button>';
    echo '<button class="action-btn decline-btn" onclick="openActionModal(' . $row['id'] . ', \'decline\')">⛔ Decline</button>';
} else if ($row['status'] === 'declined') {
    echo '<span style="color:#c62828;">Declined</span>';
    echo '<button class="action-btn complete-btn" onclick="openActionModal(' . $row['id'] . ', \'complete\')">✔️ Mark Complete</button>';
}
?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<div id="confirmModal" class="confirm-modal">
<div class="confirm-box">
<h3>Confirm Action?</h3>
<p>Please confirm this action.</p>
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

// Action modal functionality
let pendingAction = { id: null, type: null };

function openActionModal(id, action){
    pendingAction = { id, type: action };
    const modal = document.getElementById('confirmModal');
    
    if (action === 'decline') {
        modal.querySelector('h3').innerText = 'Decline this reservation?';
        modal.querySelector('p').innerText = 'This will mark the reservation as declined.';
        modal.querySelector('#confirmYes').innerText = 'Decline';
        modal.querySelector('.confirm-yes').style.background = '#c62828';
    } else if (action === 'complete') {
        modal.querySelector('h3').innerText = 'Mark as Completed?';
        modal.querySelector('p').innerText = 'This reservation will be moved to the completed list.';
        modal.querySelector('#confirmYes').innerText = 'Mark Complete';
        modal.querySelector('.confirm-yes').style.background = '#2e7d32';
    }
    
    modal.style.display = 'flex';
}

document.getElementById('confirmYes').onclick = function(){
    if(!pendingAction.id || !pendingAction.type) return;
    if(pendingAction.type === 'decline'){
        window.location.href = "manage_reservations.php?decline=" + pendingAction.id;
    } else if(pendingAction.type === 'complete'){
        window.location.href = "manage_reservations.php?complete=" + pendingAction.id;
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
