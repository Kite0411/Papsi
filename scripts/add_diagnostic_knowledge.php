<?php
include '../includes/config.php';
$conn = getDBConnection();

// Vehicle diagnostic knowledge base
$diagnosticKnowledge = [
    // Engine Problems
    ['trigger' => 'engine noise', 'response' => 'Engine noise could indicate several issues. I recommend our **Engine Diagnostics** service ($200) to identify the exact problem. This could be anything from a simple tune-up to serious engine issues.'],
    ['trigger' => 'engine knocking', 'response' => 'Engine knocking is serious! This could be low oil, worn bearings, or timing issues. I recommend immediate **Engine Diagnostics** ($200) to prevent further damage.'],
    ['trigger' => 'engine overheating', 'response' => 'Engine overheating is dangerous! I recommend immediate **Engine Diagnostics** ($200) to check for coolant leaks, thermostat issues, or water pump problems. Don\'t drive until this is checked.'],
    ['trigger' => 'engine won\'t start', 'response' => 'Starting problems could be battery, alternator, fuel system, or electrical issues. I recommend our **Electrical Diagnostics** ($150) to identify the exact problem.'],
    
    // Brake Problems
    ['trigger' => 'brake squeaking', 'response' => 'Brake squeaking usually means worn brake pads. I recommend our **Brake Service** starting at $500. This includes pad replacement and brake system inspection.'],
    ['trigger' => 'brake noise', 'response' => 'Brake noise indicates worn components. I recommend our **Brake Service** starting at $500 to inspect and replace worn brake pads or rotors.'],
    ['trigger' => 'soft brakes', 'response' => 'Soft brakes could indicate air in brake lines or brake fluid issues. I recommend our **Brake Service** with fluid check and bleeding ($600).'],
    ['trigger' => 'brake pedal spongy', 'response' => 'Spongy brake pedal usually means air in brake lines. I recommend our **Brake Service** with brake bleeding ($600) to restore proper brake feel.'],
    
    // Air Conditioning
    ['trigger' => 'ac not cooling', 'response' => 'Your AC not cooling could be low refrigerant, compressor issues, or electrical problems. I recommend our **Air Conditioning Service** ($500) which includes diagnosis, cleaning, and recharging.'],
    ['trigger' => 'air conditioning warm', 'response' => 'Warm AC air indicates system problems. I recommend our **Air Conditioning Service** ($500) to diagnose and fix the issue.'],
    ['trigger' => 'ac blowing hot air', 'response' => 'AC blowing hot air needs immediate attention. I recommend our **Air Conditioning Service** ($500) to check refrigerant levels and system components.'],
    
    // Oil and Maintenance
    ['trigger' => 'oil change', 'response' => 'Perfect! Our **Oil Change Service** costs $800 and takes about 1 hour. This includes new oil, filter, and a basic inspection. Would you like to book an appointment?'],
    ['trigger' => 'oil leak', 'response' => 'Oil leaks need immediate attention. I recommend our **Engine Diagnostics** ($200) to identify the leak source, then appropriate repairs. This could be gaskets, seals, or other engine components.'],
    ['trigger' => 'low oil', 'response' => 'Low oil levels can cause serious engine damage. I recommend our **Oil Change Service** ($800) and **Engine Diagnostics** ($200) to check for leaks or consumption issues.'],
    
    // Electrical Problems
    ['trigger' => 'car won\'t start', 'response' => 'Starting problems could be battery, alternator, or electrical system issues. I recommend our **Electrical Diagnostics** ($150) to identify the exact problem.'],
    ['trigger' => 'battery dead', 'response' => 'Dead battery could be due to age, charging system issues, or electrical drains. I recommend our **Electrical Diagnostics** ($150) to check the battery and charging system.'],
    ['trigger' => 'electrical problems', 'response' => 'Electrical issues can be complex. I recommend our **Electrical Diagnostics** ($150) to identify the problem, then appropriate repairs.'],
    
    // Transmission
    ['trigger' => 'transmission problems', 'response' => 'Transmission issues are complex and expensive to fix. I recommend our **Transmission Diagnostics** ($250) to identify the problem, then we can recommend the right repair service.'],
    ['trigger' => 'gear shifting problems', 'response' => 'Shifting problems could be transmission fluid, clutch, or transmission issues. I recommend our **Transmission Diagnostics** ($250) to identify the exact problem.'],
    ['trigger' => 'transmission noise', 'response' => 'Transmission noise indicates internal problems. I recommend our **Transmission Diagnostics** ($250) to assess the damage and recommend repairs.'],
    
    // Suspension
    ['trigger' => 'rough ride', 'response' => 'Rough ride could be worn suspension components. I recommend our **Suspension Inspection** ($100) to identify worn shocks, struts, or other components.'],
    ['trigger' => 'car bouncing', 'response' => 'Excessive bouncing indicates worn suspension. I recommend our **Suspension Inspection** ($100) to check shocks, struts, and other suspension components.'],
    ['trigger' => 'suspension problems', 'response' => 'Suspension problems affect ride quality and safety. I recommend our **Suspension Inspection** ($100) to identify worn components, then appropriate repairs.'],
    
    // General Diagnostic
    ['trigger' => 'check engine light', 'response' => 'Check engine light indicates various engine problems. I recommend our **Engine Diagnostics** ($200) to read the error codes and identify the specific issue.'],
    ['trigger' => 'strange noise', 'response' => 'Strange noises can indicate various problems. I recommend our **General Diagnostics** ($150) to identify the source and recommend the right service.'],
    ['trigger' => 'car problem', 'response' => 'I can help diagnose your car problem! Please describe the specific symptoms you\'re experiencing, or I can recommend our **General Diagnostics** ($150) to identify the issue.'],
    
    // Service Information
    ['trigger' => 'what services do you offer', 'response' => 'We offer comprehensive auto repair services: Oil Change ($800), Brake Service ($500+), Air Conditioning ($500), Tune Up ($1000), Engine Diagnostics ($200), Transmission Service, Electrical Repair, and Suspension Work. What specific problem are you experiencing?'],
    ['trigger' => 'service prices', 'response' => 'Our service prices: Oil Change ($800), Brake Service ($500+), AC Service ($500), Tune Up ($1000), Engine Diagnostics ($200), Electrical Diagnostics ($150), Transmission Diagnostics ($250), Suspension Inspection ($100). What service do you need?'],
    ['trigger' => 'book appointment', 'response' => 'You can book an appointment by calling us at +63 912 345 6789 or visiting our reservation page. What service would you like to schedule?'],
    
    // Business Information
    ['trigger' => 'business hours', 'response' => 'We\'re open Monday to Saturday, 7 AM to 7 PM. Closed on Sundays. You can book appointments during these hours.'],
    ['trigger' => 'location', 'response' => 'We\'re located at 123 Auto Street, Manila, Philippines. Easy to find and with plenty of parking!'],
    ['trigger' => 'phone number', 'response' => 'You can reach us at +63 912 345 6789. We\'re available during business hours to help with your vehicle problems.']
];

