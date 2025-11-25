<?php
/**
 * Reservation Reminder System
 * Sends automated email reminders:
 * - 3 hours before reservation time
 * - At exact reservation time
 * 
 * Run this script via cron job every 5-10 minutes
 */

// Prevent direct browser access (optional, comment out for testing)
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_key'])) {
    // Allow web access only with secret key for testing
    $cron_key = $_GET['cron_key'] ?? '';
    if ($cron_key !== 'your_secret_cron_key_here') {
        die('Access denied. This script should be run via cron job.');
    }
}

include '../includes/config.php';
$conn = getDBConnection();

// Set timezone (adjust to your timezone)
date_default_timezone_set('Asia/Manila'); // Change to your timezone

$current_datetime = new DateTime();
$current_date = $current_datetime->format('Y-m-d');
$current_time = $current_datetime->format('H:i:s');
$current_timestamp = $current_datetime->getTimestamp();

$reminders_sent = 0;
$errors = [];

// Get approved/confirmed reservations that haven't passed yet
$query = "
    SELECT 
        r.id,
        r.reservation_date,
        r.reservation_time,
        r.reminder_3h_sent,
        r.reminder_time_sent,
        r.status,
        c.email AS customer_email,
        c.name AS customer_name,
        r.vehicle_make,
        r.vehicle_model,
        r.vehicle_year
    FROM reservations r
    JOIN customers c ON r.customer_id = c.id
    WHERE r.status IN ('approved', 'confirmed')
    AND r.archived = 0
    AND CONCAT(r.reservation_date, ' ', r.reservation_time) >= NOW()
    AND (
        (r.reminder_3h_sent = 0) OR 
        (r.reminder_time_sent = 0)
    )
    ORDER BY r.reservation_date, r.reservation_time
";

$result = $conn->query($query);

if (!$result) {
    error_log("Reminder script error: " . $conn->error);
    die("Database error: " . $conn->error);
}

while ($reservation = $result->fetch_assoc()) {
    $reservation_datetime = new DateTime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']);
    $reservation_timestamp = $reservation_datetime->getTimestamp();
    
    // Calculate time difference in hours
    $hours_until = ($reservation_timestamp - $current_timestamp) / 3600;
    
    // Check if we need to send 3-hour reminder (between 2.5 and 3 hours before)
    if ($reservation['reminder_3h_sent'] == 0 && $hours_until <= 3 && $hours_until > 2.5) {
        // Send 3-hour reminder
        if (sendReminderEmail($reservation, '3h', $conn)) {
            $reminders_sent++;
        }
    }
    
    // Check if we need to send time-based reminder (at exact time or within 5 minutes after)
    // This sends when reservation time is reached (0 to 5 minutes after)
    if ($reservation['reminder_time_sent'] == 0 && $hours_until <= 0.083 && $hours_until >= -0.083) {
        // Send time-based reminder (at exact reservation time)
        if (sendReminderEmail($reservation, 'time', $conn)) {
            $reminders_sent++;
        }
    }
}

// Log summary
if (php_sapi_name() === 'cli') {
    echo "Reminder script executed at " . date('Y-m-d H:i:s') . "\n";
    echo "Reminders sent: $reminders_sent\n";
    if (!empty($errors)) {
        echo "Errors: " . implode("\n", $errors) . "\n";
    }
}

$conn->close();

/**
 * Send reminder email to customer
 */
function sendReminderEmail($reservation, $type, $conn) {
    if (empty($reservation['customer_email'])) {
        error_log("No email found for reservation #{$reservation['id']}");
        return false;
    }
    
    if (!function_exists('sendEmail')) {
        error_log("sendEmail() function not found");
        return false;
    }
    
    $name = htmlspecialchars($reservation['customer_name']);
    $date = htmlspecialchars($reservation['reservation_date']);
    $time = htmlspecialchars(date("g:i A", strtotime($reservation['reservation_time'])));
    $vehicle = htmlspecialchars($reservation['vehicle_make'] . ' ' . $reservation['vehicle_model'] . ' (' . $reservation['vehicle_year'] . ')');
    
    if ($type === '3h') {
        $subject = "Reminder: Your Reservation is in 3 Hours - Auto Repair Center";
        $body_html = "
            <p>Dear <b>$name</b>,</p>
            <p>This is a friendly reminder that your reservation is scheduled in <b>3 hours</b>.</p>
            <p><b>Reservation Details:</b></p>
            <ul>
                <li><b>Vehicle:</b> $vehicle</li>
                <li><b>Date:</b> $date</li>
                <li><b>Time:</b> $time</li>
            </ul>
            <p>Please arrive on time. We look forward to serving you!</p>
            <p>Thank you,<br><b>AutoRepair Center</b></p>
        ";
        $body_text = "Dear $name,\n\nThis is a friendly reminder that your reservation is scheduled in 3 hours.\n\nReservation Details:\n- Vehicle: $vehicle\n- Date: $date\n- Time: $time\n\nPlease arrive on time. We look forward to serving you!\n\nThank you,\nAutoRepair Center";
        
        $column = 'reminder_3h_sent';
        $timestamp_column = 'reminder_3h_sent_at';
    } else {
        // Time-based reminder
        $subject = "Reminder: Your Reservation Time is Now - Auto Repair Center";
        $body_html = "
            <p>Dear <b>$name</b>,</p>
            <p>This is a reminder that your reservation time is <b>now</b>.</p>
            <p><b>Reservation Details:</b></p>
            <ul>
                <li><b>Vehicle:</b> $vehicle</li>
                <li><b>Date:</b> $date</li>
                <li><b>Time:</b> $time</li>
            </ul>
            <p>Please proceed to our service center. We're ready to assist you!</p>
            <p>Thank you,<br><b>AutoRepair Center</b></p>
        ";
        $body_text = "Dear $name,\n\nThis is a reminder that your reservation time is now.\n\nReservation Details:\n- Vehicle: $vehicle\n- Date: $date\n- Time: $time\n\nPlease proceed to our service center. We're ready to assist you!\n\nThank you,\nAutoRepair Center";
        
        $column = 'reminder_time_sent';
        $timestamp_column = 'reminder_time_sent_at';
    }
    
    list($sent, $err) = sendEmail($reservation['customer_email'], $subject, $body_html, $body_text);
    
    if ($sent) {
        // Update database to mark reminder as sent
        $update = $conn->prepare("
            UPDATE reservations 
            SET $column = 1, $timestamp_column = NOW() 
            WHERE id = ?
        ");
        $update->bind_param("i", $reservation['id']);
        $update->execute();
        $update->close();
        
        error_log("Reminder email sent to {$reservation['customer_email']} for reservation #{$reservation['id']} (type: $type)");
        return true;
    } else {
        error_log("Failed to send reminder email to {$reservation['customer_email']} for reservation #{$reservation['id']}: $err");
        return false;
    }
}
?>
