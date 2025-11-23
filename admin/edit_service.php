<?php
include '../includes/config.php';
session_name("admin_session");
session_start();
$conn = getDBConnection();

// --- Validate ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_services.php");
    exit();
}

$id = intval($_GET['id']);

// --- Fetch existing service ---
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();

if (!$service) {
    header("Location: manage_services.php");
    exit();
}

// --- Update Service ---
if (isset($_POST['update_service'])) {
    $service_name = sanitizeInput($_POST['service_name']);
    $description  = sanitizeInput($_POST['description']);
    $duration     = sanitizeInput($_POST['duration']);
    $price        = sanitizeInput($_POST['price']);

    // --- Store old values ---
    $oldValues = [
        'service_name' => $service['service_name'],
        'description'  => $service['description'],
        'duration'     => $service['duration'],
        'price'        => $service['price']
    ];

    // --- Update in DB ---
    $update = $conn->prepare("UPDATE services SET service_name = ?, description = ?, duration = ?, price = ? WHERE id = ?");
    $update->bind_param("sssdi", $service_name, $description, $duration, $price, $id);
    $update->execute();

    // --- Log audit trail ---
    $desc = "Updated service: $service_name (₱$price, $duration mins)";
    logAudit('SERVICE_UPDATE', $desc, $_SESSION['user_id'], $_SESSION['username']);

    $_SESSION['notif'] = [
        'message' => 'Service Edited successfully!',
        'type' => 'success'
    ];
    header("Location: manage_services.php");
    exit();

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Service - Auto Repair Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: var(--light-gray);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 30px 0;
        }

                .navbar {
            background: white;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            padding: 15px 30px;
            align-items: center;
            border-bottom: 3px solid var(--primary-red);
        }
        
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
        
/* Main Form Card */
.container {
    max-width: 700px;
    margin: 60px auto;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    padding: 40px 50px;
    border: 1px solid rgba(220, 20, 60, 0.1);
}

.container h1 {
    text-align: center;
    color: var(--primary-red);
    font-weight: 800;
    font-size: 2rem;
    margin-bottom: 35px;
    letter-spacing: 0.5px;
}

/* Labels & Inputs */
label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
    margin-top: 18px;
}

input[type="text"],
input[type="number"],
textarea {
    width: 100%;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px 15px;
    font-size: 1rem;
    transition: all 0.25s ease;
    resize: none;
}

input:focus,
textarea:focus {
    border-color: var(--primary-red);
    box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
    outline: none;
}

/* Buttons */
button,
.btn-primary {
    display: inline-block;
    background: var(--gradient-primary);
    color: white;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    padding: 12px 45px;
    font-size: 1.05rem;
    letter-spacing: 0.5px;
    margin-top: 25px;
    cursor: pointer;
    transition: all 0.25s ease;
}

button:hover,
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(220, 20, 60, 0.25);
}

/* Add soft focus on form */
.container form {
    display: flex;
    flex-direction: column;
}

/* Optional: subtle divider line under header */
.container::before {
    content: '';
    display: block;
    width: 60px;
    height: 4px;
    background: var(--primary-red);
    border-radius: 4px;
    margin: 0 auto 25px;
}

/* Responsive */
@media (max-width: 576px) {
    .container {
        padding: 30px 25px;
    }
}

        .notif-toast {
        position: fixed;
        top: 25px;
        left: 50%;
        transform: translateX(-50%) translateY(-20px);
        color: white;
        padding: 15px 35px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 1rem;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        opacity: 0;
        transition: opacity 0.4s ease, transform 0.4s ease;
        z-index: 9999;
    }
    .notif-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    </style>
</head>
<body>
<?php if (isset($_SESSION['notif'])): 
    $type = $_SESSION['notif']['type'];
    $message = $_SESSION['notif']['message'];
    $color = $type === 'success' ? '#28a745' : '#dc3545'; // green or red
?>
<div class="notif-toast" id="notifToast" style="background: <?php echo $color; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php unset($_SESSION['notif']); endif; ?>
<!-- Navbar -->
<nav class="navbar">
    <div class="logo"> Papsi Paps Admin</div>
    <ul>
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="index.php">Dashboard</a></li>
        <?php endif; ?>
        <!-- Always visible -->
        <li><a href="walk_in.php">Manage Walk-In</a></li>
        <li><a href="manage_payments.php">Payments</a></li>
        <li><a href="manage_services.php" class="active">Manage Services</a></li>
        <li><a href="manage_reservations.php">Reservations</a></li>

        <!-- Only visible to Super Admin -->
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            <li><a href="audit_trail.php">Audit Trail</a></li>
        <?php endif; ?>

        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>
</nav>

<div class="container">
    <div class="card">
        <h1>Edit Service</h1>
        <form method="POST">
            <label>Service Name:</label>
            <input type="text" name="service_name" value="<?php echo htmlspecialchars($service['service_name']); ?>" required>

            <label>Description:</label>
            <textarea name="description" required><?php echo htmlspecialchars($service['description']); ?></textarea>

            <label>Duration (e.g., 1 hour):</label>
            <input type="text" name="duration" value="<?php echo htmlspecialchars($service['duration']); ?>" required>

            <label>Price:</label>
            <input type="number" step="0.01" name="price" value="<?php echo $service['price']; ?>" required>

            <button type="submit" name="update_service">Update Service</button>
            <button type="button" class="btn-cancel" onclick="window.location.href='manage_services.php'">Cancel</button>
        </form>
    </div>
</div>
<?php include "logout-modal.php" ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
    const toast = document.getElementById("notifToast");
    if (toast) {
        setTimeout(() => toast.classList.add("show"), 100); // fade in
        setTimeout(() => {
            toast.classList.remove("show"); // fade out
            setTimeout(() => toast.remove(), 400); // remove after fade
        }, 3000); // visible for 3s
    }
});
</script>
</body>
</html>
