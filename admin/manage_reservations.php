<?php
// UPDATED VERSION - Shows grouped multi-vehicle bookings
include '../includes/config.php';
session_name("admin_session");
session_start();
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Approve all vehicles in a booking group
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
        
        // Build vehicle list for email
        $vehicle_list = '';
        foreach ($group as $res) {
            $vehicle_list .= "- {$res['vehicle_make']} {$res['vehicle_model']}<br>";
        }
        
        // Send email (to be implemented with your email function)
        // sendMultiVehicleEmail($group[0]['email'], $group[0]['name'], $vehicle_list, $date, $time);
        
        // Log audit
        $vehicle_count = count($group);
        $desc = "Approved {$vehicle_count} vehicle booking for {$group[0]['name']} on $date at $time by admin '{$_SESSION['username']}'";
        logAudit('RESERVATION_APPROVED', $desc, $_SESSION['user_id'], $_SESSION['username']);
        
        $_SESSION['notif'] = [
            'message' => "✅ Approved booking for {$vehicle_count} vehicle(s)!",
            'type' => 'success'
        ];
    }
    
    header("Location: manage_reservations.php");
    exit();
}

// FETCH GROUPED RESERVATIONS
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
    GROUP BY r.customer_id, r.reservation_date, r.reservation_time
    ORDER BY r.reservation_date DESC, r.reservation_time DESC
");
$reservations_stmt->execute();
$reservations_result = $reservations_stmt->get_result();
?>
<!-- HTML remains similar but displays grouped data -->
<!-- In the table, show vehicle_count and vehicles_list -->
