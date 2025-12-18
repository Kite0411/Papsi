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

// --- RESTORE Reservation (back to pending) ---
if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);

    $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ? AND archived = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();

    if ($reservation) {
        // Restore to pending status and make active again
        $update = $conn->prepare("UPDATE reservations SET archived = 0, status = 'pending' WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();

        // Log audit trail
        $desc = "Reservation #{$reservation['id']} restored from completed to pending by admin '{$_SESSION['username']}'.";
        logAudit('RESERVATION_RESTORED', $desc, $_SESSION['user_id'], $_SESSION['username']);
    }

    $_SESSION['notif'] = [
        'message' => 'Reservation restored to pending status!',
        'type' => 'success'
    ];
    header("Location: completed_reservations.php");
    exit();
}

// --- DELETE Reservation PERMANENTLY ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Get reservation details before deleting
    $stmt = $conn->prepare("
        SELECT r.*, c.name AS customer_name 
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.id = ? AND r.archived = 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();

    if ($reservation) {
        // Delete related reservation_services first
        $delete_services = $conn->prepare("DELETE FROM reservation_services WHERE reservation_id = ?");
        $delete_services->bind_param("i", $id);
        $delete_services->execute();

        // Delete the reservation
        $delete = $conn->prepare("DELETE FROM reservations WHERE id = ?");
        $delete->bind_param("i", $id);
        $delete->execute();

        // Log audit trail
        $desc = "Reservation #{$reservation['id']} ({$reservation['customer_name']} - {$reservation['vehicle_make']} {$reservation['vehicle_model']}) permanently deleted by admin '{$_SESSION['username']}'.";
        logAudit('RESERVATION_DELETED', $desc, $_SESSION['user_id'], $_SESSION['username']);
    }

    $_SESSION['notif'] = [
        'message' => 'Reservation permanently deleted!',
        'type' => 'success'
    ];
    header("Location: completed_reservations.php");
    exit();
}

// --- FETCH Completed Reservations ---
$completed_stmt = $conn->prepare("
    SELECT r.id, c.name AS customer_name, r.vehicle_make, r.vehicle_model,
        r.reservation_date, r.reservation_time, r.status, r.method
    FROM reservations r
    JOIN customers c ON r.customer_id = c.id
    WHERE r.archived = 1
    ORDER BY r.reservation_date DESC, r.reservation_time DESC
");
$completed_stmt->execute();
$completed_result = $completed_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Completed Reservations - Auto Repair Admin</title>
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
    box-shadow: var(--shadow-md); 
    display: flex; 
    justify-content: space-between; 
    padding: 15px 30px; 
    align-items: center; 
    border-bottom: 3px solid var(--primary-red);
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
    display: inline-block;
    min-height: 44px;
    line-height: 44px;
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

.info-badge {
    background: #E8F5E9;
    color: #2E7D32;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #2E7D32;
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
        font-size: 1.3rem;
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

<?php if (isset($_SESSION['notif'])): 
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
        <li><a href="manage_reservations.php">Reservations</a></li>
        <li><a href="completed_reservations.php" class="active">Completed</a></li>
        <?php if($_SESSION['role']==='superadmin'): ?>
            <li><a href="audit_trail.php">Audit Trail</a></li>
        <?php endif; ?>
        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>
</nav>

<div class="container">
<div class="card">
<h1>📋 Completed Reservations</h1>

<div class="info-badge">
    <strong><i class="fas fa-info-circle"></i> Info:</strong> These reservations have been approved and completed. You can restore them back to pending status or permanently delete them.
</div>

<table>
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
if ($completed_result->num_rows === 0) {
    echo '<tr><td colspan="8" style="text-align:center; padding: 40px;">
            <i class="fas fa-inbox fa-3x" style="color: #ccc; margin-bottom: 15px;"></i>
            <p style="color: #999; font-size: 1.1rem;">No completed reservations yet.</p>
          </td></tr>';
} else {
    while ($row = $completed_result->fetch_assoc()):
    $res_id = $row['id'];

    // Fetch services using prepared statement
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
    while ($s = $services_result->fetch_assoc()) { $services[] = $s; }
?>
<tr>
<td data-label="Customer"><?php echo htmlspecialchars($row['customer_name']); ?></td>
<td data-label="Service">
<?php if(empty($services)) { echo "<em style='color:gray;'>No linked services</em>"; } 
    else { foreach($services as $s){ echo htmlspecialchars($s['service_name'])."<br>"; } } ?>
</td>
<td data-label="Duration"><?php foreach($services as $s){ echo $s['duration']." minutes<br>"; } ?></td>
<td data-label="Price"><?php foreach($services as $s){ echo "₱".number_format($s['price'],2)."<br>"; } ?></td>
<td data-label="Date"><?php echo date("M j, Y", strtotime($row['reservation_date'])); ?></td>
<td data-label="Time"><?php echo date("g:i A", strtotime($row['reservation_time'])); ?></td>
<td data-label="Status">
    <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; background: #E8F5E9; color: #2E7D32; display: inline-block;">
        Complete
    </span>
</td>
<td data-label="Action">
<a href="#" onclick="confirmAction(<?php echo $row['id']; ?>, 'delete'); return false;" style="color: #c62828;">🗑️ Delete</a>
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
<p id="modalMessage">Are you sure?</p>
<button class="confirm-yes" id="confirmYes">Confirm</button>
<button class="confirm-no" onclick="closeModal()">Cancel</button>
</div>
</div>

<?php include "logout-modal.php" ?>

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

function confirmAction(id, action) {
    pendingAction = { id, type: action };
    const modal = document.getElementById('confirmModal');
    const title = document.getElementById('modalTitle');
    const message = document.getElementById('modalMessage');
    const yesBtn = document.getElementById('confirmYes');
    
    if (action === 'restore') {
        title.innerText = '♻️ Restore this reservation?';
        message.innerText = 'This will move it back to pending reservations with pending status.';
        yesBtn.innerText = 'Restore';
        yesBtn.style.background = '#2e7d32';
    } else if (action === 'delete') {
        title.innerText = '🗑️ Delete this reservation?';
        message.innerText = 'This action CANNOT be undone. The reservation will be permanently deleted.';
        yesBtn.innerText = 'Delete';
        yesBtn.style.background = '#c62828';
    }
    
    modal.style.display = 'flex';
}

document.getElementById('confirmYes').onclick = function() {
    if (!pendingAction.id || !pendingAction.type) return;
    
    if (pendingAction.type === 'restore') {
        window.location.href = "completed_reservations.php?restore=" + pendingAction.id;
    } else if (pendingAction.type === 'delete') {
        window.location.href = "completed_reservations.php?delete=" + pendingAction.id;
    }
};

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

// Notification toast
document.addEventListener("DOMContentLoaded", function() {
    const toast = document.getElementById("notifToast");
    if (toast) {
        setTimeout(() => toast.classList.add("show"), 100);
        setTimeout(() => { 
            toast.classList.remove("show"); 
            setTimeout(()=>toast.remove(),400); 
        }, 3000);
    }
});
</script>

</body>
</html>
