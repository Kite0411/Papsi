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

// --- ARCHIVE Reservation ---
if (isset($_GET['archive'])) {
    $id = intval($_GET['archive']);

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
        $update = $conn->prepare("UPDATE reservations SET archived = 1 WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();

        $desc = "Reservation #{$reservation['id']} ({$reservation['customer_name']} - {$reservation['vehicle_make']} {$reservation['vehicle_model']}) scheduled on {$reservation['reservation_date']} at {$reservation['reservation_time']} was archived by admin '{$_SESSION['username']}'.";
        logAudit('RESERVATION_ARCHIVED', $desc, $_SESSION['user_id'], $_SESSION['username']);
    }

    $_SESSION['notif'] = ['message' => 'Reservation archived!', 'type' => 'info'];
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
        AND r.status NOT IN ('approved', 'declined')
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

// --- FETCH Status Counts ---
$status_counts = [
    'pending_verification' => 0,
    'confirmed' => 0,
    'approved' => 0,
    'declined' => 0,
    'completed' => 0
];

$status_query = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM reservations 
    WHERE archived = 0 
    GROUP BY status
");

while ($row = $status_query->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}

// --- FETCH Active Reservations ---
$reservations_stmt = $conn->prepare("
    SELECT r.id, c.name AS customer_name, r.vehicle_make, r.vehicle_model, 
           r.reservation_date, r.reservation_time, r.status, r.method
    FROM reservations r
    JOIN customers c ON r.customer_id = c.id
    WHERE r.archived = 0
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
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--light-gray); margin:0; }
.navbar { background:white; display:flex; justify-content:space-between; padding:15px 30px; align-items:center; border-bottom:3px solid var(--primary-red); box-shadow:var(--shadow-md); }
.navbar .logo { color:var(--primary-red); font-size:1.8rem; font-weight:800; }
.navbar ul { list-style:none; display:flex; gap:15px; margin:0; padding:0; flex-wrap:wrap; }
.navbar ul li a { color:var(--dark-gray); text-decoration:none; font-weight:600; padding:8px 15px; border-radius:var(--radius-md); transition:var(--transition-fast); }
.navbar ul li a.active, .navbar ul li a:hover { background: var(--gradient-primary); color:white; }
.container { max-width: 1100px; margin: 100px auto; padding: 0 20px; }
h1 { text-align:center; margin-bottom:30px; font-weight:800; }
.card { background:white; padding:30px; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border:2px solid rgba(220,20,60,0.1); margin-bottom:25px; }

/* Status Table Styles */
.status-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 25px;
    text-align: center;
    box-shadow: var(--shadow-md);
    border: 2px solid;
    transition: var(--transition-normal);
}

.status-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.status-card.pending {
    border-color: #FFA726;
    background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%);
}

.status-card.confirmed {
    border-color: #42A5F5;
    background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);
}

.status-card.approved {
    border-color: #66BB6A;
    background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
}

