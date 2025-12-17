<?php
include '../includes/config.php';
session_name("customer_session");
session_start();
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch existing customer info (if any)
$customer = null;
$stmt = $conn->prepare("SELECT * FROM customers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $customer = $result->fetch_assoc();
}
$stmt->close();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    
    // Handle multiple vehicles
    $vehicle_makes = $_POST['vehicle_make'] ?? [];
    $vehicle_models = $_POST['vehicle_model'] ?? [];
    $vehicle_years = $_POST['vehicle_year'] ?? [];
    
    $reservation_date = $_POST['reservation_date'];
    $reservation_time = $_POST['reservation_time'];
    $selected_services = $_POST['services'] ?? [];

    if (
        empty($name) || empty($phone) || empty($email) || 
        empty($vehicle_makes) || empty($vehicle_models) || 
        empty($vehicle_years) || empty($reservation_date) || 
        empty($reservation_time) || empty($selected_services)
    ) {
        $message = "All fields are required.";
        $messageType = 'danger';
    } elseif (!validateEmail($email)) {
        $message = "Please enter a valid email address.";
        $messageType = 'danger';
    } else {
        // Build vehicles array
        $vehicles = [];
        for ($i = 0; $i < count($vehicle_makes); $i++) {
            if (!empty($vehicle_makes[$i]) && !empty($vehicle_models[$i]) && !empty($vehicle_years[$i])) {
                $vehicles[] = [
                    'make' => sanitizeInput($vehicle_makes[$i]),
                    'model' => sanitizeInput($vehicle_models[$i]),
                    'year' => sanitizeInput($vehicle_years[$i])
                ];
            }
        }
        
        if (empty($vehicles)) {
            $message = "Please add at least one vehicle.";
            $messageType = 'danger';
        } else {
            // Store reservation details temporarily in session
            $_SESSION['reservation_data'] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'vehicles' => $vehicles, // Store all vehicles
                'reservation_date' => $reservation_date,
                'reservation_time' => $reservation_time
            ];

            // Store selected service IDs
            $_SESSION['selected_services'] = $selected_services;

            // Compute total amount for display
            $total_amount = 0;
            if (!empty($selected_services)) {
                $placeholders = implode(',', array_fill(0, count($selected_services), '?'));
                $types = str_repeat('i', count($selected_services));
                $stmt = $conn->prepare("SELECT price FROM services WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$selected_services);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $total_amount += $row['price'];
                }
                $stmt->close();
            }

            $_SESSION['total_amount'] = $total_amount;

            // Redirect to payment page
            header("Location: payment.php");
            exit();
        }
    }
}

