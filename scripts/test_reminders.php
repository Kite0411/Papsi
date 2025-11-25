<?php
/**
 * Test Reminder System
 * Use this script to test if reminders are working correctly
 * 
 * Usage: php scripts/test_reminders.php
 */

include '../includes/config.php';
$conn = getDBConnection();

date_default_timezone_set('Asia/Manila');

echo "=== Reminder System Test ===\n\n";

// Check if reminder columns exist
$checkColumns = $conn->query("SHOW COLUMNS FROM reservations LIKE 'reminder_3h_sent'");
if ($checkColumns->num_rows == 0) {
    echo "❌ Reminder columns not found. Please run: php scripts/setup_reminders.php\n";
    exit(1);
}
echo "✅ Reminder columns exist\n\n";

// Get upcoming reservations
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
        r.vehicle_year,
        CONCAT(r.reservation_date, ' ', r.reservation_time) AS reservation_datetime
    FROM reservations r
    JOIN customers c ON r.customer_id = c.id
    WHERE r.status IN ('approved', 'confirmed')
    AND r.archived = 0
    AND CONCAT(r.reservation_date, ' ', r.reservation_time) >= NOW()
    ORDER BY r.reservation_date, r.reservation_time
    LIMIT 10
";

$result = $conn->query($query);

if (!$result) {
    echo "❌ Database error: " . $conn->error . "\n";
    exit(1);
}

$current_datetime = new DateTime();
echo "Current time: " . $current_datetime->format('Y-m-d H:i:s') . "\n\n";

if ($result->num_rows == 0) {
    echo "No upcoming reservations found.\n";
    exit(0);
}

echo "Upcoming Reservations:\n";
echo str_repeat("-", 80) . "\n";

while ($reservation = $result->fetch_assoc()) {
    $reservation_datetime = new DateTime($reservation['reservation_datetime']);
    $current_timestamp = $current_datetime->getTimestamp();
    $reservation_timestamp = $reservation_datetime->getTimestamp();
    $hours_until = ($reservation_timestamp - $current_timestamp) / 3600;
    
    echo "Reservation #{$reservation['id']}\n";
    echo "  Customer: {$reservation['customer_name']} ({$reservation['customer_email']})\n";
    echo "  Vehicle: {$reservation['vehicle_make']} {$reservation['vehicle_model']}\n";
    echo "  Date/Time: {$reservation['reservation_date']} {$reservation['reservation_time']}\n";
    echo "  Hours until: " . number_format($hours_until, 2) . " hours\n";
    echo "  3h reminder sent: " . ($reservation['reminder_3h_sent'] ? 'Yes' : 'No') . "\n";
    echo "  Time reminder sent: " . ($reservation['reminder_time_sent'] ? 'Yes' : 'No') . "\n";
    
    // Check if reminders should be sent
    $needs_3h = ($reservation['reminder_3h_sent'] == 0 && $hours_until <= 3 && $hours_until > 2.5);
    $needs_time = ($reservation['reminder_time_sent'] == 0 && $hours_until <= 0.083 && $hours_until >= -0.083);
    
    if ($needs_3h) {
        echo "  ⚠️  Should send 3-hour reminder NOW\n";
    }
    if ($needs_time) {
        echo "  ⚠️  Should send time-based reminder NOW\n";
    }
    if (!$needs_3h && !$needs_time) {
        echo "  ✓ No reminders needed at this time\n";
    }
    
    echo "\n";
}

echo str_repeat("-", 80) . "\n";
echo "\nTo test sending reminders, run: php scripts/send_reminders.php\n";
echo "Or set up a cron job as described in CRON_SETUP.md\n";

$conn->close();
?>