.status-card.declined {
    border-color: #EF5350;
    background: linear-gradient(135deg, #FFEBEE 0%, #FFCDD2 100%);
}

.status-card.completed {
    border-color: #AB47BC;
    background: linear-gradient(135deg, #F3E5F5 0%, #E1BEE7 100%);
}

.status-card .count {
    font-size: 3rem;
    font-weight: 800;
    margin: 10px 0;
}

.status-card.pending .count { color: #F57C00; }
.status-card.confirmed .count { color: #1976D2; }
.status-card.approved .count { color: #388E3C; }
.status-card.declined .count { color: #D32F2F; }
.status-card.completed .count { color: #7B1FA2; }

.status-card .label {
    font-size: 0.95rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #555;
}

.status-card .icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
}

table { width:100%; border-collapse:collapse; }
table th, table td { padding:15px; text-align:left; }
table th { background: var(--gradient-primary); color:white; font-weight:700; }
table tr:nth-child(even) { background: var(--light-gray); }
table tr:hover { background:#FFEBEE; }
table a { text-decoration:none; color:var(--primary-red); font-weight:600; margin-right:10px; transition:var(--transition-fast); }
table a:hover { color:var(--primary-red-dark); text-decoration:underline; }

.notif-toast { position: fixed; top:25px; left:50%; transform:translateX(-50%) translateY(-20px); color:white; padding:15px 35px; border-radius:8px; font-weight:600; font-size:1rem; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.2); opacity:0; transition:opacity 0.4s ease, transform 0.4s ease; z-index:9999; }
.notif-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
.confirm-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; }
.confirm-box { background:white; padding:25px 30px; border-radius:10px; text-align:center; max-width:400px; width:90%; box-shadow:0 5px 20px rgba(0,0,0,0.2); }
.confirm-box h3 { margin-bottom:15px; color:#b71c1c; }
.confirm-box button { border:none; padding:10px 20px; border-radius:5px; margin:0 10px; cursor:pointer; font-weight:bold; }
.confirm-yes { background:#d32f2f; color:white; }
.confirm-no { background:#ccc; }

.action-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 14px;
    text-align: center;
}

.approve-btn {
    background-color: #28a745;
    color: white;
}

.approve-btn:hover {
    background-color: #218838;
}

.archive-btn {
    background-color: #f0ad4e;
    color: white;
    margin-left: 10px;
}

.archive-btn:hover {
    background-color: #ec971f;
}

.decline-btn {
    background-color: #c62828;
    color: white;
    margin-left: 10px;
}

.decline-btn:hover {
    background-color: #b71c1c;
}

@media (max-width: 900px) {
    .navbar { flex-direction:column; align-items:flex-start; gap:15px; }
    .navbar ul { flex-direction:column; width:100%; }
    .navbar ul li a { width:100%; }
    .container { margin: 40px auto; }
}

@media (max-width: 768px) {
    .card { padding:20px; }
    .status-summary { grid-template-columns: 1fr; }
    table, thead, tbody, th, tr { display:block; }
    table thead { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); }
    table tr { background:white; margin-bottom:15px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm, 0 2px 6px rgba(0,0,0,0.1)); padding:10px 0; }
    table td { display:flex; justify-content:space-between; align-items:flex-start; padding:10px 15px; border-bottom:1px solid var(--light-gray); }
    table td:last-child { border-bottom:none; }
    table td::before { content: attr(data-label); font-weight:700; padding-right:10px; flex-basis:45%; color:var(--dark-gray); }
    table td span { display:block; }
    .action-btn { width:100%; margin:6px 0; }
    .archive-btn, .decline-btn { margin-left:0; }
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

<!-- Navbar -->
<nav class="navbar">
<div class="logo">Papsi Paps Admin</div>
<ul>
<?php if($_SESSION['role']==='superadmin'): ?><li><a href="index.php">Dashboard</a></li><?php endif; ?>
<li><a href="walk_in.php">Manage Walk-In</a></li>
<li><a href="manage_payments.php">Payments</a></li>
<?php if ($_SESSION['role'] === 'superadmin'): ?>
<li><a href="manage_services.php">Manage Services</a></li>
<?php endif; ?>
<li><a href="manage_reservations.php" class="active">Reservations</a></li>
<li><a href="archived_reservations.php">Archived Reservations</a></li>
<?php if($_SESSION['role']==='superadmin'): ?><li><a href="audit_trail.php">Audit Trail</a></li><?php endif; ?>
<li><a href="#" onclick="openLogoutModal()">Logout</a></li>
</ul>
</nav>

<div class="container">
<h1>Manage Reservations</h1>

<div class="card">
<table class="reservations-table">
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
            case 'completed':
                echo 'background: #F3E5F5; color: #7B1FA2;';
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
    echo '<button class="action-btn decline-btn" onclick="openActionModal(' . $row['id'] . ', \'decline\')">⛔ Decline</button>';
} else if ($row['status'] === 'declined') {
    echo '<span style="color:#c62828;">Declined</span>';
}
?>
<button class="action-btn archive-btn" onclick="openActionModal(<?php echo $row['id']; ?>, 'archive')">📦 Archive</button>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>

<div id="confirmModal" class="confirm-modal">
<div class="confirm-box">
<h3>Archive this reservation?</h3>
<p>This will move the reservation to archived list.</p>
<button class="confirm-yes" id="confirmYes">Archive</button>
<button class="confirm-no" onclick="closeModal()">Cancel</button>
</div>
</div>

<?php include "logout-modal.php"; ?>

<script>
let pendingAction = { id: null, type: null };
function openActionModal(id, action){
    pendingAction = { id, type: action };
    const modal = document.getElementById('confirmModal');
    const title = action === 'decline' ? 'Decline this reservation?' : 'Archive this reservation?';
    const message = action === 'decline' ? 'This will mark the reservation as declined.' : 'This will move the reservation to archived list.';
    const cta = action === 'decline' ? 'Decline' : 'Archive';
    modal.querySelector('h3').innerText = title;
    modal.querySelector('p').innerText = message;
    modal.querySelector('#confirmYes').innerText = cta;
    modal.style.display = 'flex';
}
document.getElementById('confirmYes').onclick = function(){
    if(!pendingAction.id || !pendingAction.type) return;
    if(pendingAction.type === 'decline'){
        window.location.href = "manage_reservations.php?decline=" + pendingAction.id;
    } else if(pendingAction.type === 'archive'){
        window.location.href = "manage_reservations.php?archive=" + pendingAction.id;
    }
};
function closeModal(){ document.getElementById('confirmModal').style.display='none'; }

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
