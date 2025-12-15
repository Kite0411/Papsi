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

// --- Pagination setup ---
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Filters ---
$actionType = $_GET['action_type'] ?? '';
$staffId = $_GET['staff_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = [];
$params = [];
$types = '';

// --- Apply filters ---
// Filter by action type
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

// Filter by staff ID
if ($staffId !== '') {
    $where[] = "a.admin_id = ?";
    $params[] = $staffId;
    $types .= 'i';
}

// Filter by date range
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

// --- Build queries ---
$sql = "SELECT a.* FROM audit_trail a";
$countSql = "SELECT COUNT(*) AS total FROM audit_trail a";

if ($where) {
    $whereSql = " WHERE " . implode(" AND ", $where);
    $sql .= $whereSql;
    $countSql .= $whereSql;
}

$sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$paramsForQuery = $params;
$typesForQuery = $types . 'ii';
$paramsForQuery[] = $limit;
$paramsForQuery[] = $offset;

// --- Execute query ---
$stmt = $conn->prepare($sql);
if (!empty($paramsForQuery)) {
    $stmt->bind_param($typesForQuery, ...$paramsForQuery);
}
$stmt->execute();
$result = $stmt->get_result();
$staffActions = $result->fetch_all(MYSQLI_ASSOC);

// --- Count for pagination ---
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// --- Fetch all staff members for dropdown ---
$staffMembers = $conn->query("
    SELECT DISTINCT admin_id, admin_username 
    FROM audit_trail 
    WHERE admin_username IS NOT NULL
    ORDER BY admin_username
")->fetch_all(MYSQLI_ASSOC);

// --- Get statistics ---
$stats = [];

// Total actions
$stats['total_actions'] = $totalRecords;

// Actions today
$todayResult = $conn->query("
    SELECT COUNT(*) as count 
    FROM audit_trail 
    WHERE DATE(created_at) = CURDATE()
");
$stats['today_actions'] = $todayResult->fetch_assoc()['count'];

// Actions this month
$monthResult = $conn->query("
    SELECT COUNT(*) as count 
    FROM audit_trail 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
");
$stats['month_actions'] = $monthResult->fetch_assoc()['count'];

// Most active staff
$activeStaffResult = $conn->query("
    SELECT admin_username, COUNT(*) as action_count
    FROM audit_trail
    WHERE admin_username IS NOT NULL
    GROUP BY admin_username
    ORDER BY action_count DESC
    LIMIT 1
");
$activeStaff = $activeStaffResult->fetch_assoc();
$stats['most_active'] = $activeStaff ? $activeStaff['admin_username'] : 'N/A';
$stats['most_active_count'] = $activeStaff ? $activeStaff['action_count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Activity Report - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: var(--light-gray);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 80px;
            padding-bottom: 30px;
            margin: 0;
        }
        
        .navbar {
            background: white;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            padding: 15px 30px;
            align-items: center;
            border-bottom: 3px solid var(--primary-red);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .navbar .logo {
            color: var(--primary-red);
            font-size: 1.8rem;
            font-weight: 800;
        }
        
        .navbar-toggle {
            display: none;
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        .navbar ul {
            list-style: none;
            display: flex;
            gap: 15px;
            margin: 0;
            padding: 0;
            flex-wrap: wrap;
        }
        
        .navbar ul li a {
            color: var(--dark-gray);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 15px;
            border-radius: var(--radius-md);
            transition: var(--transition-fast);
        }
        
        .navbar ul li a.active, 
        .navbar ul li a:hover {
            background: var(--gradient-primary);
            color: white;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-red);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(220, 20, 60, 0.2);
        }
        
        .stat-card .icon {
            font-size: 2.5rem;
            color: var(--primary-red);
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--black);
            margin: 10px 0;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .card {
            border: 2px solid rgba(220, 20, 60, 0.1);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 25px;
        }
        
        .card-header {
            background: var(--gradient-primary);
            color: white;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
            font-weight: 700;
            border: none;
            padding: 20px;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead th {
            background: var(--gradient-primary);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Action badges */
        .action-SERVICE_ADD { background: #28a745; }
        .action-SERVICE_UPDATE { background: #ffc107; color: #000; }
        .action-SERVICE_DELETE { background: #dc3545; }
        .action-USER_LOGIN { background: #17a2b8; }
        .action-USER_LOGOUT { background: #6c757d; }
        .action-WALKIN_ADDED { background: #28a745; }
        .action-PAYMENT_VERIFIED { background: #28a745; }
        .action-PAYMENT_REJECTED { background: #dc3545; }
        .action-RESERVATION_APPROVED { background: #20c997; }
        .action-RESERVATION_DECLINED { background: #f10505ff; }
        .action-SERVICE_RESTORE { background: #0dcaf0; }
        .action-SERVICE_ARCHIVE { background: #f10505ff; }
        .action-RESERVATION_RESTORED { background: #0dcaf0; }
        .action-RESERVATION_ARCHIVED { background: #f10505ff; }
        .action-SERVICE_DELETED { background: #f10505ff; }
        
        .badge {
            font-size: 0.8rem;
            padding: 0.5em 0.8em;
            border-radius: 0.4rem;
            font-weight: 600;
            color: white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .btn {
            min-height: 44px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 20, 60, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .pagination {
            justify-content: center;
            margin-top: 30px;
        }
        
        .page-link {
            color: #2c3e50;
            border-color: #dee2e6;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            margin: 0 3px;
        }
        
        .page-link:hover {
            background: var(--primary-red);
            border-color: var(--primary-red);
            color: white;
        }
        
        .page-item.active .page-link {
            background: var(--primary-red);
            border-color: var(--primary-red);
        }
        
        .form-control, 
        .form-select {
            font-size: 16px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            padding: 10px 15px;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .navbar-toggle {
                display: block;
            }
            
            .navbar.collapsed ul {
                display: none;
            }
            
            body {
                padding-top: 70px;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .filter-card {
                padding: 15px;
            }
            
            .card-header {
                font-size: 0.95rem;
                padding: 15px;
            }
            
            .table-responsive {
                border: none;
            }
            
            .table thead {
                display: none;
            }
            
            .table, 
            .table tbody, 
            .table tr, 
            .table td {
                display: block;
                width: 100%;
            }
            
            .table tr {
                margin-bottom: 15px;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 15px;
                background: white;
            }
            
            .table td {
                text-align: left;
                padding: 8px 0;
                border: none;
                position: relative;
                padding-left: 50%;
            }
            
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 45%;
                padding-right: 10px;
                font-weight: 700;
                color: var(--dark-gray);
            }
            
            .badge {
                display: inline-block;
                margin-top: 5px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
            
            .page-item {
                margin: 2px;
            }
        }
        
        @media (max-width: 400px) {
            .navbar .logo {
                font-size: 1.4rem;
            }
            
            .navbar {
                padding: 12px 20px;
            }
        }
    </style>
</head>
<body>

<!-- Navbar with mobile toggle -->
<nav class="navbar collapsed" id="adminNavbar">
    <div class="logo">🔧 Papsi Paps Admin</div>
    
    <button class="navbar-toggle" onclick="toggleNav()">
        <i class="fas fa-bars"></i>
    </button>
    
    <ul id="navMenu">
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="index.php">Dashboard</a></li>
        <?php endif; ?>
        <li><a href="walk_in.php">Manage Walk-In</a></li>
        <li><a href="manage_payments.php">Payments</a></li>
        <li><a href="manage_services.php">Manage Services</a></li>
        <li><a href="manage_reservations.php">Reservations</a></li>
        <li><a href="staff_report.php" class="active">Staff Report</a></li>
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="audit_trail.php">Audit Trail</a></li>
        <?php endif; ?>
        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>
</nav>

<div class="container py-4">
    <h2 class="mb-4">
        <i class="fas fa-chart-bar me-2"></i>Staff Activity Report
    </h2>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon"><i class="fas fa-list-check"></i></div>
            <div class="value"><?php echo number_format($stats['total_actions']); ?></div>
            <div class="label">Total Actions</div>
        </div>
        
        <div class="stat-card">
            <div class="icon"><i class="fas fa-calendar-day"></i></div>
            <div class="value"><?php echo number_format($stats['today_actions']); ?></div>
            <div class="label">Actions Today</div>
        </div>
        
        <div class="stat-card">
            <div class="icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="value"><?php echo number_format($stats['month_actions']); ?></div>
            <div class="label">Actions This Month</div>
        </div>
        
        <div class="stat-card">
            <div class="icon"><i class="fas fa-user-crown"></i></div>
            <div class="value"><?php echo htmlspecialchars($stats['most_active']); ?></div>
            <div class="label">Most Active Staff (<?php echo number_format($stats['most_active_count']); ?> actions)</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
        <h5 class="mb-3">
            <i class="fas fa-filter me-2"></i>Filter Reports
        </h5>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="action_type" class="form-label">Action Type</label>
                <select class="form-select" id="action_type" name="action_type">
                    <option value="">All Actions</option>
                    <option value="WALKIN" <?= $actionType === 'WALKIN' ? 'selected' : '' ?>>Walk-In Services</option>
                    <option value="PAYMENT" <?= $actionType === 'PAYMENT' ? 'selected' : '' ?>>Payment Actions</option>
                    <option value="RESERVATION" <?= $actionType === 'RESERVATION' ? 'selected' : '' ?>>Reservation Actions</option>
                    <option value="ARCHIVE" <?= $actionType === 'ARCHIVE' ? 'selected' : '' ?>>Archive/Restore</option>
                    <option value="USER_LOGIN" <?= $actionType === 'USER_LOGIN' ? 'selected' : '' ?>>Login</option>
                    <option value="USER_LOGOUT" <?= $actionType === 'USER_LOGOUT' ? 'selected' : '' ?>>Logout</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="staff_id" class="form-label">Staff Member</label>
                <select class="form-select" id="staff_id" name="staff_id">
                    <option value="">All Staff</option>
                    <?php foreach ($staffMembers as $staff): ?>
                        <option value="<?= $staff['admin_id'] ?>" <?= $staffId == $staff['admin_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($staff['admin_username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i>Apply Filter
                </button>
            </div>
        </form>
        
        <div class="mt-3">
            <a href="staff_report.php" class="btn btn-secondary me-2">
                <i class="fas fa-redo me-2"></i>Clear Filters
            </a>
            <a href="export_staff_report.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                <i class="fas fa-download me-2"></i>Export to CSV
            </a>
        </div>
    </div>

    <!-- Report Table -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-table me-2"></i>Staff Activity Logs
            <span class="badge bg-light text-dark ms-2"><?= number_format($totalRecords) ?> records</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($staffActions)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No staff activity found matching your filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Staff Member</th>
                                <th>Action Type</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffActions as $action): ?>
                                <tr>
                                    <td data-label="Timestamp">
                                        <small><?= date('M j, Y g:i A', strtotime($action['created_at'])) ?></small>
                                    </td>
                                    <td data-label="Staff Member">
                                        <strong><?= htmlspecialchars($action['admin_username']) ?></strong>
                                    </td>
                                    <td data-label="Action Type">
                                        <span class="badge action-<?= $action['action_type'] ?>">
                                            <?= strtoupper(str_replace('_', ' ', $action['action_type'])) ?>
                                        </span>
                                    </td>
                                    <td data-label="Description">
                                        <?= htmlspecialchars($action['description']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include "logout-modal.php" ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mobile navbar toggle
function toggleNav() {
    const navbar = document.getElementById('adminNavbar');
    const icon = document.querySelector('.navbar-toggle i');
    
    if (navbar.classList.contains('collapsed')) {
        navbar.classList.remove('collapsed');
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
    } else {
        navbar.classList.add('collapsed');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
    }
}

// Auto-collapse on window resize
window.addEventListener('resize', function() {
    const navbar = document.getElementById('adminNavbar');
    if (window.innerWidth > 768) {
        navbar.classList.remove('collapsed');
        const icon = document.querySelector('.navbar-toggle i');
        if (icon) {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
});
</script>
</body>
</html>