echo "<h2>Adding Vehicle Diagnostic Knowledge...</h2>";

$added = 0;
$updated = 0;

foreach ($diagnosticKnowledge as $knowledge) {
    $trigger = $knowledge['trigger'];
    $response = $knowledge['response'];
    
    // Check if trigger already exists
    $checkStmt = $conn->prepare("SELECT id FROM chat_knowledge WHERE trigger1 = ? OR trigger = ?");
    $checkStmt->bind_param("ss", $trigger, $trigger);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing entry
        $stmt = $conn->prepare("UPDATE chat_knowledge SET response = ? WHERE trigger1 = ? OR trigger = ?");
        $stmt->bind_param("sss", $response, $trigger, $trigger);
        $stmt->execute();
        $updated++;
        echo "✅ Updated: '$trigger'<br>";
    } else {
        // Insert new knowledge
        $stmt = $conn->prepare("INSERT INTO chat_knowledge (trigger1, response) VALUES (?, ?)");
        $stmt->bind_param("ss", $trigger, $response);
        $stmt->execute();
        $added++;
        echo "➕ Added: '$trigger'<br>";
    }
    
    $stmt->close();
    $checkStmt->close();
}

echo "<br><h3>Summary:</h3>";
echo "✅ Added: $added new diagnostic entries<br>";
echo "🔄 Updated: $updated existing entries<br>";
echo "🎯 Total diagnostic knowledge: " . ($added + $updated) . " entries<br>";

echo "<br><h3>Your chatbot is now trained for vehicle diagnostics!</h3>";
echo "<p>The chatbot can now:</p>";
echo "<ul>";
echo "<li>🔧 Analyze vehicle problems</li>";
echo "<li>💡 Recommend appropriate services</li>";
echo "<li>💰 Provide pricing information</li>";
echo "<li>📞 Guide users to book appointments</li>";
echo "<li>🏢 Share business information</li>";
echo "</ul>";

echo "<br><p><strong>Test your diagnostic chatbot now!</strong></p>";
?> 