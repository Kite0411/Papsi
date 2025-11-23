<?php
session_name("admin_session");
session_start();

include '../includes/config.php';
$conn = getDBConnection();
include '../includes/audit_trail.php'; // make sure logAudit() is available

// --- Capture admin info before destroying session ---
$admin_id = $_SESSION['user_id'] ?? 0;
$admin_username = $_SESSION['username'] ?? 'Unknown';

// --- Log logout action ---
logAudit('USER_LOGOUT', " '$admin_username' logged out.", $admin_id, $admin_username);

// --- Destroy session ---
session_unset();
session_destroy();

// --- Redirect to login page ---
header("Location: admin_login.php");
exit();
?>
