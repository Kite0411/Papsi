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

// --- Filters (same as staff_report.php) ---
$actionType = $_GET['action_type'] ?? '';
$staffId = $_GET['staff_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = [];
$params = [];
$types = '';

// --- Apply filters ---
if ($actionType !== '') {
    if ($actionType === 'ARCHIVE') {
        $where[] = "(
            a.action_type = 'SERVICE_ARCHIVE' OR 
            a.action_type = 'SERVICE_RESTORE' OR 
            a.action_type = 'RESERVATION_ARCHIVED' OR 
            a.action_type = 'RESERVATION_RESTORED'
        )";
    } elseif ($actionType === 'WALKIN') {
        $where[] = "a.action_type = 'WALKIN_ADDED'";
    } elseif ($actionType === 'PAYMENT') {
        $where[] = "(
            a.action_type = 'PAYMENT_VERIFIED' OR 
            a.action_type = 'PAYMENT_REJECTED'
        )";
    } elseif ($actionType === 'RESERVATION') {
        $where[] = "(
            a.action_type = 'RESERVATION_APPROVED' OR 
            a.action_type = 'RESERVATION_DECLINED'
        )";
    } else {
        $where[] = "a.action_type = ?";
        $params[] = $actionType;
        $types .= 's';
    }
}

if ($staffId !== '') {
    $where[] = "a.admin_id = ?";
    $params[] = $staffId;
    $types .= 'i';
}

if ($dateFrom !== '') {
    $where[] = "DATE(a.created_at) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $where[] = "DATE(a.created_at) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

// --- Build query ---
$sql = "SELECT a.* FROM audit_trail a";

if ($where) {
    $whereSql = " WHERE " . implode(" AND ", $where);
    $sql .= $whereSql;
}

$sql .= " ORDER BY a.created_at DESC";

// --- Execute query ---
$stmt = $conn->prepare($sql);
if ($where) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$staffActions = $result->fetch_all(MYSQLI_ASSOC);

// --- Generate filename ---
$filename = 'staff_activity_report_' . date('Y-m-d_His') . '.csv';

// --- CSV Generation ---
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Column headers
fputcsv($output, ['Timestamp', 'Staff Member', 'Staff ID', 'Action Type', 'Description']);

// Data rows
foreach ($staffActions as $action) {
    $timestamp = date('M j, Y g:i A', strtotime($action['created_at']));
    $staffName = $action['admin_username'] ?? 'N/A';
    $staffId = $action['admin_id'] ?? 'N/A';
    $actionType = strtoupper(str_replace('_', ' ', $action['action_type']));
    $description = $action['description'];
    
    fputcsv($output, [
        $timestamp,
        $staffName,
        $staffId,
        $actionType,
        $description
    ]);
}

fclose($output);
exit;
