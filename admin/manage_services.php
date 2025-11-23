<?php
include '../includes/config.php';
session_name("admin_session");
session_start();
$conn = getDBConnection();


// --- Add New Service ---
if (isset($_POST['add_service'])) {
    $service_name = sanitizeInput($_POST['service_name']);
    $description  = sanitizeInput($_POST['description']);
    $duration     = sanitizeInput($_POST['duration']);
    $price        = sanitizeInput($_POST['price']);
    $photo        = null;

    // --- Handle photo upload ---
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $photo_name = uniqid('svc_', true) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], '../uploads/' . $photo_name);
            $photo = $photo_name;
        }
    }

    // --- Insert service ---
    $stmt = $conn->prepare("INSERT INTO services (service_name, description, duration, price, photo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdss", $service_name, $description, $duration, $price, $photo);
    $stmt->execute();

    // --- Log audit ---
   // In manage_services.php, after inserting a new service
    $desc = "New service added: $service_name (₱$price, $duration)";
    logAudit('SERVICE_ADD', $desc, $_SESSION['user_id'], $_SESSION['username']);


    $_SESSION['notif'] = ['message' => 'Service added successfully!', 'type' => 'success'];
    header("Location: manage_services.php");
    exit();
}

// --- Archive Service ---
if (isset($_GET['archive'])) {
    $id = intval($_GET['archive']);

    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $service = $stmt->get_result()->fetch_assoc();

    if ($service) {
        $archive = $conn->prepare("UPDATE services SET is_archived = 1 WHERE id = ?");
        $archive->bind_param("i", $id);
        $archive->execute();

        $desc = sprintf("Archived service: %s (₱%s, %s)", $service['service_name'], number_format($service['price'], 2), $service['duration']);
        logAudit('SERVICE_ARCHIVE', $desc, $_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? 'System');
    }

    $_SESSION['notif'] = ['message' => 'Service archived successfully!', 'type' => 'info'];
    header("Location: manage_services.php");
    exit();
}

