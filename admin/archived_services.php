<?php
include '../includes/config.php';
session_name("admin_session");
session_start();
$conn = getDBConnection();

// --- Restore Service ---
if (isset($_GET['restore'])) {
    $id = intval($_GET['restore']);

    // Fetch service before restoring
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc();

    if ($service) {
        // Restore the service
        $restore = $conn->prepare("UPDATE services SET is_archived = 0 WHERE id = ?");
        $restore->bind_param("i", $id);
        $restore->execute();

        // Log the restoration
        $desc = sprintf(
            "Restored service: %s (₱%s, %s)",
            $service['service_name'],
            number_format($service['price'], 2),
            $service['duration']
        );
        logAudit('SERVICE_RESTORE', $desc, $_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? 'System');
    }

    $_SESSION['notif'] = [
        'message' => 'Service restored successfully!',
        'type' => 'success'
    ];
    header("Location: archived_services.php");
    exit();
}

// --- DELETE Service ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Fetch the service details before deleting
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc();

    if ($service) {
        // Delete the service from the database
        $delete = $conn->prepare("DELETE FROM services WHERE id = ?");
        $delete->bind_param("i", $id);
        $delete->execute();

        // Log the deletion action
        $desc = sprintf(
            "Deleted service: %s (₱%s, %s)",
            $service['service_name'],
            number_format($service['price'], 2),
            $service['duration']
        );
        logAudit('SERVICE_DELETED', $desc, $_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? 'System');
    }

    $_SESSION['notif'] = [
        'message' => 'Service deleted successfully!',
        'type' => 'danger'
    ];
    header("Location: archived_services.php");
    exit();
}

// --- Fetch Archived Services ---
$result = $conn->query("SELECT * FROM services WHERE is_archived = 1 ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Archived Services - Auto Repair Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin-mobile-responsive.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    background: var(--light-gray); 
    margin: 0;
    padding-top: 80px;
    padding-bottom: 30px;
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
    background: white; 
    padding: 25px 30px; 
    border-radius: var(--radius-lg); 
    box-shadow: var(--shadow-md); 
    border: 2px solid rgba(220, 20, 60, 0.1); 
    margin-bottom: 30px; 
}

table { 
    width: 100%; 
    border-collapse: collapse; 
}

table th, 
table td { 
    padding: 15px; 
    text-align: left; 
}

table th { 
    background: var(--gradient-primary); 
    color: white; 
    font-weight: 700; 
}

table tr:nth-child(even) { 
    background: var(--light-gray); 
}

table tr:hover { 
    background: #E0F7FA; 
}

table a { 
    text-decoration: none; 
    color: var(--primary-red); 
    font-weight: 600; 
    margin-right: 10px; 
    transition: var(--transition-fast);
    display: inline-block;
    min-height: 44px;
    line-height: 44px;
}

table a:hover { 
    color: var(--primary-red-dark); 
    text-decoration: underline; 
}

.notif-toast { 
    position: fixed; 
    top: 90px;
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
    max-width: 90%;
}

.notif-toast.show { 
    opacity: 1; 
    transform: translateX(-50%) translateY(0); 
}

/* Confirm modal styles */
.confirm-modal { 
    display: none; 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100%; 
    height: 100%; 
    background: rgba(0,0,0,0.6); 
    justify-content: center; 
    align-items: center; 
    z-index: 9999; 
}

.confirm-box { 
    background: white; 
    padding: 25px 30px; 
    border-radius: 12px; 
    text-align: center; 
    width: 90%; 
    max-width: 400px; 
    box-shadow: 0 8px 20px rgba(0,0,0,0.2); 
    border-top: 6px solid var(--primary-red); 
}

.confirm-box h3 { 
    color: var(--primary-red); 
    margin-bottom: 10px; 
}

.confirm-box p { 
    color: #555; 
    margin-bottom: 20px; 
}

.confirm-buttons { 
    display: flex; 
    justify-content: center; 
    gap: 15px;
    flex-wrap: wrap;
}

.confirm-box button { 
    border: none; 
    padding: 10px 20px; 
    border-radius: 6px; 
    font-weight: 600; 
    cursor: pointer; 
    transition: 0.2s;
    min-height: 44px;
    min-width: 44px;
}

