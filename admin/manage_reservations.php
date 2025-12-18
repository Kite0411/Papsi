<?php
include '../includes/config.php';
session_name("admin_session");
session_start();
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// === Approve all vehicles in a booking group ===
if (isset($_GET['approve_group'])) {
    $customer_id = intval($_GET['customer_id']);
    $date = $_GET['date'];
    $time = $_GET['time'];
    
    // Get all reservations in this group
    $stmt = $conn->prepare("
        SELECT r.id, r.vehicle_make, r.vehicle_model, c.email, c.name
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.customer_id = ? 
        AND r.reservation_date = ?
        AND r.reservation_time = ?
        AND r.archived = 0
        AND r.status != 'declined'
    ");
    $stmt->bind_param("iss", $customer_id, $date, $time);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($group)) {
        // Approve all vehicles
        $update = $conn->prepare("
            UPDATE reservations 
            SET status = 'approved', archived = 1 
            WHERE customer_id = ? 
            AND reservation_date = ? 
            AND reservation_time = ?
        ");
        $update->bind_param("iss", $customer_id, $date, $time);
        $update->execute();
        
        // Log audit
        $vehicle_count = count($group);
        $desc = "Approved {$vehicle_count} vehicle booking for {$group[0]['name']} on $date at $time by admin '{$_SESSION['username']}'";
        logAudit('RESERVATION_APPROVED', $desc, $_SESSION['user_id'], $_SESSION['username']);
        
        $_SESSION['notif'] = [
            'message' => "✅ Complete booking for {$vehicle_count} vehicle(s)!",
            'type' => 'success'
        ];
    }
    
    header("Location: manage_reservations.php");
    exit();
}

// === Decline all vehicles in a booking group ===
if (isset($_GET['decline_group'])) {
    $customer_id = intval($_GET['customer_id']);
    $date = $_GET['date'];
    $time = $_GET['time'];
    
    $stmt = $conn->prepare("
        SELECT r.id, c.name
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.customer_id = ? 
        AND r.reservation_date = ?
        AND r.reservation_time = ?
        AND r.archived = 0
    ");
    $stmt->bind_param("iss", $customer_id, $date, $time);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!empty($group)) {
        // Decline all vehicles
        $update = $conn->prepare("
            UPDATE reservations 
            SET status = 'declined', archived = 1 
            WHERE customer_id = ? 
            AND reservation_date = ? 
            AND reservation_time = ?
        ");
        $update->bind_param("iss", $customer_id, $date, $time);
        $update->execute();

        $vehicle_count = count($group);
        $desc = "Declined {$vehicle_count} vehicle booking for {$group[0]['name']} on $date at $time by admin '{$_SESSION['username']}'";
        logAudit('RESERVATION_DECLINED', $desc, $_SESSION['user_id'], $_SESSION['username']);
        
        $_SESSION['notif'] = [
            'message' => "Reservation declined and moved to declined list.",
            'type' => 'info'
        ];
    }

    header("Location: manage_reservations.php");
    exit();
}

// === FETCH GROUPED RESERVATIONS ===
$reservations_stmt = $conn->prepare("
    SELECT 
        r.customer_id,
        c.name AS customer_name,
        r.reservation_date,
        r.reservation_time,
        r.status,
        COUNT(r.id) as vehicle_count,
        GROUP_CONCAT(
            CONCAT(r.vehicle_make, ' ', r.vehicle_model, ' (', r.vehicle_year, ')')
            SEPARATOR '||'
        ) as vehicles_list,
        MIN(r.id) as first_reservation_id
    FROM reservations r
    JOIN customers c ON r.customer_id = c.id
    WHERE r.archived = 0 AND r.status != 'declined'
    GROUP BY r.customer_id, r.reservation_date, r.reservation_time, r.status
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

.vehicle-badge {
    display: inline-block;
    background: #E3F2FD;
    color: #1976D2;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 700;
    margin-left: 8px;
}

.vehicle-list {
    font-size: 0.9rem;
    color: #666;
    margin-top: 5px;
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
    <i class="fas fa-ban me-2"></i>View Canceled Reservations
</a>

<div class="info-badge">
    <strong><i class="fas fa-info-circle"></i> Multi-Vehicle Bookings:</strong> When multiple vehicles are booked together, they are grouped and shown as one reservation. Approving or declining will apply to all vehicles in the group.
</div>

<div class="card">
<table class="reservations-table">
<thead>
<tr>
<th>Customer</th>
<th>Vehicles</th>
<th>Date</th>
<th>Time</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php 
if ($reservations_result->num_rows === 0) {
    echo '<tr><td colspan="6" style="text-align:center; padding: 40px;">
            <i class="fas fa-inbox fa-3x" style="color: #ccc; margin-bottom: 15px;"></i>
            <p style="color: #999; font-size: 1.1rem;">No pending reservations.</p>
          </td></tr>';
} else {
    while($row = $reservations_result->fetch_assoc()):
        $vehicles = explode('||', $row['vehicles_list']);
        $vehicle_count = $row['vehicle_count'];
?>
<tr>
<td data-label="Customer">
    <?php echo htmlspecialchars($row['customer_name']); ?>
    <?php if ($vehicle_count > 1): ?>
        <span class="vehicle-badge"><?php echo $vehicle_count; ?> vehicles</span>
    <?php endif; ?>
</td>
<td data-label="Vehicles">
    <?php foreach($vehicles as $vehicle): ?>
        <div class="vehicle-list">🚗 <?php echo htmlspecialchars($vehicle); ?></div>
    <?php endforeach; ?>
</td>
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
    <button class="action-btn approve-btn" 
            onclick="confirmAction(<?php echo $row['customer_id']; ?>, '<?php echo $row['reservation_date']; ?>', '<?php echo $row['reservation_time']; ?>', 'approve', <?php echo $vehicle_count; ?>)">
         Complete <?php echo $vehicle_count > 1 ? "" : ""; ?>
    </button>
    <button class="action-btn decline-btn" 
            onclick="confirmAction(<?php echo $row['customer_id']; ?>, '<?php echo $row['reservation_date']; ?>', '<?php echo $row['reservation_time']; ?>', 'decline', <?php echo $vehicle_count; ?>)">
         Cancel <?php echo $vehicle_count > 1 ? "All" : ""; ?>
    </button>
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
let pendingAction = { customer_id: null, date: null, time: null, type: null };

function confirmAction(customer_id, date, time, action, vehicleCount){
    pendingAction = { customer_id, date, time, type: action };
    const modal = document.getElementById('confirmModal');
    const title = document.getElementById('modalTitle');
    const message = document.getElementById('modalMessage');
    const yesBtn = document.getElementById('confirmYes');
    
    const vehicleText = vehicleCount > 1 ? `all ${vehicleCount} vehicles` : 'this vehicle';
    
    if (action === 'approve') {
        title.innerText = '✅ Approve ' + (vehicleCount > 1 ? 'Multi-Vehicle ' : '') + 'Reservation?';
        message.innerText = `This will approve ${vehicleText} and move them to completed.`;
        yesBtn.innerText = 'Approve';
        yesBtn.style.background = '#2e7d32';
    } else if (action === 'decline') {
        title.innerText = '⛔ Cancel' + (vehicleCount > 1 ? 'Multi-Vehicle ' : '') + 'Reservation?';
        message.innerText = `This will cancel ${vehicleText} and move them to canceled list.`;
        yesBtn.innerText = 'Cancel';
        yesBtn.style.background = '#c62828';
    }
    
    modal.style.display = 'flex';
}

document.getElementById('confirmYes').onclick = function(){
    if(!pendingAction.customer_id || !pendingAction.type) return;
    
    const action = pendingAction.type === 'approve' ? 'approve_group' : 'decline_group';
    window.location.href = `manage_reservations.php?${action}&customer_id=${pendingAction.customer_id}&date=${pendingAction.date}&time=${pendingAction.time}`;
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
