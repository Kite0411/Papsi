<?php
include '../includes/config.php';
session_name("admin_session");
session_start();


// --- Database Connection ---
$conn = getDBConnection();

// --- Initialize Counts ---
$total_services = $total_reservations = $total_customers = $pending_payments = 0;

// --- Fetch Service Count ---
if ($result = $conn->query("SELECT COUNT(*) AS total FROM services")) {
    $row = $result->fetch_assoc();
    $total_services = $row['total'] ?? 0;
    $result->free();
}

// --- Fetch Reservation Count ---
if ($result = $conn->query("SELECT COUNT(*) AS total FROM reservations")) {
    $row = $result->fetch_assoc();
    $total_reservations = $row['total'] ?? 0;
    $result->free();
}

// --- Fetch Customer Count ---
if ($result = $conn->query("SELECT COUNT(*) AS total FROM customers")) {
    $row = $result->fetch_assoc();
    $total_customers = $row['total'] ?? 0;
    $result->free();
}

// --- Fetch Pending Payments Count ---
if ($result = $conn->query("SELECT COUNT(*) AS total FROM payments WHERE payment_status = 'pending'")) {
    $row = $result->fetch_assoc();
    $pending_payments = $row['total'] ?? 0;
    $result->free();
}

// --- Fetch Audit Trail Stats ---
$audit_stats = getAuditTrailStats() ?? [];

// --- Recent Activities (latest 10 entries) ---
$recentActivities = getAuditTrail(10, 0);

// --- Hourly Activity for last 24 hours ---
$hourlyActivity = $audit_stats['hourly_activity'] ?? [];