// --- Fetch Active Services ---
$result = $conn->query("SELECT * FROM services WHERE is_archived = 0 ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Services - Auto Repair Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-wV6RmC8RmFflHVDJtR4eC0VgH6cB8tO+9z1xZq4WgJfl1AhnK0N3d82L7Qo+Qb5Dq1YQv8X3rPj+0Pt6D8VhHw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<style>
/* --- Styles Same as Your Previous Code --- */
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--light-gray); margin: 0; }
.navbar { background: white; box-shadow: var(--shadow-md); display: flex; justify-content: space-between; padding: 15px 30px; align-items: center; border-bottom: 3px solid var(--primary-red); }
.navbar .logo { color: var(--primary-red); font-size: 1.8rem; font-weight: 800; }
.navbar ul { list-style: none; display: flex; gap: 15px; margin: 0; padding: 0; flex-wrap: wrap; }
.navbar ul li a { color: var(--dark-gray); text-decoration: none; font-weight: 600; padding: 8px 15px; border-radius: var(--radius-md); transition: var(--transition-fast); }
.navbar ul li a.active, .navbar ul li a:hover { background: var(--gradient-primary); color: white; }
.container { max-width: 1100px; margin: 100px auto; padding: 0 20px; }
h1 { text-align: center; margin-bottom: 30px; color: var(--black); font-weight: 800; }
.card { background: white; padding: 25px 30px; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 2px solid rgba(220, 20, 60, 0.1); box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 30px; }
form label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--black); }
form input, form textarea { width: 100%; padding: 12px 15px; margin-bottom: 15px; border-radius: var(--radius-md); border: 2px solid #e0e0e0; box-sizing: border-box; transition: var(--transition-fast); }
form input:focus, form textarea:focus { border-color: var(--primary-red); outline: none; box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1); }
form button { padding: 12px 30px; background: var(--gradient-primary); color: white; border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 700; transition: var(--transition-normal); letter-spacing: 0.5px; }
form button:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
table { width: 100%; border-collapse: collapse; }
table th, table td { padding: 15px; text-align: left; }
table th { background: var(--gradient-primary); color: white; font-weight: 700; }
table tr:nth-child(even) { background: var(--light-gray); }
table tr:hover { background: #FFEBEE; }
table a { text-decoration: none; color: var(--primary-red); font-weight: 600; margin-right: 10px; transition: var(--transition-fast); }
table a:hover { color: var(--primary-red-dark); text-decoration: underline; }
.notif-toast { position: fixed; top: 25px; left: 50%; transform: translateX(-50%) translateY(-20px); color: white; padding: 15px 35px; border-radius: 8px; font-weight: 600; font-size: 1rem; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.2); opacity: 0; transition: opacity 0.4s ease, transform 0.4s ease; z-index: 9999; }
.notif-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
/* Confirm modal styles (same as before) */
.confirm-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); justify-content: center; align-items: center; z-index: 9999; }
.confirm-box { background: white; padding: 25px 30px; border-radius: 12px; text-align: center; width: 90%; max-width: 400px; box-shadow: 0 8px 20px rgba(0,0,0,0.2); border-top: 6px solid var(--primary-red); }
.confirm-box h3 { color: var(--primary-red); margin-bottom: 10px; }
.confirm-box p { color: #555; margin-bottom: 20px; }
.confirm-buttons { display: flex; justify-content: center; gap: 15px; }
.confirm-box button { border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; }
.confirm-yes { background: var(--primary-red); color: white; }
.confirm-yes:hover { background: #b71c1c; }
.confirm-no { background: #e0e0e0; }
.confirm-no:hover { background: #ccc; }
</style>
</head>
<body>

<?php if (isset($_SESSION['notif'])): 
    $type = $_SESSION['notif']['type'];
    $message = $_SESSION['notif']['message'];
    $color = $type==='success'?'#28a745':($type==='info'?'#17a2b8':'#dc3545'); 
?>
<div class="notif-toast" id="notifToast" style="background: <?php echo $color; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php unset($_SESSION['notif']); endif; ?>

<!-- Navbar -->
<nav class="navbar">
    <div class="logo">Admin Panel</div>
    <ul>
          <?php if ($_SESSION['role'] === 'superadmin'): ?>
        <li><a href="index.php">&#8592; Back</a></li>
        <li><a href="manage_services.php" class="active">Manage Services</a></li>
        <li><a href="archived_services.php">Archived Services</a></li>
        <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
                <?php endif; ?>

    </ul>
</nav>

<div class="container">

    <!-- Add Service Form -->
    <div class="card">
        <h2>Add New Service</h2>
        <form method="POST" enctype="multipart/form-data">
            <label>Service Name:</label>
            <input type="text" name="service_name" required>
            <label>Description:</label>
            <textarea name="description" required></textarea>
            <label>Duration:</label>
            <input type="text" name="duration" required>
            <label>Price:</label>
            <input type="number" step="0.01" name="price" required>
            <label>Photo:</label>
            <input type="file" name="photo" accept="image/*">
            <button type="submit" name="add_service">Add Service</button>
        </form>
    </div>

    <!-- Active Services Table -->
    <div class="card">
        <h2>Active Services</h2>
        <table>
            <tr><th>ID</th><th>Photo</th><th>Name</th><th>Description</th><th>Duration</th><th>Price</th><th>Action</th></tr>
            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php if($row['photo']): ?><img src="../uploads/<?php echo $row['photo']; ?>" width="80"><?php endif; ?></td>
                <td><?php echo $row['service_name']; ?></td>
                <td><?php echo $row['description']; ?></td>
                <td><?php echo $row['duration']; ?></td>
                <td>₱<?php echo number_format($row['price'],2); ?></td>
                <td>
                    <a href="edit_service.php?id=<?php echo $row['id']; ?>">✏ Edit</a> |
                    <a href="#" onclick="confirmArchive(<?php echo $row['id']; ?>)">📦 Archive</a>
                </td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>

<!-- Archive Confirmation Modal -->
<div id="confirmModal" class="confirm-modal">
  <div class="confirm-box">
    <h3>Archive this service?</h3>
    <p>You can view archived services later.</p>
    <div class="confirm-buttons">
      <button class="confirm-yes" id="confirmYes">Archive</button>
      <button class="confirm-no" onclick="closeModal()">Cancel</button>
    </div>
  </div>
</div>

<?php include "logout-modal.php" ?>
<script>
let archiveId=null;
function confirmArchive(id){archiveId=id;document.getElementById('confirmModal').style.display='flex';}
function closeModal(){document.getElementById('confirmModal').style.display='none';}
document.getElementById('confirmYes').onclick=function(){if(archiveId){window.location.href="manage_services.php?archive="+archiveId;}};
document.addEventListener("DOMContentLoaded",function(){const toast=document.getElementById("notifToast");if(toast){setTimeout(()=>toast.classList.add("show"),100);setTimeout(()=>{toast.classList.remove("show");setTimeout(()=>toast.remove(),400);},3000);}});
</script>
</body>
</html>
