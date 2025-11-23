<?php
include '../includes/config.php';
session_start();
$conn = getDBConnection();

// Handle Approve/Decline actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];

    // Get reservation details before update for logging
    $reservationQuery = "SELECT r.*, c.name as customer_name, s.service_name 
                        FROM reservations r 
                        JOIN customers c ON r.customer_id = c.id 
                        JOIN reservation_services rs ON r.id = rs.reservation_id 
                        JOIN services s ON rs.service_id = s.id 
                        WHERE r.id = '$id'";
    $reservationResult = mysqli_query($conn, $reservationQuery);
    $reservation = mysqli_fetch_assoc($reservationResult);

    if ($action == "approve") {
        $oldStatus = $reservation['status'];
        mysqli_query($conn, "UPDATE reservations SET status='approved' WHERE id='$id'");
        
        // Log audit (Option A)
        logAudit(
            'UPDATE',
            "Approved reservation (#$id) for {$reservation['customer_name']} - {$reservation['service_name']}",
            $_SESSION['admin_id'] ?? null,
            $_SESSION['admin_username'] ?? null
        );

    } elseif ($action == "decline") {
        $oldStatus = $reservation['status'];
        mysqli_query($conn, "UPDATE reservations SET status='declined' WHERE id='$id'");
        
        // Log audit (Option A)
        logAudit(
            'UPDATE',
            "Declined reservation (#$id) for {$reservation['customer_name']} - {$reservation['service_name']}",
            $_SESSION['admin_id'] ?? null,
            $_SESSION['admin_username'] ?? null
        );
    }

    header("Location: admin.php");
    exit();
}

// Fetch reservations with customer + service info
$sql = "SELECT r.id, r.vehicle_make, r.vehicle_model, r.vehicle_year,
               r.reservation_date, r.reservation_time, r.status,
               c.name AS customer_name, c.phone, c.email,
               GROUP_CONCAT(s.service_name SEPARATOR ', ') AS service_name
        FROM reservations r
        JOIN customers c ON r.customer_id = c.id
        LEFT JOIN reservation_services rs ON r.id = rs.reservation_id
        LEFT JOIN services s ON rs.service_id = s.id
        GROUP BY r.id
        ORDER BY r.created_at DESC";

$reservations = mysqli_query($conn, $sql);

if (!$reservations) {
    die("Query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Reservations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
      body {
          background: #f5f5f5;
      }
      .container {
          margin-top: 80px;
      }
      .table {
          background: #fff;
          border-radius: 10px;
          overflow: hidden;
          box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
      }
      .status-pending { color: orange; font-weight: bold; }
      .status-approved { color: green; font-weight: bold; }
      .status-declined { color: red; font-weight: bold; }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">Papsi Admin</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link active" href="admin.php">Reservations</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">
  <h2 class="mb-4">📋 Reservation Management</h2>

  <table class="table table-striped table-hover">
      <thead class="table-dark">
          <tr>
              <th>Customer</th>
              <th>Contact</th>
              <th>Vehicle</th>
              <th>Service</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th>Action</th>
          </tr>
      </thead>
      <tbody>
          <?php while ($row = mysqli_fetch_assoc($reservations)) { ?>
              <tr>
                  <td><?= $row['customer_name']; ?></td>
                  <td><?= $row['phone']; ?><br><?= $row['email']; ?></td>
                  <td><?= $row['vehicle_make']." ".$row['vehicle_model']." (".$row['vehicle_year'].")"; ?></td>
                  <td><?= $row['service_name']; ?></td>
                  <td><?= $row['reservation_date']; ?></td>
                  <td><?= $row['reservation_time']; ?></td>
                  <td>
                      <?php
                      if ($row['status'] == "pending") echo "<span class='status-pending'>Pending</span>";
                      if ($row['status'] == "approved") echo "<span class='status-approved'>Approved</span>";
                      if ($row['status'] == "declined") echo "<span class='status-declined'>Declined</span>";
                      ?>
                  </td>
                  <td>
                      <?php if ($row['status'] == "pending") { ?>
                          <a href="admin.php?action=approve&id=<?= $row['id']; ?>" class="btn btn-success btn-sm">Approve</a>
                          <a href="admin.php?action=decline&id=<?= $row['id']; ?>" class="btn btn-danger btn-sm">Decline</a>
                      <?php } else { ?>
                          <em>No action</em>
                      <?php } ?>
                  </td>
              </tr>
          <?php } ?>
      </tbody>
  </table>
</div>

</body>
</html>