.confirm-yes { 
    background: var(--primary-red); 
    color: white; 
}

.confirm-yes:hover { 
    background: #b71c1c; 
}

.confirm-no { 
    background: #e0e0e0; 
}

.confirm-no:hover { 
    background: #ccc; 
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
    
    .card {
        padding: 20px 15px;
    }
    
    .notif-toast {
        top: 80px;
        font-size: 0.9rem;
        padding: 12px 20px;
    }
    
    h2 {
        font-size: 1.3rem;
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

<?php if (isset($_SESSION['notif'])): 
    $type = $_SESSION['notif']['type'];
    $message = $_SESSION['notif']['message'];
    $color = $type === 'success' ? '#28a745' : ($type === 'info' ? '#17a2b8' : '#dc3545'); 
?>
<div class="notif-toast" id="notifToast" style="background: <?php echo $color; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php unset($_SESSION['notif']); endif; ?>

<!-- Navbar with mobile toggle -->
<nav class="navbar collapsed" id="adminNavbar">
    <div class="logo">🔧 Papsi Paps Admin</div>
    
    <button class="navbar-toggle" onclick="toggleNav()">
        <i class="fas fa-bars"></i>
    </button>
    
    <ul id="navMenu">
        <li><a href="archived_services.php" class="active">Archived Services</a></li>
        <li><a href="manage_services.php">Manage Services</a></li>
        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
    </ul>
</nav>

<div class="container">

    <!-- Archived Services Table -->
    <div class="card">
        <h2>Archived Services</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Photo</th>
                    <th>Service Name</th>
                    <th>Description</th>
                    <th>Duration</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                <tr>
                    <td data-label="ID"><?php echo $row['id']; ?></td>
                    <td data-label="Photo">
                        <?php if ($row['photo']): ?>
                            <img src="../uploads/<?php echo $row['photo']; ?>" width="80" alt="Service Photo">
                        <?php endif; ?>
                    </td>
                    <td data-label="Service Name"><?php echo htmlspecialchars($row['service_name']); ?></td>
                    <td data-label="Description"><?php echo htmlspecialchars($row['description']); ?></td>
                    <td data-label="Duration"><?php echo htmlspecialchars($row['duration']); ?></td>
                    <td data-label="Price">₱<?php echo number_format($row['price'], 2); ?></td>
                    <td data-label="Action">
                        <a href="#" onclick="confirmRestore(<?php echo $row['id']; ?>); return false;">♻ Restore</a>
                        <a href="#" onclick="confirmDelete(<?php echo $row['id']; ?>); return false;">❌ Delete</a>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div id="confirmModal" class="confirm-modal">
    <div class="confirm-box">
        <h3>Restore this service?</h3>
        <p>The service will be active again.</p>
        <div class="confirm-buttons">
            <button class="confirm-yes" id="confirmYes">Restore</button>
            <button class="confirm-no" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="confirm-modal">
    <div class="confirm-box">
        <h3>Delete this service?</h3>
        <p>This action cannot be undone.</p>
        <div class="confirm-buttons">
            <button class="confirm-yes" id="confirmDeleteYes">Delete</button>
            <button class="confirm-no" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

<?php include "logout-modal.php" ?>

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

// Restore functionality
let restoreId = null;

function confirmRestore(id) {
    restoreId = id;
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

document.getElementById('confirmYes').onclick = function() {
    if (restoreId) {
        window.location.href = "archived_services.php?restore=" + restoreId;
    }
};

// Delete functionality
let deleteId = null;

function confirmDelete(id) {
    deleteId = id;
    document.getElementById('deleteConfirmModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
}

document.getElementById('confirmDeleteYes').onclick = function() {
    if (deleteId) {
        window.location.href = "archived_services.php?delete=" + deleteId;
    }
};

// Notification toast
document.addEventListener("DOMContentLoaded", function() {
    const toast = document.getElementById("notifToast");
    if (toast) {
        setTimeout(() => toast.classList.add("show"), 100);
        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 400);
        }, 3000);
    }
});
</script>
</body>
</html>
