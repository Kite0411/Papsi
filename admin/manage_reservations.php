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

            // Fetch reservation for audit log
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

     if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    
    // Fetch reservation to ensure it's pending verification
    $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ? AND status = 'pending_verification'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        // Update the reservation status to approved
        $update = $conn->prepare("UPDATE reservations SET status = 'approved' WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();

        // Fetch the updated status to confirm it's approved
        $stmt = $conn->prepare("SELECT status FROM reservations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $status_result = $stmt->get_result()->fetch_assoc();

        if ($status_result['status'] === 'approved') {
            $desc = "Reservation #{$res['id']} for {$res['vehicle_make']} {$res['vehicle_model']} approved by admin '{$_SESSION['username']}'";
            logAudit('RESERVATION_APPROVED', $desc, $_SESSION['user_id'], $_SESSION['username']);
            $_SESSION['notif'] = ['message' => 'Reservation approved successfully!', 'type' => 'success'];
        }
    }
    
    // Redirect to reload the page with the updated data
    header("Location: manage_reservations.php");
    exit();
}

        // --- DECLINE Reservation ---
        if (isset($_GET['decline'])) {
            $id = intval($_GET['decline']);

            // Fetch reservation limited to actionable states
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



        // --- FETCH Active Reservations ---
        $reservations_stmt = $conn->prepare("
            SELECT r.id, c.name AS customer_name, r.vehicle_make, r.vehicle_model, r.reservation_date, r.reservation_time, r.status, r.method
            FROM reservations r
            JOIN customers c ON r.customer_id = c.id
            WHERE r.archived = 0
            ORDER BY r.reservation_date DESC, r.reservation_time DESC
        ");
        $reservations_stmt->execute();
        $reservations_result = $reservations_stmt->get_result();
        //?>

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
        .card { background:white; padding:30px; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border:2px solid rgba(220,20,60,0.1); }
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
        /* Base style for action buttons */
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
    background-color: #28a745; /* Green for approve */
    color: white;
}

.approve-btn:hover {
    background-color: #218838; /* Darker green on hover */
}

.archive-btn {
    background-color: #f0ad4e; /* Amber for archive */
    color: white;
    margin-left: 10px; /* Space between buttons */
}

.archive-btn:hover {
    background-color: #ec971f; /* Darker amber on hover */
}

.decline-btn {
    background-color: #c62828; /* Red for decline */
    color: white;
    margin-left: 10px;
}

.decline-btn:hover {
    background-color: #b71c1c;
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
        <div class="card">
        <h1>Manage Reservations</h1>
        <table>
        <tr>
        <th>Customer</th>
        <th>Service</th>
        <th>Duration</th>
        <th>Price</th>
        <th>Date</th>
        <th>Time</th>
        <th>Action</th>
        </tr>

        <?php while($row = $reservations_result->fetch_assoc()):
        $res_id = $row['id'];
        // Fetch services using prepared statements
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
        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
        <td>
        <?php if(empty($services)){ echo "<em style='color:gray;'>No linked services</em>"; } else { foreach($services as $s){ echo htmlspecialchars($s['service_name'])."<br>"; } } ?>
        </td>
        <td><?php foreach($services as $s){ echo $s['duration']." minutes<br>"; } ?></td>
        <td><?php foreach($services as $s){ echo "₱".number_format($s['price'],2)."<br>"; } ?></td>
        <td><?php echo date("M j, Y", strtotime($row['reservation_date'])); ?></td>
        <td><?php echo date("g:i A", strtotime($row['reservation_time'])); ?></td>
      <td>
    <?php 
    if ($row['status'] === 'pending_verification' || $row['method'] === 'Walk-In') {
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