// --- Top Action Types (limit to 5) ---
$topActions = array_slice($audit_stats['by_action'] ?? [], 0, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Auto Repair Shop</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-mobile-responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Keep all your existing styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background: var(--light-gray);
        }
        
        .navbar {
            background: white;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            padding: 15px 30px;
            align-items: center;
            border-bottom: 3px solid var(--primary-red);
            position: relative; /* ADD THIS */
        }
        
        /* ADD THIS - Mobile toggle button */
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
        
        @media (max-width: 768px) {
            .navbar-toggle {
                display: block;
            }
            
            .navbar.collapsed ul {
                display: none;
            }
        }
        
        /* Keep all your other existing styles */
        .navbar .logo {
            color: var(--primary-red);
            font-size: 1.8rem;
            font-weight: 800;
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
        
        .navbar ul li a.active, .navbar ul li a:hover {
            background: var(--gradient-primary);
            color: white;
        }
        
        /* [Keep all your other existing styles] */
        .container {
            padding: 40px 30px;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: var(--transition-normal);
            border: 2px solid transparent;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-red);
        }
        
        .card h2 {
            margin: 10px 0;
            color: var(--primary-red);
            font-size: 2.5rem;
            font-weight: 800;
        }
        
        .card p {
            color: var(--dark-gray);
            margin: 0;
            font-weight: 600;
        }
        
        .card-icon {
            font-size: 2.5rem;
            color: var(--primary-red);
            margin-bottom: 15px;
        }
        
        .chatbot-card {
            background: var(--gradient-primary);
            color: white;
            border-color: var(--primary-red);
        }
        
        .chatbot-card .card-icon {
            color: white;
        }
        
        .chatbot-card h2 {
            color: white;
        }
        
        .chatbot-card p {
            color: rgba(255,255,255,0.95);
        }
        
        footer {
            text-align: center;
            padding: 20px;
            background: var(--black);
            color: white;
            margin-top: 50px;
            border-top: 3px solid var(--primary-red);
        }
        
        .activity-section {
            margin-top: 30px;
        }
        
        .activity-item {
            padding: 12px 18px;
            border-left: 4px solid var(--primary-red);
            background: white;
            margin-bottom: 12px;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            box-shadow: var(--shadow-sm);
            transition: var(--transition-fast);
        }
        
        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-md);
        }
        
        .activity-item.user { border-left-color: #4CAF50; }
        .activity-item.admin { border-left-color: var(--primary-red); }
        .activity-item.customer { border-left-color: #FF9800; }
        .activity-item.system { border-left-color: #9C27B0; }
        .activity-item.chatbot { border-left-color: #00BCD4; }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
        }
        
        .section-title {
            color: var(--black);
            border-bottom: 3px solid var(--primary-red);
            padding-bottom: 15px;
            margin-bottom: 30px;
            font-weight: 800;
        }

        .logout-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            animation: fadeInBg 0.3s ease;
        }

        .logout-modal {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 420px;
            overflow: hidden;
            transform: scale(0.9);
            animation: fadeInModal 0.25s ease forwards;
        }

        .logout-modal-header {
            background: var(--gradient-primary);
            color: white;
            padding: 20px 25px;
            text-align: center;
        }

        .logout-modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: white;
        }

        .logout-modal-body {
            padding: 25px 30px;
            text-align: center;
        }

        .logout-modal-body p {
            color: var(--dark-gray);
            font-size: 1.05rem;
            margin: 0;
        }

        .logout-modal-footer {
            padding: 15px 25px 25px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .btn-cancel,
        .btn-logout {
            border: none;
            border-radius: var(--radius-md);
            padding: 10px 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            font-size: 1rem;
        }

        .btn-cancel {
            background: #e0e0e0;
            color: #333;
        }

        .btn-cancel:hover {
            background: #cacaca;
        }

        .btn-logout {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        @keyframes fadeInBg {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInModal {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>

<!-- UPDATED NAVBAR with mobile toggle -->
<nav class="navbar collapsed" id="adminNavbar">
    <div class="logo">Papsi Paps Admin</div>
    
    <!-- Mobile toggle button -->
    <button class="navbar-toggle" onclick="toggleNav()">
        <i class="fas fa-bars"></i>
    </button>
    
    <ul id="navMenu">
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="index.php" class="active">Dashboard</a></li>
        <?php endif; ?>
        <li><a href="walk_in.php">Manage Walk-In</a></li>
        <li><a href="manage_payments.php">Payments</a></li>
        <li><a href="manage_services.php">Manage Services</a></li>
        <li><a href="manage_reservations.php">Reservations</a></li>
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="audit_trail.php">Audit Trail</a></li>
        <?php endif; ?>
        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <h2>Dashboard Overview</h2>
    <div class="dashboard">
        <div class="card">
            <div class="card-icon">
                <i class="fas fa-tools"></i>
            </div>
            <h2><?php echo $total_services; ?></h2>
            <p>Total Services</p>
        </div>
        <div class="card">
            <div class="card-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h2><?php echo $total_reservations; ?></h2>
            <p>Total Reservations</p>
        </div>
        <div class="card">
            <div class="card-icon">
                <i class="fas fa-users"></i>
            </div>
            <h2><?php echo $total_customers; ?></h2>
            <p>Total Customers</p>
        </div>
        <div class="card">
            <div class="card-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <h2><?php echo $pending_payments; ?></h2>
            <p>Pending Payments</p>
        </div>
    </div>
    
    <?php include '../chatbot/admin_chatbot.php'; ?>
    
    <!-- System Activity Section -->
    <div class="activity-section">
        <h2 class="section-title">
            <i class="fas fa-chart-line me-2"></i> System Activity Monitor
        </h2>
        
        <div class="dashboard">
            <!-- Activity Chart -->
            <div class="card" style="grid-column: 1 / -1;">
                <h3 style="margin-bottom: 20px; color: #2c3e50;">
                    Activity Over Time (Last 24 Hours)
                </h3>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<footer>
    &copy; <?php echo date("Y"); ?> Auto Repair Shop | Admin Panel
</footer>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal-overlay">
    <div class="logout-modal">
        <div class="logout-modal-header">
            <h2>Confirm Logout</h2>
        </div>
        <div class="logout-modal-body">
            <p>Are you sure you want to log out of your admin account?</p>
        </div>
        <div class="logout-modal-footer">
            <button class="btn-cancel" onclick="closeLogoutModal()">Cancel</button>
            <button class="btn-logout" onclick="confirmLogout()">Logout</button>
        </div>
    </div>
</div>

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
        document.querySelector('.navbar-toggle i').classList.remove('fa-times');
        document.querySelector('.navbar-toggle i').classList.add('fa-bars');
    } else if (!navbar.classList.contains('collapsed')) {
        navbar.classList.add('collapsed');
    }
});

// Chart initialization
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('activityChart')?.getContext('2d');
    if (!ctx) return;

    const hourlyData = <?= json_encode($hourlyActivity ?? []) ?>;

    // Prepare 24-hour dataset
    const hours = Array.from({ length: 24 }, (_, i) => `${i}:00`);
    const counts = hours.map((_, i) => {
        const match = hourlyData.find(h => parseInt(h.hour) === i);
        return match ? parseInt(match.count) : 0;
    });

    // Create line chart with responsive options
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: hours,
            datasets: [{
                label: 'Hourly Activities',
                data: counts,
                borderColor: '#DC143C',
                backgroundColor: 'rgba(220, 20, 60, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#DC143C'
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Hour of the Day'
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: 'Number of Actions'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` ${context.parsed.y} activity${context.parsed.y === 1 ? '' : 'ies'}`;
                        }
                    }
                }
            }
        }
    });
});

// Logout modal functions
function openLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function confirmLogout() {
    const modal = document.getElementById('logoutModal');
    modal.style.opacity = '0';
    setTimeout(() => {
        window.location.href = '../auth/logout.php';
    }, 250);
}

// Auto-refresh every 60 seconds
setTimeout(() => location.reload(), 60000);
</script>

</body>
</html>
