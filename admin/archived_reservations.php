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

    // --- RESTORE Reservation ---
    if (isset($_GET['restore'])) {
        $id = intval($_GET['restore']); // sanitize input

        $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ? AND archived = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $reservation = $stmt->get_result()->fetch_assoc();

        if ($reservation) {
            $update = $conn->prepare("UPDATE reservations SET archived = 0 WHERE id = ?");
            $update->bind_param("i", $id);
            $update->execute();

            // Log audit trail
            $desc = "Reservation #{$reservation['id']} restored by admin '{$_SESSION['username']}'.";
            logAudit('RESERVATION_RESTORED', $desc, $_SESSION['user_id'], $_SESSION['username']);
        }

        $_SESSION['notif'] = [
            'message' => 'Reservation restored!',
            'type' => 'success'
        ];
        header("Location: archived_reservations.php");
        exit();
    }

    // --- FETCH Archived Reservations ---
    $archived_stmt = $conn->prepare("
        SELECT r.id, c.name AS customer_name, r.vehicle_make, r.vehicle_model,
            r.reservation_date, r.reservation_time, r.status, r.method
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.archived = 1
        ORDER BY r.reservation_date DESC, r.reservation_time DESC
    ");
    $archived_stmt->execute();
    $archived_result = $archived_stmt->get_result();
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Reservations - Auto Repair Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--light-gray); margin:0; }
        .navbar { background: white; box-shadow: var(--shadow-md); display: flex; justify-content: space-between; padding: 15px 30px; align-items: center; border-bottom: 3px solid var(--primary-red); }
        .navbar .logo { color: var(--primary-red); font-size: 1.8rem; font-weight: 800; }
        .navbar ul { list-style: none; display: flex; gap: 15px; margin: 0; padding: 0; flex-wrap: wrap; }
    .navbar ul li a { color: var(--dark-gray); text-decoration: none; font-weight: 600; padding: 8px 15px; border-radius: var(--radius-md); transition: var(--transition-fast); }
    .navbar ul li a.active, .navbar ul li a:hover { background: var(--gradient-primary); color: white; }
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
    .confirm-box h3 { margin-bottom:15px; color:#2e7d32; }
    .confirm-box button { border:none; padding:10px 20px; border-radius:5px; margin:0 10px; cursor:pointer; font-weight:bold; }
    .confirm-yes { background:#2e7d32; color:white; }
    .confirm-no { background:#ccc; }
    </style>
    </head>
    <body>
    <nav class="navbar">
        <div class="logo"> Papsi Paps Admin</div>
    <ul>
        <li><a href="archived_services.php" class="active">Archived Services</a></li>
        <li><a href="manage_reservations.php">Manage Reservations</a></li>
        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>

    </nav>
    <?php if (isset($_SESSION['notif'])): 
    $type = $_SESSION['notif']['type'];
    $message = $_SESSION['notif']['message'];
    $color = $type === 'success' ? '#28a745' : '#dc3545';
    ?>
    <div class="notif-toast" id="notifToast" style="background: <?php echo $color; ?>">
    <?php echo htmlspecialchars($message); ?>
    </div>
    <?php unset($_SESSION['notif']); endif; ?>

    <div class="container">
    <div class="card">
    <h1>Archived Reservations</h1>
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

    <?php while ($row = $archived_result->fetch_assoc()):
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
    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
    <td>
    <?php if(empty($services)) { echo "<em style='color:gray;'>No linked services</em>"; } 
        else { foreach($services as $s){ echo htmlspecialchars($s['service_name'])."<br>"; } } ?>
    </td>
    <td><?php foreach($services as $s){ echo $s['duration']." minutes<br>"; } ?></td>
    <td><?php foreach($services as $s){ echo "₱".number_format($s['price'],2)."<br>"; } ?></td>
    <td><?php echo date("M j, Y", strtotime($row['reservation_date'])); ?></td>
    <td><?php echo date("g:i A", strtotime($row['reservation_time'])); ?></td>
    <td>
    <a href="#" onclick="confirmRestore(<?php echo $row['id']; ?>)">♻️ Restore</a>
    </td>
    </tr>
    <?php endwhile; ?>
    </table>
    </div>
    </div>

    <div id="confirmModal" class="confirm-modal">
    <div class="confirm-box">
    <h3>Restore this reservation?</h3>
    <p>This will move the reservation back to active reservations.</p>
    <button class="confirm-yes" id="confirmYes">Restore</button>
    <button class="confirm-no" onclick="closeModal()">Cancel</button>
    </div>
    </div>

    <script>
    let restoreId = null;
    function confirmRestore(id) {
        restoreId = id;
        const modal = document.getElementById('confirmModal');
        modal.querySelector('h3').innerText = 'Restore this reservation?';
        modal.querySelector('#confirmYes').innerText = 'Restore';
        modal.style.display = 'flex';
    }

    document.getElementById('confirmYes').onclick = function() {
        if (restoreId) {
            window.location.href = "archived_reservations.php?restore=" + restoreId;
        }
    };

    function closeModal() {
        document.getElementById('confirmModal').style.display = 'none';
    }

    document.addEventListener("DOMContentLoaded", function() {
        const toast = document.getElementById("notifToast");
        if (toast) {
            setTimeout(() => toast.classList.add("show"), 100);
            setTimeout(() => { toast.classList.remove("show"); setTimeout(()=>toast.remove(),400); }, 3000);
        }
    });
    </script>

    </body>
    </html>
