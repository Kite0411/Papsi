<?php
include '../includes/config.php';
$conn = getDBConnection();

$date = $_GET['date'] ?? null;
$services = $_GET['services'] ?? '';

if (!$date || !$services) {
    echo json_encode([]);
    exit;
}

$serviceIds = array_map('intval', explode(',', $services));
$in = implode(',', array_fill(0, count($serviceIds), '?'));

// --- total duration of all selected services ---
$query = $conn->prepare("SELECT SUM(CAST(duration AS UNSIGNED)) AS total_duration FROM services WHERE id IN ($in)");
$query->bind_param(str_repeat('i', count($serviceIds)), ...$serviceIds);
$query->execute();
$total_duration = (int)$query->get_result()->fetch_assoc()['total_duration'];

// --- schedule config ---
$open = "07:00";
$close = "21:00";
$break_start = "12:30";
$break_end = "13:00";
$buffer_minutes = 30; // 30-minute gap between reservations

// --- existing reservations ---
$resQuery = $conn->prepare("SELECT reservation_time, end_time FROM reservations WHERE reservation_date = ?");
$resQuery->bind_param("s", $date);
$resQuery->execute();
$resResult = $resQuery->get_result();
$bookings = [];
while ($row = $resResult->fetch_assoc()) {
    $bookings[] = $row;
}

// --- generate slots (every 30 mins) ---
function generateSlots($start, $end, $interval = 30) {
    $slots = [];
    $current = strtotime($start);
    $endTime = strtotime($end);
    while ($current <= $endTime) {
        $slots[] = date('H:i', $current);
        $current += $interval * 60;
    }
    return $slots;
}

$allSlots = generateSlots($open, $close);
$available = [];

foreach ($allSlots as $slot) {
    $start = strtotime($slot);
    // Add buffer time to total duration
    $end = $start + (($total_duration + $buffer_minutes) * 60);

    // skip lunch
    if (
        ($start >= strtotime($break_start) && $start < strtotime($break_end)) ||
        ($end > strtotime($break_start) && $start < strtotime($break_end))
    ) continue;

    if ($end > strtotime($close)) continue;

    // check if this slot overlaps any booking (including their buffer)
    $conflict = false;
    foreach ($bookings as $b) {
        $bStart = strtotime($b['reservation_time']);
        $bEnd = strtotime($b['end_time']) + ($buffer_minutes * 60); // extend booked end time by 30 mins
        if ($start < $bEnd && $end > $bStart) {
            $conflict = true;
            break;
        }
    }

    if (!$conflict) $available[] = $slot;
}

// ✅ NEW: Hide past time slots if selected date is today
$currentDate = date('Y-m-d');
$currentTime = date('H:i');

if ($date === $currentDate) {
    $available = array_filter($available, function($time) use ($currentTime) {
        return $time > $currentTime;
    });
}

echo json_encode(array_values($available));
?>
