<?php
/**
 * Setup Reminder System
 * Adds columns to track reminder emails sent
 */

include '../includes/config.php';
$conn = getDBConnection();

echo "Setting up reminder system...\n\n";

// Check if columns already exist
$checkColumns = $conn->query("SHOW COLUMNS FROM reservations LIKE 'reminder_3h_sent'");
if ($checkColumns->num_rows > 0) {
    echo "⚠️  Reminder columns already exist. Skipping...\n";
    exit;
}

// Add columns to track reminder emails
$sql = "
    ALTER TABLE reservations
    ADD COLUMN reminder_3h_sent TINYINT(1) DEFAULT 0 COMMENT 'Whether 3-hour reminder was sent',
    ADD COLUMN reminder_time_sent TINYINT(1) DEFAULT 0 COMMENT 'Whether time-based reminder was sent',
    ADD COLUMN reminder_3h_sent_at DATETIME NULL COMMENT 'When 3-hour reminder was sent',
    ADD COLUMN reminder_time_sent_at DATETIME NULL COMMENT 'When time-based reminder was sent'
";

if ($conn->query($sql)) {
    echo "✅ Successfully added reminder tracking columns to reservations table!\n";
    echo "\nColumns added:\n";
    echo "  - reminder_3h_sent (TINYINT)\n";
    echo "  - reminder_time_sent (TINYINT)\n";
    echo "  - reminder_3h_sent_at (DATETIME)\n";
    echo "  - reminder_time_sent_at (DATETIME)\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
    exit(1);
}

echo "\n✅ Reminder system setup complete!\n";
echo "\nNext steps:\n";
echo "1. Set up a cron job to run scripts/send_reminders.php every 5-10 minutes\n";
echo "2. See CRON_SETUP.md for detailed instructions\n";

$conn->close();
?>
