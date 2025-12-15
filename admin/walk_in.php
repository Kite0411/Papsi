<?php
include '../includes/config.php';
session_name("admin_session");
session_start();
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all services
$services = mysqli_query($conn, "SELECT * FROM services WHERE is_archived = 0");

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $vehicle_make = sanitizeInput($_POST['vehicle_make']);
    $vehicle_model = sanitizeInput($_POST['vehicle_model']);
    $vehicle_year = sanitizeInput($_POST['vehicle_year']);
    $reservation_date = $_POST['reservation_date'];
    $reservation_time = $_POST['reservation_time'];
    $selected_services = $_POST['services'] ?? [];
    $method = 'Walk-In'; // Always Walk-In for this page

    if (
        empty($name) || empty($phone) || empty($email) || empty($vehicle_make) ||
        empty($vehicle_model) || empty($vehicle_year) || empty($reservation_date) ||
        empty($reservation_time) || empty($selected_services)
    ) {
        $message = "All fields are required.";
        $messageType = 'danger';
    } elseif (!validateEmail($email)) {
        $message = "Please enter a valid email address.";
        $messageType = 'danger';
    } else {
        // Insert customer
        $stmt = $conn->prepare("INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $phone, $email);

        if ($stmt->execute()) {
            $customer_id = $conn->insert_id;

            // Calculate total duration for selected services
            $durationQuery = $conn->prepare("
                SELECT SUM(CAST(duration AS UNSIGNED)) AS total_duration 
                FROM services 
                WHERE id IN (" . implode(',', array_fill(0, count($selected_services), '?')) . ")
            ");
            $durationQuery->bind_param(str_repeat('i', count($selected_services)), ...$selected_services);
            $durationQuery->execute();
            $total_duration = (int)$durationQuery->get_result()->fetch_assoc()['total_duration'];

            // Compute end_time
            $end_time = date("H:i:s", strtotime($reservation_time) + ($total_duration * 60));

            // ✅ WALK-IN AUTO-APPROVAL: Set status to 'approved' and archived to 1 (completed)
            $status = 'approved';
            $archived = 1;
            
            $stmt2 = $conn->prepare("
                INSERT INTO reservations 
                (customer_id, vehicle_make, vehicle_model, vehicle_year, reservation_date, reservation_time, end_time, method, status, archived) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // i=int, s=string - customer_id(i), vehicle_make(s), vehicle_model(s), vehicle_year(s), 
            // reservation_date(s), reservation_time(s), end_time(s), method(s), status(s), archived(i)
            $stmt2->bind_param("issssssssi", $customer_id, $vehicle_make, $vehicle_model, $vehicle_year, $reservation_date, $reservation_time, $end_time, $method, $status, $archived);

            if ($stmt2->execute()) {
                $reservation_id = $conn->insert_id;

                // Insert reservation services
                $stmt3 = $conn->prepare("INSERT INTO reservation_services (reservation_id, service_id) VALUES (?, ?)");
                foreach ($selected_services as $service_id) {
                    $stmt3->bind_param("ii", $reservation_id, $service_id);
                    $stmt3->execute();
                }

                // Log audit trail
                $desc = "Walk-in reservation added and automatically completed by " . $_SESSION['username'] . 
                        " for customer: $name ($vehicle_make $vehicle_model, $vehicle_year)";
                logAudit('WALKIN_ADDED', $desc, $_SESSION['user_id'], $_SESSION['username']);

                $message = "✅ Walk-in reservation successfully saved and marked as completed!";
                $messageType = 'success';

            } else {
                $message = "Error creating reservation.";
                $messageType = 'danger';
            }
            $stmt2->close();
        } else {
            $message = "Error creating customer record.";
            $messageType = 'danger';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-In Booking - AutoFix Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-mobile-responsive.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
    
    .reservation-form {
        background: white;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
        border: 2px solid rgba(220, 20, 60, 0.1);
    }
    
    .form-header {
        background: var(--gradient-primary);
        color: white;
        padding: 40px 30px;
        text-align: center;
        position: relative;
    }
    
    .form-header::after {
        content: '';
        position: absolute;
        bottom: -20px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 0;
        border-left: 20px solid transparent;
        border-right: 20px solid transparent;
        border-top: 20px solid var(--primary-red-dark);
    }
    
    .form-header h2 {
        font-size: 2rem;
        font-weight: 800;
        margin: 0 0 10px 0;
        color: white;
    }
    
    .form-header p {
        margin: 0;
        opacity: 0.95;
        font-size: 1.1rem;
        color: white;
    }
    
    .form-body {
        padding: 50px 40px 40px;
    }
    
    .form-control {
        border-radius: var(--radius-md);
        border: 2px solid #e0e0e0;
        transition: var(--transition-fast);
        padding: 12px 15px;
        font-size: 16px;
    }
    
    .form-control:focus {
        border-color: var(--primary-red);
        box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
    }
    
    .form-label {
        font-weight: 600;
        color: var(--black);
        margin-bottom: 8px;
    }
    
    h5 {
        color: var(--primary-red);
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-red);
    }
    
    .btn-primary {
        background: var(--gradient-primary);
        border: none;
        border-radius: var(--radius-md);
        padding: 15px 50px;
        font-weight: 700;
        transition: var(--transition-normal);
        font-size: 1.1rem;
        letter-spacing: 0.5px;
        min-height: 44px;
        width: 100%;
    }
    
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }
    
    .service-card {
        border: 2px solid #e0e0e0;
        border-radius: var(--radius-md);
        padding: 15px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: var(--transition-normal);
        background: white;
    }
    
    .service-card:hover {
        border-color: var(--primary-red);
        transform: translateX(5px);
        box-shadow: var(--shadow-md);
    }
    
    .service-card.selected {
        border-color: var(--primary-red);
        background: #FFEBEE;
        box-shadow: var(--shadow-md);
    }
    
    .service-card strong {
        color: var(--primary-red);
        font-size: 1.05rem;
    }
    
    .alert {
        border-radius: var(--radius-md);
        border: none;
        padding: 15px 20px;
    }
    
    .info-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        border-radius: var(--radius-md);
        margin-bottom: 25px;
        text-align: center;
    }
    
    .info-banner strong {
        display: block;
        font-size: 1.1rem;
        margin-bottom: 5px;
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
        
        .form-header {
            padding: 30px 20px;
        }
        
        .form-header h2 {
            font-size: 1.5rem;
        }
        
        .form-header p {
            font-size: 0.95rem;
        }
        
        .form-body {
            padding: 30px 20px 25px;
        }
        
        .btn-primary {
            padding: 12px 30px;
            font-size: 1rem;
        }
        
        h5 {
            font-size: 1.1rem;
        }
        
        .container {
            padding-left: 15px;
            padding-right: 15px;
        }
    }
    
    @media (max-width: 400px) {
        .form-header::after {
            display: none;
        }
        
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
        <li><a href="walk_in.php" class="active">Manage Walk-In</a></li>
        <li><a href="manage_payments.php">Payments</a></li>
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="manage_services.php">Manage Services</a></li>
        <?php endif; ?>
        <li><a href="manage_reservations.php">Reservations</a></li>
        <li><a href="completed_reservations.php">Completed</a></li>
        <!-- ✅ CORRECTED: Added .php extension to staff report link -->
        <li><a href="staff_report.php">📊 Report</a></li>
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="audit_trail.php">Audit Trail</a></li>
        <?php endif; ?>
        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>
</nav>

<div class="container py-5 mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="info-banner">
                <strong><i class="fas fa-walking"></i> Walk-In Booking</strong>
                <p>Walk-in bookings are automatically approved and marked as completed upon submission.</p>
            </div>
            
            <div class="reservation-form">
                <div class="form-header">
                    <h2>🚗 Walk-In Service Booking</h2>
                    <p>Quick registration for walk-in customers</p>
                </div>
                
                <div class="form-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Personal Information</h5>
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Vehicle Information</h5>
                                <div class="mb-3">
                                    <label class="form-label">Vehicle Make</label>
                                    <input type="text" name="vehicle_make" class="form-control" value="<?php echo htmlspecialchars($_POST['vehicle_make'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Vehicle Model</label>
                                    <input type="text" name="vehicle_model" class="form-control" value="<?php echo htmlspecialchars($_POST['vehicle_model'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Vehicle Year</label>
                                    <input type="text" name="vehicle_year" class="form-control" value="<?php echo htmlspecialchars($_POST['vehicle_year'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">Service Details</h5>
                                <div class="mb-3">
                                    <label class="form-label">Service Date</label>
                                    <input 
                                        type="text" 
                                        id="reservation_date"
                                        name="reservation_date" 
                                        class="form-control" 
                                        placeholder="Select date"
                                        value="<?php echo $_POST['reservation_date'] ?? ''; ?>" 
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Service Time</label>
                                    <select 
                                        name="reservation_time" 
                                        id="reservation_time"
                                        class="form-control" 
                                        required>
                                        <option value="">Select time</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Select Services</h5>
                                <?php 
                                mysqli_data_seek($services, 0); // Reset pointer
                                while ($service = mysqli_fetch_assoc($services)) { ?>
                                    <div class="service-card" onclick="toggleService(<?php echo $service['id']; ?>)">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="services[]" value="<?php echo $service['id']; ?>" id="service_<?php echo $service['id']; ?>">
                                            <label class="form-check-label" for="service_<?php echo $service['id']; ?>">
                                                <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    Duration: <?php echo $service['duration']; ?> | 
                                                    Price: ₱<?php echo number_format($service['price'], 2); ?>
                                                </small>
                                            </label>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check-circle me-2"></i>Complete Walk-In Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
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

    // Service card toggle
    function toggleService(serviceId) {
        const checkbox = document.getElementById('service_' + serviceId);
        const card = checkbox.closest('.service-card');
        
        checkbox.checked = !checkbox.checked;
        
        if (checkbox.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }
    
    // Initialize selected services
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('input[name="services[]"]');
        checkboxes.forEach(checkbox => {
            const card = checkbox.closest('.service-card');
            if (checkbox.checked) {
                card.classList.add('selected');
            }
        });
    });
    
    // Initialize flatpickr
    flatpickr("#reservation_date", {
        dateFormat: "Y-m-d",
        minDate: "today",
        disable: [
            function(date) {
                return date.getDay() === 0; // Disable Sunday
            }
        ]
    });

    function getSelectedServiceIds() {
        const ids = [];
        document.querySelectorAll('input[name="services[]"]:checked').forEach(cb => {
            ids.push(cb.value);
        });
        return ids;
    }

    async function loadAvailableTimes() {
        const date = document.getElementById('reservation_date').value;
        const serviceIds = getSelectedServiceIds();
        const timeSelect = document.getElementById('reservation_time');
        timeSelect.innerHTML = '<option>Loading...</option>';

        if (!date || serviceIds.length === 0) {
            timeSelect.innerHTML = '<option value="">Select date & services first</option>';
            return;
        }

        const res = await fetch(`../reservations/get_available_times.php?date=${date}&services=${serviceIds.join(',')}`);
        const times = await res.json();

        timeSelect.innerHTML = '';
        if (times.length === 0) {
            timeSelect.innerHTML = '<option value="">No available slots</option>';
            return;
        }

        timeSelect.innerHTML = '<option value="">Select time</option>';
        times.forEach(t => {
            const option = document.createElement('option');
            option.value = t;
            option.textContent = new Date(`1970-01-01T${t}:00`)
                .toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            timeSelect.appendChild(option);
        });
    }

    document.getElementById('reservation_date').addEventListener('change', loadAvailableTimes);
    document.querySelectorAll('input[name="services[]"]').forEach(cb => {
        cb.addEventListener('change', loadAvailableTimes);
    });
</script>
</body>
</html>