// Fetch all services for display
$services = mysqli_query($conn, "SELECT * FROM services");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Reservation - AutoFix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body {
            background: url('../bg/rbg.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 30px 0;
            color: #222;
            min-height: 100vh;
            margin: 0;
        }

        /* Overlay for better text contrast */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: -1;
        }
        
        .reservation-form {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid rgba(184, 97, 97, 0.86);
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
            font-size: 16px; /* Prevents iOS zoom */
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

        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            transition: all 0.2s ease-in-out;
            min-height: 44px;
        }

        .btn:hover {
            transform: scale(1.03);
        }

        .btn-outline-danger {
            min-height: 40px;
            min-width: 40px;
        }

        .vehicle-group {
            background: #f8f9fa;
            position: relative;
        }

        .vehicle-group .btn-remove-vehicle {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            font-size: 0.85rem;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            body {
                padding: 15px 0;
            }
            
            .container {
                padding: 0 10px;
            }
            
            .form-header {
                padding: 30px 20px;
            }
            
            .form-header h2 {
                font-size: 1.5rem;
                margin-top: 10px;
            }
            
            .form-header p {
                font-size: 0.95rem;
            }
            
            .form-header::after {
                display: none;
            }
            
            .form-body {
                padding: 30px 20px 25px;
            }
            
            h5 {
                font-size: 1.1rem;
            }
            
            .btn-primary {
                padding: 12px 30px;
                font-size: 1rem;
            }
            
            .service-card {
                padding: 12px;
            }
            
            .service-card strong {
                font-size: 1rem;
            }
            
            .btn-outline-danger {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 400px) {
            .form-header h2 {
                font-size: 1.3rem;
            }
            
            .form-header p {
                font-size: 0.85rem;
            }
            
            h5 {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <div class="reservation-form">
                    
                    <div class="form-header text-center position-relative">
                        <a href="../index.php" 
                           class="btn btn-outline-danger btn-sm position-absolute top-0 start-0 m-3" 
                           style="color: white; border-color: white;">
                            <i class="fa-solid fa-arrow-left"></i>
                        </a>

                        <h2 class="mt-3">🚗 Book Your Auto Service</h2>
                        <p>Schedule your appointment with AutoFix — Professional service guaranteed</p>
                    </div>

                    
                    <div class="form-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" id="reservationForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-3">Personal Information</h5>

                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" name="name" class="form-control" 
                                            value="<?php 
                                                echo htmlspecialchars($customer['name'] ?? ($_POST['name'] ?? '')); 
                                            ?>" 
                                            required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" 
                                            value="<?php 
                                                echo htmlspecialchars($customer['phone'] ?? ($_POST['phone'] ?? '')); 
                                            ?>" 
                                            required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" 
                                            value="<?php 
                                                echo htmlspecialchars($customer['email'] ?? ($_POST['email'] ?? '')); 
                                            ?>" 
                                            required>
                                    </div>
                                </div>

                                
                                <div class="col-md-6">
                                    <h5 class="mb-3">Vehicle Information</h5>
                                    <div id="vehiclesContainer">
                                        <div class="vehicle-group mb-3 p-3 border rounded">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong>Vehicle #1</strong>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">Vehicle Make</label>
                                                <input type="text" name="vehicle_make[]" class="form-control" placeholder="e.g., Toyota" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">Vehicle Model</label>
                                                <input type="text" name="vehicle_model[]" class="form-control" placeholder="e.g., Camry" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">Vehicle Year</label>
                                                <input type="text" name="vehicle_year[]" class="form-control" placeholder="e.g., 2020" required>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="addVehicle()">
                                        <i class="fa-solid fa-plus"></i> Add Another Vehicle
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h5 class="mb-3">Appointment Details</h5>
                                    <div class="mb-3">
                                        <label class="form-label">Preferred Date</label>
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
                                        <label class="form-label">Preferred Time</label>
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
                                    <?php while ($service = mysqli_fetch_assoc($services)) { ?>
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
                                <button type="button" class="btn btn-primary btn-lg"
                                        onclick="confirmAction('Do you want to book this appointment?', 'form', document.getElementById('reservationForm'))">
                                    Book Appointment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Universal Confirmation Modal -->
    <div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-3 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmActionLabel" style="color: #FFF;">Confirm Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p id="confirmMessage" class="fs-6 mb-3">Are you sure you want to proceed?</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="confirmFormSubmit" class="btn btn-danger px-4">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let vehicleCount = 1;

        function addVehicle() {
            vehicleCount++;
            const container = document.getElementById('vehiclesContainer');
            const newVehicle = document.createElement('div');
            newVehicle.className = 'vehicle-group mb-3 p-3 border rounded';
            newVehicle.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Vehicle #${vehicleCount}</strong>
                    <button type="button" class="btn btn-danger btn-sm btn-remove-vehicle" onclick="removeVehicle(this)">
                        <i class="fa-solid fa-trash"></i> Remove
                    </button>
                </div>
                <div class="mb-2">
                    <label class="form-label">Vehicle Make</label>
                    <input type="text" name="vehicle_make[]" class="form-control" placeholder="e.g., Honda" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Vehicle Model</label>
                    <input type="text" name="vehicle_model[]" class="form-control" placeholder="e.g., Civic" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Vehicle Year</label>
                    <input type="text" name="vehicle_year[]" class="form-control" placeholder="e.g., 2021" required>
                </div>
            `;
            container.appendChild(newVehicle);
        }

        function removeVehicle(button) {
            const vehicleGroup = button.closest('.vehicle-group');
            vehicleGroup.remove();
            
            // Renumber remaining vehicles
            const vehicles = document.querySelectorAll('.vehicle-group');
            vehicles.forEach((vehicle, index) => {
                vehicle.querySelector('strong').textContent = `Vehicle #${index + 1}`;
            });
            vehicleCount = vehicles.length;
        }

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

            const res = await fetch(`get_available_times.php?date=${date}&services=${serviceIds.join(',')}`);
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

        function confirmAction(message, actionType = 'link', target = null) {
            const modal = new bootstrap.Modal(document.getElementById('confirmActionModal'));
            document.getElementById('confirmMessage').textContent = message;

            const confirmBtn = document.getElementById('confirmFormSubmit');
            confirmBtn.onclick = function() {
                if (actionType === 'form' && target) {
                    target.submit();
                } else if (actionType === 'link' && target) {
                    window.location.href = target;
                }
                modal.hide();
            };

            modal.show();
        }
    </script>
</body>
</html>
