<?php
include '../includes/config.php';
session_name("admin_session");
session_start();
$conn = getDBConnection();

// --- Filters ---
$actionType = $_GET['action_type'] ?? '';
$adminId = $_GET['admin_id'] ?? '';

$where = [];
$params = [];
$types = '';

if ($actionType !== '') {
    $where[] = "a.action_type = ?";
    $params[] = $actionType;
    $types .= 's';
}
if ($adminId !== '') {
    $where[] = "a.admin_id = ?";
    $params[] = $adminId;
    $types .= 'i';
}

$sql = "SELECT a.* FROM audit_trail a";
if ($where) {
    $whereSql = " WHERE " . implode(" AND ", $where);
    $sql .= $whereSql;
}

// --- Execute query ---
$stmt = $conn->prepare($sql);
if ($where) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$auditLogs = $result->fetch_all(MYSQLI_ASSOC);

// --- CSV Generation ---
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="audit_trail.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Timestamp', 'User', 'Action', 'Description']); // Column headers

foreach ($auditLogs as $log) {
    // Debugging: Print the raw 'created_at' value to check if it's valid
    // Uncomment the following line if you want to debug
    // var_dump($log['created_at']);  

    // Check if 'created_at' is a valid timestamp and format it
    $createdAt = strtotime($log['created_at']);
    if ($createdAt === false) {
        $createdAt = 'Invalid Date'; // Handle invalid date if necessary
    } else {
        $createdAt = date('M j, Y g:i A', $createdAt); // Format as 'Month day, Year Hour:Minute AM/PM'
    }

    // Output the data to CSV
    fputcsv($output, [
        $createdAt, // Timestamp
        $log['admin_username'],
        strtoupper($log['action_type']),
        ucfirst($log['description']),
    ]);
}

fclose($output);
exit;
