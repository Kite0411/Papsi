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
    
    // Handle multiple vehicles with THEIR OWN services
    $vehicle_makes = $_POST['vehicle_make'] ?? [];
    $vehicle_models = $_POST['vehicle_model'] ?? [];
    $vehicle_years = $_POST['vehicle_year'] ?? [];
    $vehicle_services = $_POST['vehicle_services'] ?? []; // New: services per vehicle
    
    $reservation_date = $_POST['reservation_date'];
    $reservation_time = $_POST['reservation_time'];

    if (
        empty($name) || empty($phone) || empty($email) || 
        empty($vehicle_makes) || empty($vehicle_models) || 
        empty($vehicle_years) || empty($reservation_date) || 
        empty($reservation_time)
    ) {
        $message = "All fields are required.";
        $messageType = 'danger';
    } elseif (!validateEmail($email)) {
        $message = "Please enter a valid email address.";
        $messageType = 'danger';
    } else {
        // Build vehicles array with THEIR services
        $vehicles = [];
        for ($i = 0; $i < count($vehicle_makes); $i++) {
            if (!empty($vehicle_makes[$i]) && !empty($vehicle_models[$i]) && !empty($vehicle_years[$i])) {
                // Get services for THIS specific vehicle
                $this_vehicle_services = isset($vehicle_services[$i]) ? $vehicle_services[$i] : [];
                
                if (empty($this_vehicle_services)) {
                    $message = "Please select at least one service for " . htmlspecialchars($vehicle_makes[$i]) . " " . htmlspecialchars($vehicle_models[$i]);
                    $messageType = 'danger';
                    break;
                }
                
                $vehicles[] = [
                    'make' => sanitizeInput($vehicle_makes[$i]),
                    'model' => sanitizeInput($vehicle_models[$i]),
                    'year' => sanitizeInput($vehicle_years[$i]),
                    'services' => $this_vehicle_services // Store services for THIS vehicle
                ];
            }
        }
        
        if (empty($vehicles) && empty($message)) {
            $message = "Please add at least one vehicle.";
            $messageType = 'danger';
        } elseif (empty($message)) {
            // Store reservation details temporarily in session
            $_SESSION['reservation_data'] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'vehicles' => $vehicles, // Now includes services per vehicle
                'reservation_date' => $reservation_date,
                'reservation_time' => $reservation_time
            ];

            // ✅ CORRECTED: Calculate total with services PER vehicle
            $total_amount = 0;
            $all_service_ids = []; // For time slot calculation
            
            foreach ($vehicles as $vehicle) {
                if (!empty($vehicle['services'])) {
                    $placeholders = implode(',', array_fill(0, count($vehicle['services']), '?'));
                    $types = str_repeat('i', count($vehicle['services']));
                    $stmt = $conn->prepare("SELECT price FROM services WHERE id IN ($placeholders)");
                    $stmt->bind_param($types, ...$vehicle['services']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $total_amount += $row['price']; // Add each vehicle's service costs
                    }
                    $stmt->close();
                    
                    // Collect all service IDs for duration calculation
                    $all_service_ids = array_merge($all_service_ids, $vehicle['services']);
                }
            }

            $_SESSION['total_amount'] = $total_amount;
            $_SESSION['all_service_ids'] = array_unique($all_service_ids); // For time slot

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
        
        .form-header h2 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 10px 0;
            color: white;
        }
        
        .form-body {
            padding: 50px 40px 40px;
        }
        
        .vehicle-group {
            background: #f8f9fa;
            position: relative;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .vehicle-group h6 {
            color: var(--primary-red);
            font-weight: 700;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--primary-red);
            padding-bottom: 8px;
        }

        .vehicle-group .btn-remove-vehicle {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            font-size: 0.85rem;
        }
        
        .service-card {
            border: 2px solid #e0e0e0;
            border-radius: var(--radius-md);
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: var(--transition-normal);
            background: white;
        }
        
        .service-card:hover {
            border-color: var(--primary-red);
            box-shadow: var(--shadow-md);
        }
        
        .service-card.selected {
            border-color: var(--primary-red);
            background: #FFEBEE;
            box-shadow: var(--shadow-md);
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
        
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: var(--radius-md);
            padding: 15px 50px;
            font-weight: 700;
            transition: var(--transition-normal);
            font-size: 1.1rem;
            width: 100%;
            min-height: 44px;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="reservation-form">
                    <div class="form-header text-center position-relative">
                        <a href="../index.php" 
                           class="btn btn-outline-danger btn-sm position-absolute top-0 start-0 m-3" 
                           style="color: white; border-color: white;">
                            <i class="fa-solid fa-arrow-left"></i>
                        </a>
                        <h2 class="mt-3">🚗 Book Your Auto Service</h2>
                        <p>Each vehicle can have different services</p>
                    </div>

                    <div class="form-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" id="reservationForm">
                            <!-- Personal Info -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 style="color: var(--primary-red); border-bottom: 2px solid var(--primary-red); padding-bottom: 10px;">Personal Information</h5>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" 
                                        value="<?php echo htmlspecialchars($customer['name'] ?? ($_POST['name'] ?? '')); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                        value="<?php echo htmlspecialchars($customer['phone'] ?? ($_POST['phone'] ?? '')); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                        value="<?php echo htmlspecialchars($customer['email'] ?? ($_POST['email'] ?? '')); ?>" required>
                                </div>
                            </div>

                            <!-- Vehicles Section -->
                            <h5 style="color: var(--primary-red); border-bottom: 2px solid var(--primary-red); padding-bottom: 10px;">Vehicles & Services</h5>
                            <div id="vehiclesContainer">
                                <!-- First vehicle -->
                                <div class="vehicle-group" data-vehicle-index="0">
                                    <h6>🚗 Vehicle #1</h6>
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Make</label>
                                            <input type="text" name="vehicle_make[]" class="form-control" placeholder="e.g., Toyota" required>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Model</label>
                                            <input type="text" name="vehicle_model[]" class="form-control" placeholder="e.g., Camry" required>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Year</label>
                                            <input type="text" name="vehicle_year[]" class="form-control" placeholder="e.g., 2020" required>
                                        </div>
                                    </div>
                                    
                                    <label class="form-label mt-2"><strong>Select Services for This Vehicle:</strong></label>
                                    <div class="services-for-vehicle" data-vehicle="0">
                                        <?php 
                                        mysqli_data_seek($services, 0);
                                        while ($service = mysqli_fetch_assoc($services)) { ?>
                                            <div class="service-card" onclick="toggleVehicleService(0, <?php echo $service['id']; ?>)">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="vehicle_services[0][]" 
                                                           value="<?php echo $service['id']; ?>" 
                                                           id="vehicle_0_service_<?php echo $service['id']; ?>">
                                                    <label class="form-check-label">
                                                        <strong><?php echo htmlspecialchars($service['service_name']); ?></strong><br>
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
                            </div>
                            
                            <button type="button" class="btn btn-outline-primary mb-4" onclick="addVehicle()">
                                <i class="fa-solid fa-plus"></i> Add Another Vehicle
                            </button>

                            <!-- Date & Time -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Date</label>
                                    <input type="text" id="reservation_date" name="reservation_date" 
                                           class="form-control" placeholder="Select date" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Time</label>
                                    <select name="reservation_time" id="reservation_time" class="form-control" required>
                                        <option value="">Select time</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Book Appointment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let vehicleCount = 1;
        const servicesData = <?php 
            mysqli_data_seek($services, 0);
            $servicesArray = [];
            while ($s = mysqli_fetch_assoc($services)) {
                $servicesArray[] = $s;
            }
            echo json_encode($servicesArray);
        ?>;

        function addVehicle() {
            vehicleCount++;
            const container = document.getElementById('vehiclesContainer');
            const newVehicle = document.createElement('div');
            newVehicle.className = 'vehicle-group';
            newVehicle.setAttribute('data-vehicle-index', vehicleCount - 1);
            
            let servicesHTML = '';
            servicesData.forEach(service => {
                servicesHTML += `
                    <div class="service-card" onclick="toggleVehicleService(${vehicleCount - 1}, ${service.id})">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="vehicle_services[${vehicleCount - 1}][]" 
                                   value="${service.id}" 
                                   id="vehicle_${vehicleCount - 1}_service_${service.id}">
                            <label class="form-check-label">
                                <strong>${service.service_name}</strong><br>
                                <small class="text-muted">
                                    Duration: ${service.duration} | Price: ₱${parseFloat(service.price).toFixed(2)}
                                </small>
                            </label>
                        </div>
                    </div>
                `;
            });
            
            newVehicle.innerHTML = `
                <h6>🚗 Vehicle #${vehicleCount}</h6>
                <button type="button" class="btn btn-danger btn-sm btn-remove-vehicle" onclick="removeVehicle(this)">
                    <i class="fa-solid fa-trash"></i> Remove
                </button>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Make</label>
                        <input type="text" name="vehicle_make[]" class="form-control" placeholder="e.g., Honda" required>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Model</label>
                        <input type="text" name="vehicle_model[]" class="form-control" placeholder="e.g., Civic" required>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Year</label>
                        <input type="text" name="vehicle_year[]" class="form-control" placeholder="e.g., 2021" required>
                    </div>
                </div>
                <label class="form-label mt-2"><strong>Select Services for This Vehicle:</strong></label>
                <div class="services-for-vehicle" data-vehicle="${vehicleCount - 1}">
                    ${servicesHTML}
                </div>
            `;
            container.appendChild(newVehicle);
        }

        function removeVehicle(button) {
            const vehicleGroup = button.closest('.vehicle-group');
            vehicleGroup.remove();
            
            // Renumber vehicles
            const vehicles = document.querySelectorAll('.vehicle-group');
            vehicles.forEach((vehicle, index) => {
                vehicle.querySelector('h6').textContent = `🚗 Vehicle #${index + 1}`;
            });
            vehicleCount = vehicles.length;
        }

        function toggleVehicleService(vehicleIndex, serviceId) {
            const checkbox = document.getElementById(`vehicle_${vehicleIndex}_service_${serviceId}`);
            const card = checkbox.closest('.service-card');
            
            checkbox.checked = !checkbox.checked;
            card.classList.toggle('selected', checkbox.checked);
        }
        
        flatpickr("#reservation_date", {
            dateFormat: "Y-m-d",
            minDate: "today",
            disable: [function(date) { return date.getDay() === 0; }]
        });

        function getSelectedServiceIds() {
            const ids = new Set();
            document.querySelectorAll('input[type="checkbox"][name^="vehicle_services"]:checked').forEach(cb => {
                ids.add(cb.value);
            });
            return Array.from(ids);
        }

        async function loadAvailableTimes() {
            const date = document.getElementById('reservation_date').value;
            const serviceIds = getSelectedServiceIds();
            const timeSelect = document.getElementById('reservation_time');
            
            if (!date || serviceIds.length === 0) {
                timeSelect.innerHTML = '<option value="">Select date & services first</option>';
                return;
            }

            timeSelect.innerHTML = '<option>Loading...</option>';
            const res = await fetch(`get_available_times.php?date=${date}&services=${serviceIds.join(',')}`);
            const times = await res.json();

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
        document.addEventListener('change', function(e) {
            if (e.target.matches('input[type="checkbox"][name^="vehicle_services"]')) {
                loadAvailableTimes();
            }
        });
    </script>
</body>
</html>
