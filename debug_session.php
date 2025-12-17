<?php
/**
 * DEBUG SCRIPT - Check Session and Database Flow
 * Place this in your web root and access it while logged in
 */

session_name("customer_session");
session_start();

include 'includes/config.php';

echo "<h1>🔍 Session Debug</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} code{background:#f0f0f0;padding:2px 6px;}</style>";

// Step 1: Check Session
echo "<h2>Step 1: Check PHP Session</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p class='success'>✅ Session active</p>";
    echo "<p>Session Name: <code>" . session_name() . "</code></p>";
    echo "<p>Session ID: <code>" . session_id() . "</code></p>";
    echo "<p>user_id from session: <code>" . $_SESSION['user_id'] . "</code></p>";
    echo "<p>username from session: <code>" . ($_SESSION['username'] ?? 'Not set') . "</code></p>";
    
    $userId = $_SESSION['user_id'];
    
    // Step 2: Check users table
    echo "<h2>Step 2: Query Users Table</h2>";
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo "<p class='success'>✅ User found in users table</p>";
            echo "<table border='1' cellpadding='10' style='border-collapse:collapse;margin:20px 0;'>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>User ID</td><td><strong>" . $row['id'] . "</strong></td></tr>";
            echo "<tr><td>Username</td><td>" . $row['username'] . "</td></tr>";
            echo "<tr><td>Email</td><td>" . $row['email'] . "</td></tr>";
            echo "</table>";
        } else {
            echo "<p class='error'>❌ User NOT found in users table</p>";
        }
        $stmt->close();
        
        // Step 3: Check customers table
        echo "<h2>Step 3: Query Customers Table</h2>";
        $stmt = $conn->prepare("SELECT id, user_id, name, phone, email FROM customers WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo "<p class='success'>✅ Customer record found!</p>";
            echo "<table border='1' cellpadding='10' style='border-collapse:collapse;margin:20px 0;'>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>Customer ID</td><td><strong style='color:red;font-size:20px;'>" . $row['id'] . "</strong></td></tr>";
            echo "<tr><td>User ID</td><td>" . $row['user_id'] . "</td></tr>";
            echo "<tr><td>Name</td><td>" . htmlspecialchars($row['name']) . "</td></tr>";
            echo "<tr><td>Phone</td><td>" . htmlspecialchars($row['phone']) . "</td></tr>";
            echo "<tr><td>Email</td><td>" . htmlspecialchars($row['email']) . "</td></tr>";
            echo "</table>";
            
            $customerId = $row['id'];
            
            // Step 4: Check reservations
            echo "<h2>Step 4: Query Reservations</h2>";
            $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE customer_id = ? AND archived = 0");
            $stmt2->bind_param("i", $customerId);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $countRow = $result2->fetch_assoc();
            
            echo "<p>Reservations found: <strong>" . $countRow['count'] . "</strong></p>";
            
            if ($countRow['count'] > 0) {
                echo "<p class='success'>✅ Customer has reservations</p>";
                
                // Show sample reservation
                $stmt3 = $conn->prepare("SELECT id, reservation_date, reservation_time, status, vehicle_make, vehicle_model FROM reservations WHERE customer_id = ? AND archived = 0 LIMIT 3");
                $stmt3->bind_param("i", $customerId);
                $stmt3->execute();
                $result3 = $stmt3->get_result();
                
                echo "<table border='1' cellpadding='10' style='border-collapse:collapse;margin:20px 0;'>";
                echo "<tr><th>ID</th><th>Date</th><th>Time</th><th>Vehicle</th><th>Status</th></tr>";
                while ($resRow = $result3->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $resRow['id'] . "</td>";
                    echo "<td>" . $resRow['reservation_date'] . "</td>";
                    echo "<td>" . $resRow['reservation_time'] . "</td>";
                    echo "<td>" . $resRow['vehicle_make'] . " " . $resRow['vehicle_model'] . "</td>";
                    echo "<td>" . $resRow['status'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='error'>⚠️ No reservations found</p>";
            }
            
            // Step 5: Test what chatbot will receive
            echo "<h2>Step 5: What Chatbot Will Receive</h2>";
            echo "<div style='background:#f0f0f0;padding:20px;border-radius:8px;'>";
            echo "<p><strong>X-Customer-ID Header:</strong> <code>" . $customerId . "</code></p>";
            echo "<p><strong>customer_id in JSON:</strong> <code>" . $customerId . "</code></p>";
            echo "</div>";
            
            echo "<h2>✅ CONCLUSION</h2>";
            echo "<p class='success' style='font-size:18px;'>";
            echo "Everything looks good! Customer ID <strong>" . $customerId . "</strong> should be passed to the chatbot.";
            echo "</p>";
            
        } else {
            echo "<p class='error'>❌ PROBLEM FOUND: No customer record for user_id " . $userId . "</p>";
            echo "<p>This means you're logged in as a user but don't have a customer profile.</p>";
            echo "<p><strong>Solution:</strong> Create a customer record or check if your account was created before the customers table existed.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Database error: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p class='error'>❌ NOT LOGGED IN</p>";
    echo "<p>Please <a href='auth/login.php'>login</a> first.</p>";
}

echo "<hr>";
echo "<h2>All Session Variables</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>
