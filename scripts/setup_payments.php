<?php
/**
 * Setup Script for Payments System
 * Run this script once to create the payments table
 */

include '../includes/config.php';

echo "<h2>Setting up Payments System</h2>";

try {
    $conn = getDBConnection();
    
    // Create payments table
    $createPaymentsTable = "
    CREATE TABLE IF NOT EXISTS `payments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `reservation_id` int(11) NOT NULL,
      `account_name` varchar(255) NOT NULL,
      `amount_paid` decimal(10,2) NOT NULL,
      `payment_proof` varchar(255) NOT NULL,
      `payment_status` enum('pending','verified','rejected') DEFAULT 'pending',
      `verified_by` int(11) DEFAULT NULL,
      `verified_at` timestamp NULL DEFAULT NULL,
      `notes` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `reservation_id` (`reservation_id`),
      KEY `payment_status` (`payment_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    if (mysqli_query($conn, $createPaymentsTable)) {
        echo "<p style='color: green;'>✅ Payments table created successfully!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Payments table may already exist or error: " . mysqli_error($conn) . "</p>";
    }
    
    // Add status column to reservations table
    $addStatusColumn = "
    ALTER TABLE `reservations` 
    ADD COLUMN `status` enum('pending','pending_verification','confirmed','completed','cancelled') DEFAULT 'pending';
    ";
    
    if (mysqli_query($conn, $addStatusColumn)) {
        echo "<p style='color: green;'>✅ Status column added to reservations table!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Status column may already exist or error: " . mysqli_error($conn) . "</p>";
    }
    
    // Create uploads directory
    $upload_dir = '../uploads/payments/';
    if (!file_exists($upload_dir)) {
        if (mkdir($upload_dir, 0777, true)) {
            echo "<p style='color: green;'>✅ Payments upload directory created!</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create upload directory</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Payments upload directory already exists!</p>";
    }
    
    echo "<h3>Next Steps</h3>";
    echo "<ol>";
    echo "<li>Test the reservation flow: <a href='../reservations/reservation.php'>Book a Reservation</a></li>";
    echo "<li>After booking, you'll be redirected to the payment page</li>";
    echo "<li>Admin can verify payments in the admin panel</li>";
    echo "</ol>";
    
    echo "<p style='color: blue;'>🎉 Payment system is now ready to use!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
}
h2, h3 {
    color: #2c3e50;
}
p {
    padding: 10px;
    border-radius: 5px;
    background: white;
    margin: 10px 0;
}
ol {
    background: white;
    padding: 20px;
    border-radius: 5px;
}
a {
    color: #1abc9c;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>
