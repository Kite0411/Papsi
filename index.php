<?php
include 'includes/config.php';
session_name("customer_session");
session_start();

$conn = getDBConnection();

$user_id = $_SESSION['user_id'] ?? null;
$customer = null;
$user = null;
$reservations = null;

// Only fetch data if user is logged in
if ($user_id) {
    // Fetch customer details
    $stmt = $conn->prepare("SELECT * FROM customers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $customerResult = $stmt->get_result();
    if ($customerResult->num_rows > 0) {
        $customer = $customerResult->fetch_assoc();
    }
    $stmt->close();

    // Fetch user account info
    $userResult = $conn->query("SELECT username, email, created_at FROM users WHERE id = $user_id");
    $user = $userResult->fetch_assoc();

    // Fetch reservations of this user
    $reservationsQuery = "
        SELECT r.*, 
               GROUP_CONCAT(s.service_name SEPARATOR ', ') AS service_list
        FROM reservations r
        LEFT JOIN reservation_services rs ON r.id = rs.reservation_id
        LEFT JOIN services s ON rs.service_id = s.id
        WHERE r.customer_id = (
            SELECT id FROM customers WHERE user_id = $user_id
        )
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ";
    $reservations = $conn->query($reservationsQuery);
}

// Fetch services (available for all)
$services = $conn->query("SELECT * FROM services");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Papsi Repair Shop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
      body {
          padding-top: 70px;
          margin: 0;
      }
      
      .hero {
          background: url('bg/bg.jpg');
          background-size: cover;
          background-position: center;
          background-repeat: no-repeat;
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: start;
          color: white;
          text-align: start;
          position: relative;
          overflow: hidden;
          padding: 20px;
      }
      
      .hero::before {
          content: '';
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background-size: cover;
          opacity: 0.3;
      }
      
      .hero-content {
          position: relative;
          z-index: 1;
          max-width: 800px;
          padding: 0 20px;
      }
      
      .hero h1 {
          font-size: 3.5rem;
          font-weight: 800;
          color: white;
          margin-bottom: 20px;
          text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
          animation: fadeInUp 0.8s ease;
      }
      
      .hero p {
          font-size: 1.4rem;
          color: white;
          margin-bottom: 30px;
          opacity: 0.95;
          animation: fadeInUp 1s ease;
      }
      
      @keyframes fadeInUp {
          from {
              opacity: 0;
              transform: translateY(30px);
          }
          to {
              opacity: 1;
              transform: translateY(0);
          }
      }
      
      .btn-custom {
          background: white;
          color: var(--primary-red);
          padding: 15px 40px;
          border-radius: var(--radius-md);
          font-size: 1.1rem;
          font-weight: 700;
          border: none;
          box-shadow: var(--shadow-lg);
          transition: var(--transition-normal);
          animation: fadeInUp 1.2s ease;
          min-height: 44px;
          display: inline-block;
      }
      
      .btn-custom:hover {
          background: var(--primary-red);
          color: white;
          transform: translateY(-3px);
          box-shadow: var(--shadow-xl);
      }
      
      .services {
          padding: 80px 20px;
          background: var(--light-gray);
      }
      
      .services h2 {
          text-align: center;
          margin-bottom: 50px;
          font-weight: 800;
          color: var(--black);
          position: relative;
          padding-bottom: 20px;
      }
      
      .services h2::after {
          content: '';
          position: absolute;
          bottom: 0;
          left: 50%;
          transform: translateX(-50%);
          width: 80px;
          height: 4px;
          background: var(--gradient-primary);
          border-radius: 2px;
      }
      
      .service-card {
          background: white;
          border-radius: var(--radius-lg);
          overflow: hidden;
          box-shadow: var(--shadow-md);
          transition: var(--transition-normal);
          border: 2px solid transparent;
          height: 100%;
      }
      
      .service-card:hover {
          transform: translateY(-10px);
          box-shadow: var(--shadow-xl);
          border-color: var(--primary-red);
      }
      
      .service-card img {
          width: 100%;
          height: 200px;
          object-fit: cover;
      }
      
      .service-card .card-body {
          padding: 25px;
      }
      
      .service-card .card-title {
          color: var(--primary-red);
          font-weight: 700;
          font-size: 1.3rem;
          margin-bottom: 15px;
      }
      
      .service-card .card-text {
          color: var(--dark-gray);
          line-height: 1.6;
      }
      
      .service-card strong {
          color: var(--primary-red);
      }
      
      .contact-section {
          padding: 80px 20px;
          background: white;
      }
      
      .contact-section h2 {
          text-align: center;
          margin-bottom: 50px;
          font-weight: 800;
          color: var(--black);
          position: relative;
          padding-bottom: 20px;
      }
      
      .contact-section h2::after {
          content: '';
          position: absolute;
          bottom: 0;
          left: 50%;
          transform: translateX(-50%);
          width: 80px;
          height: 4px;
          background: var(--gradient-primary);
          border-radius: 2px;
      }
      
      .contact-info h5 {
          color: var(--primary-red);
          font-weight: 700;
          margin-bottom: 10px;
      }
      
      .contact-info p {
          color: var(--dark-gray);
          margin-bottom: 25px;
      }
      
      .footer {
          background: var(--black);
          color: white;
          padding: 30px 20px;
          text-align: center;
          border-top: 3px solid var(--primary-red);
      }
      
      .footer p {
          color: white;
          margin: 0;
      }

      .modal-content {
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      }

      .modal-header {
        background: #e63946;
      }

      .table > :not(caption) > * > * {
        padding: 0.75rem;
        vertical-align: middle;
      }

      .modal-footer button a:hover{
        color: white;
      }

      #changePasswordForm input {
        border-radius: 8px;
        font-size: 16px; /* Prevents iOS zoom */
      }

      #changePasswordForm button {
        border-radius: 8px;
        padding: 8px 20px;
        min-height: 44px;
      }

      .highlight-red {
        color: #c40e0eff;
        font-weight: bold;
        text-shadow: 1px 1px 3px rgba(255, 0, 0, 0.5);
      }

      .bg-primary-red {
        background:linear-gradient(135deg,#DC143C,#B71C1C);
        box-shadow: var(--shadow-md);
        border-bottom: 3px solid var(--primary-red);
      }

      /* Modern Table Design */
      .modern-table {
        border: none;
        background: #ffffff;
      }

      .modern-table thead.table-header {
        background: var(--primary-red, #c62828);
        letter-spacing: 0.5px;
      }

      .modern-table th {
        border: none;
        padding: 14px;
        text-transform: uppercase;
        font-size: 0.9rem;
      }

      .modern-table td {
        border: none;
        padding: 14px;
        color: #333;
      }

      .modern-table tbody tr:hover {
        background: rgba(198, 40, 40, 0.05);
        transform: scale(1.01);
        transition: all 0.2s ease;
      }

      .modern-table tbody tr {
        border-bottom: 1px solid #eee;
      }

      .modern-table .badge {
        font-size: 0.85rem;
        font-weight: 600;
      }

      /* Mobile responsiveness */
      @media (max-width: 768px) {
          body {
              padding-top: 60px;
          }
          
          .hero {
              min-height: 80vh;
              text-align: center;
              justify-content: center;
              padding: 40px 20px;
          }
          
          .hero-content {
              padding: 0 15px;
          }
          
          .hero h1 {
              font-size: 2rem;
          }
          
          .hero p {
              font-size: 1rem;
          }
          
          .btn-custom {
              width: 100%;
              max-width: 300px;
              padding: 12px 30px;
              font-size: 1rem;
          }
          
          .services {
              padding: 40px 15px;
          }
          
          .services h2 {
              font-size: 1.8rem;
              margin-bottom: 30px;
          }
          
          .service-card img {
              height: 180px;
          }
          
          .service-card .card-body {
              padding: 20px;
          }
          
          .contact-section {
              padding: 40px 15px;
          }
          
          .contact-section h2 {
              font-size: 1.8rem;
          }
          
          .contact-section .details {
              flex-direction: column !important;
          }
          
          .contact-section .details > div {
              margin-bottom: 20px;
          }
          
          /* Mobile table transformation */
          .modern-table thead {
              display: none;
          }
          
          .modern-table, 
          .modern-table tbody, 
          .modern-table tr, 
          .modern-table td {
              display: block;
              width: 100%;
          }
          
          .modern-table tr {
              margin-bottom: 15px;
              border: 1px solid #ddd;
              border-radius: 8px;
              padding: 15px;
              background: white;
          }
          
          .modern-table td {
              text-align: left;
              padding: 8px 0;
              border: none;
              position: relative;
              padding-left: 50%;
          }
          
          .modern-table td::before {
              content: attr(data-label);
              position: absolute;
              left: 0;
              width: 45%;
              padding-right: 10px;
              font-weight: 700;
              color: var(--dark-gray);
          }
          
          .modal-dialog {
              margin: 10px;
          }
          
          .modal-body .row > div {
              margin-bottom: 15px;
          }
      }
      
      @media (max-width: 400px) {
          .hero h1 {
              font-size: 1.5rem;
          }
          
          .hero p {
              font-size: 0.9rem;
          }
          
          .services h2,
          .contact-section h2 {
              font-size: 1.5rem;
          }
      }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-primary-red fixed-top" style="box-shadow: var(--shadow-md); border-bottom: 0px solid var(--primary-red);">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php" style="color: var(--white); font-size: 1.8rem;">
      🔧 Papsi Paps
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: white;">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item m-1"><a class="nav-link fw-semibold" href="#home" style="color: var(--white); font-size: 1.2rem;">Home</a></li>
        <li class="nav-item m-1"><a class="nav-link fw-semibold" href="#services" style="color: var(--white); font-size: 1.2rem;">Services</a></li>
        <li class="nav-item m-1"><a class="nav-link fw-semibold" href="#contact" style="color: var(--white); font-size: 1.2rem;">Contact</a></li>
<?php if (isset($_SESSION['user_id'])): ?>
    <li class="nav-item m-1">
        <a class="nav-link fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#profileModal" style="color: var(--white); font-size: 1.2rem;">Account</a>
    </li>
<?php else: ?>
    <li class="nav-item m-1">
        <a class="nav-link fw-semibold" href="auth/login.php" style="color: var(--white); font-size: 1.2rem;">Login</a>
    </li>
<?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<?php include 'chatbot/chatbot-ui.php'; ?>

<!-- Hero Section -->
<section id="home" class="hero">
  <div class="hero-content">
      <h1>Welcome to <span class="highlight-red">Papsi Paps</span> Repair Shop</h1>
      <p><i>Driven to keep you moving — with a smile.</i></p>
      <br>
      <p>At Papsi Paps Repair Shop, your friendly neighborhood auto care partner. Whether it's a quick tune-up or a major repair, we treat every vehicle like our own — with care, precision, and attention to detail. Our mission is to make car maintenance easy, stress-free, and affordable for everyone. Because when your car is in good hands, you can drive with confidence and peace of mind.</p>
      <div class="button" style="display: flex; justify-content: center; align-items: center;">
        <a href="reservations/reservation.php" class="btn btn-custom mt-3" style="text-decoration: none;">Book Now</a>
      </div>
  </div>
</section>

<!-- Services Section -->
<section id="services" class="services">
    <div class="container">
        <h2>Our Services</h2>
        <div class="row text-center">
            <?php while ($service = mysqli_fetch_assoc($services)) { ?>
                <div class="col-md-4 mb-4">
                    <div class="service-card">
                        <?php if ($service['photo']) { ?>
                            <img src="uploads/<?php echo $service['photo']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                        <?php } else { ?>
                            <img src="images/default.jpg" class="card-img-top" alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                        <?php } ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($service['service_name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($service['description']); ?></p>
                            <p class="card-text"><strong>Duration:</strong> <?php echo $service['duration']; ?> minutes</p>
                            <p class="card-text"><strong>Price:</strong> ₱<?php echo number_format($service['price'], 2); ?></p>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="contact-section" 
  style="display: flex; justify-content: center; align-items: flex-start; padding: 40px 0;">
  
  <div class="container" style="display: flex; flex-direction: column; width: 80%; max-width: 900px;">
    
    <div class="title" style="text-align: center; margin-bottom: 30px;">
      <h2>Contact</h2>
    </div>
    
    <div class="details" 
      style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; text-align: center;">
      
      <div style="flex: 1; min-width: 200px;">
        <i class="fa-solid fa-location-dot fa-2x" style="color: var(--primary-red); margin-bottom: 10px;"></i>
        <h5>Address</h5>
        <p>
          <a href="https://maps.app.goo.gl/TvoSvmx5XCTt5j1w9" 
            target="_blank" 
            style="text-decoration: none; color: inherit;">
            0925 Purok 6, Culianin, Plaridel Bulacan
          </a>
        </p>
      </div>

      <div style="flex: 1; min-width: 200px;">
        <i class="fa-solid fa-phone fa-2x" style="color: var(--primary-red); margin-bottom: 10px;"></i>
        <h5>Phone</h5>
        <p>+63 912 345 6789</p>
      </div>
      
      <div style="flex: 1; min-width: 200px;">
        <i class="fa-brands fa-facebook-f fa-2x" style="color: var(--primary-red); margin-bottom: 10px;"></i>
        <h5>Facebook</h5>
        <p><a href="https://www.facebook.com/profile.php?id=61557368530117" style="color: #555;">Papsi Pap's Auto Repair Shop</a></p>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<div class="footer">
  <p>&copy; 2025 AutoFix Repair Shop | All Rights Reserved</p>
</div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" style="color: white;">My Profile</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="container">
          <!-- User Info -->
          <h2 class="fw-bold mb-3 text-center">Profile Information</h2>
          <hr style="
            border: none;
            height: 2px;
            background: linear-gradient(to right, transparent, #000000ff, transparent);
            margin: 25px 0;
          ">
          <h4 style="color: red;">Account Information</h4>
          <div class="row mb-4">
            <div class="col-md-4 col-12 mb-2">
              <strong>Username:</strong><br>
              <?= htmlspecialchars($user['username'] ?? 'N/A') ?>
            </div>
            <div class="col-md-4 col-12 mb-2">
              <strong>Email:</strong><br>
              <?= htmlspecialchars($user['email'] ?? 'N/A') ?>
            </div>
          </div>

          <?php if ($customer): ?>
          <h4 class="fw-bold mb-3" style="color: red;">Customer Details</h4>
          <div class="row mb-4">
            <div class="col-md-4 col-12 mb-2">
              <strong>Name:</strong><br>
              <?= htmlspecialchars($customer['name']) ?>
            </div>
            <div class="col-md-4 col-12 mb-2">
              <strong>Phone:</strong><br>
              <?= htmlspecialchars($customer['phone']) ?>
            </div>
            <div class="col-md-4 col-12 mb-2">
              <strong>Email:</strong><br>
              <?= htmlspecialchars($customer['email']) ?>
            </div>
          </div>
          <?php else: ?>
            <div class="alert alert-warning">No customer profile found yet.</div>
          <?php endif; ?>
          <hr style="
            border: none;
            height: 6px;
            background: linear-gradient(to right, transparent, #dc3545, transparent);
            margin: 25px 0;
          ">

          <!-- Reservation History -->
          <h2 class="fw-bold mb-3 text-center">My Reservations</h2>
          <hr style="
            border: none;
            height: 2px;
            background: linear-gradient(to right, transparent, #000000ff, transparent);
            margin: 25px 0;
          ">

          <?php if ($reservations && $reservations->num_rows > 0): ?>
          <div class="table-responsive mt-4">
            <table class="table table-hover align-middle modern-table shadow-sm rounded-3 overflow-hidden">
              <thead class="table-header text-white">
                <tr>
                  <th>Date</th>
                  <th>Vehicle</th>
                  <th>Services</th>
                  <th>Time</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $reservations->fetch_assoc()): ?>
                <tr>
                  <td data-label="Date"><?= date("F j, Y", strtotime($row['reservation_date'])) ?></td>
                  <td data-label="Vehicle"><?= htmlspecialchars($row['vehicle_make'] . ' ' . $row['vehicle_model'] . ' (' . $row['vehicle_year'] . ')') ?></td>
                  <td data-label="Services"><?= htmlspecialchars($row['service_list'] ?: '—') ?></td>
                  <td data-label="Time"><?= date("h:i A", strtotime($row['reservation_time'])) ?> - <?= date("h:i A", strtotime($row['end_time'])) ?></td>
                  <td data-label="Status">
                    <?php
                      $statusClass = match ($row['status']) {
                          'pending_verification' => 'warning',
                          'Approved' => 'success',
                          'Rejected' => 'danger',
                          'Completed' => 'secondary',
                          default => 'info'
                      };
                    ?>
                    <span class="badge bg-<?= $statusClass ?> px-3 py-2 rounded-pill">
                      <?= htmlspecialchars($row['status']) ?>
                    </span>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>

          <?php else: ?>
            <div class="alert alert-info">You have no reservations yet.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#changePasswordModal" data-bs-dismiss="modal">
          Change Password
        </button>
        <button class="btn btn-secondary" onclick="confirmAction('Are you sure you want to log out?', 'auth/user_logout.php')">
          Logout
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="changePasswordModalLabel" style="color: white;">Change Password</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="changePasswordForm">
          <div class="mb-3">
            <label for="current_password" class="form-label">Current Password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required>
          </div>
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
          </div>
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
          </div>
          <div id="passwordMessage" class="mt-2 text-center"></div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="savePasswordBtn">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Universal Confirmation Modal -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-3 shadow">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmActionLabel" style="color: white;">Confirm Action</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <p id="confirmMessage" class="fs-6 mb-3">Are you sure you want to proceed?</p>
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <a href="#" id="confirmActionBtn" class="btn btn-danger px-4">Confirm</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById("savePasswordBtn").addEventListener("click", function() {
  const form = document.getElementById("changePasswordForm");
  const formData = new FormData(form);
  const messageDiv = document.getElementById("passwordMessage");

  fetch("auth/change_password_ajax.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    messageDiv.innerHTML = `<div class="alert alert-${data.status === 'success' ? 'success' : 'danger'}">${data.message}</div>`;
    if (data.status === 'success') {
      form.reset();
      setTimeout(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
        modal.hide();
      }, 1500);
    }
  })
  .catch(err => {
    messageDiv.innerHTML = `<div class="alert alert-danger">Error connecting to server.</div>`;
  });
});

function confirmAction(message, link) {
  document.getElementById('confirmMessage').textContent = message;
  document.getElementById('confirmActionBtn').setAttribute('href', link);
  new bootstrap.Modal(document.getElementById('confirmActionModal')).show();
}
</script>
</body>
</html>
