<?php
include '../includes/config.php';
session_name("admin_session");
session_start();
$conn = getDBConnection();

// --- Pagination setup ---
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Filters ---
$actionType = $_GET['action_type'] ?? '';
$adminId = $_GET['admin_id'] ?? '';

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
    } elseif ($actionType === 'ADD') {
        $where[] = "a.action_type = 'SERVICE_ADD'";
    } else {
        $where[] = "a.action_type = ?";
        $params[] = $actionType;
        $types .= 's';
    }
}

if ($adminId !== '') {
    $where[] = "a.admin_id = ?";
    $params[] = $adminId;
    $types .= 'i';
}

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

$stmt = $conn->prepare($sql);
if (!empty($paramsForQuery)) {
    $stmt->bind_param($typesForQuery, ...$paramsForQuery);
}
$stmt->execute();
$result = $stmt->get_result();
$auditLogs = $result->fetch_all(MYSQLI_ASSOC);

// Count for pagination
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// --- Fetch all admins for dropdown ---
$admins = $conn->query("
    SELECT DISTINCT admin_id, admin_username 
    FROM audit_trail 
    WHERE admin_username IS NOT NULL
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - Admin Panel</title>
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
        
        /* Mobile toggle button */
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
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 20px;
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
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stats-card h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stats-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
        .action-USER_REGISTER { background: #007bff; }
        .action-FAILED_LOGIN { background: #fd7e14; }
        .action-RESERVATION_CREATE { background: #20c997; }
        .action-CUSTOMER_CREATE { background: #0dcaf0; }
        .action-CHATBOT_INTERACTION { background: #6610f2; }
        .action-SYSTEM_INIT { background: #343a40; }
        .action-RESERVATION_DELETE { background: #dc3545; }
        .action-PAYMENT_VERIFIED { background: #28a745; }
        .action-WALKIN_ADDED { background: #28a745; }
        .action-PAYMENT_REJECTED { background: #dc3545; }
        .action-RESERVATION_APPROVED { background: #20c997; color: white; }
        .action-SERVICE_RESTORE { background: #0dcaf0; color: white; }
        .action-SERVICE_ARCHIVE { background: #f10505ff; color: white; }
        .action-RESERVATION_RESTORED { background: #0dcaf0; color: white; }
        .action-RESERVATION_ARCHIVED { background: #f10505ff; color: white; }
        .action-SERVICE_DELETED { background: #f10505ff; color: white; }
        .action-RESERVATION_DECLINED { background: #f10505ff; color: white; }
        
        .badge {
            font-size: 0.8rem;
            padding: 0.5em 0.8em;
            border-radius: 0.4rem;
            font-weight: 600;
            text-transform: capitalize;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .pagination {
            justify-content: center;
        }
        
        .page-link {
            color: #2c3e50;
            border-color: #dee2e6;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .page-link:hover {
            background: #1abc9c;
            border-color: #1abc9c;
            color: white;
        }
        
        .page-item.active .page-link {
            background: #2c3e50;
            border-color: #2c3e50;
        }
        
        .json-data {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            max-height: 150px;
            overflow-y: auto;
        }
        
        .btn {
            min-height: 44px;
        }
        
        .form-control, 
        .form-select {
            font-size: 16px; /* Prevents iOS zoom */
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
            
            .filter-card {
                padding: 15px;
            }
            
            .card-header {
                font-size: 0.95rem;
                padding: 12px 15px;
            }
            
            /* Transform table to cards on mobile */
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
            
            .btn {
                font-size: 14px;
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
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="audit_trail.php" class="active">Audit Trail</a></li>
        <?php endif; ?>
        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>
</nav>

<div class="container py-5">

    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="action_type" class="form-label">Action Type</label>
                <select class="form-select" id="action_type" name="action_type">
                    <option value="">All Actions</option>
                    <option value="ADD" <?= $actionType === 'Add' ? 'selected' : '' ?>>Add</option>
                    <option value="UPDATE" <?= $actionType === 'UPDATE' ? 'selected' : '' ?>>Update</option>
                    <option value="ARCHIVE" <?= $actionType === 'ARCHIVE' ? 'selected' : '' ?>>Archive</option>
                    <option value="USER_LOGIN" <?= $actionType === 'USER_LOGIN' ? 'selected' : '' ?>>User Login</option>
                    <option value="USER_LOGOUT" <?= $actionType === 'USER_LOGOUT' ? 'selected' : '' ?>>User Logout</option>
                    <option value="USER_REGISTER" <?= $actionType === 'USER_REGISTER' ? 'selected' : '' ?>>User Register</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="admin_id" class="form-label">Admin</label>
                <select class="form-select" id="admin_id" name="admin_id">
                    <option value="">All Admins</option>
                    <?php foreach ($admins as $admin): ?>
                        <option value="<?= $admin['admin_id'] ?>" <?= $adminId == $admin['admin_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($admin['admin_username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-danger w-100">
                    <i class="fas fa-filter me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
    
    <form method="POST" action="export_audit_trail.php">
        <button type="submit" class="btn btn-primary mb-3">
            <i class="fas fa-download me-2"></i>Export to CSV
        </button>
    </form>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-list me-2"></i>Audit Logs
            <span class="badge bg-light text-dark ms-2"><?= $totalRecords ?> records</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($auditLogs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No audit logs found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-header">
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td data-label="Timestamp">
                                        <small><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></small>
                                    </td>
                                    <td data-label="User">
                                        <strong><?= htmlspecialchars($log['admin_username']) ?></strong>
                                    </td>
                                    <td data-label="Action">
                                        <span class="badge action-<?= $log['action_type'] ?>">
                                            <?= strtoupper($log['action_type']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Description">
                                        <?= ucfirst(htmlspecialchars($log['description'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&action_type=<?= $actionType ?>&admin_id=<?= $adminId ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&action_type=<?= $actionType ?>&admin_id=<?= $adminId ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&action_type=<?= $actionType ?>&admin_id=<?= $adminId ?>">Next</a>
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
