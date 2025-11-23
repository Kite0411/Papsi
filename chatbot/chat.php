<?php
// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // Start output buffering to catch any stray output

header('Content-Type: application/json');
include '../includes/config.php';

// Ensure we have a database connection
try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['reply' => 'Sorry, I\'m having technical difficulties. Please try again later.', 'type' => 'error']);
    ob_end_flush();
    exit;
}

// Get the JSON input from JS
$data = json_decode(file_get_contents('php://input'), true);
$message = sanitizeInput($data['message'] ?? '');

// Check if message is empty
if (!$message) {
    ob_clean();
    echo json_encode(['reply' => 'Please describe your vehicle problem so I can help you.', 'type' => 'error']);
    ob_end_flush();
    exit;
}

// First, check if this is a service-related query
$userMessageLower = strtolower($message);
$serviceKeywords = ['services', 'service', 'what do you offer', 'what are your services', 'services available', 'what services are available', 'list of services', 'show me your services', 'available services', 'prices', 'price', 'cost', 'how much'];

$isServiceQuery = false;
foreach ($serviceKeywords as $keyword) {
    if (strpos($userMessageLower, $keyword) !== false) {
        $isServiceQuery = true;
        break;
    }
}

// If it's a service query, get services from database
if ($isServiceQuery) {
    $servicesList = getServicesAsBullets($conn);
    if (!empty($servicesList)) {
        $botReply = "Here are our current services:\n\n$servicesList\n\nWhat specific problem are you experiencing with your vehicle?";
    } else {
        $botReply = "I'm sorry, I couldn't retrieve our services at the moment. Please call us at +63 912 345 6789 for current service information.";
    }
} else {
    // Try to find a response from our database knowledge base
    $botReply = "";
    $bestMatchId = null;
    $smallestDistance = PHP_INT_MAX;

    // Fetch all triggers from DB
    $triggers = [];
    $res = $conn->query("SELECT * FROM chat_knowledge");
    while ($row = $res->fetch_assoc()) {
        $triggers[] = $row;
    }

    // Find closest match using Levenshtein
    foreach ($triggers as $trigger) {
        $triggerText = isset($trigger['trigger']) ? $trigger['trigger'] : $trigger['trigger1'];
        $distance = levenshtein($userMessageLower, strtolower($triggerText));
        if ($distance < $smallestDistance) {
            $smallestDistance = $distance;
            $botReply = $trigger['response'];
            $bestMatchId = $trigger['id'];
        }
    }
}

// If no good match found in database and it's not a service query, use AI for vehicle diagnostics
if (!$isServiceQuery && $smallestDistance > CHATBOT_SIMILARITY_THRESHOLD) {
    // Use configuration for API token
    $apiToken = HUGGING_FACE_TOKEN;
    
    // Get services from database
    $servicesList = getServicesFromDatabase($conn);
    
    // Enhanced vehicle diagnostic context
    $context = "You are an expert auto repair diagnostic assistant. Your job is to:
1. Analyze the user's vehicle problem description
2. Identify the most likely issues
3. Recommend specific auto repair services that would solve their problem
4. Provide helpful, professional advice

Available services: $servicesList

Keep responses friendly, professional, and focused on recommending the right service for their specific problem.";

    $fullPrompt = $context . "\n\nUser Problem: " . $message . "\n\nDiagnostic Analysis and Service Recommendation:";

    // cURL setup for Hugging Face API
    $ch = curl_init(AI_MODEL_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, AI_TIMEOUT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiToken",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['inputs' => $fullPrompt]));

    // Execute request
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $httpCode !== 200) {
        // Intelligent fallback based on common vehicle problems
        $botReply = getDiagnosticFallback($userMessageLower);
        
        // Log the error for debugging
        if (DEBUG_MODE) {
            logActivity('chatbot_error', "API Error: $err, HTTP Code: $httpCode");
        }
    } else {
        $resData = json_decode($response, true);
        
        if (is_array($resData) && isset($resData[0]['generated_text'])) {
            $generatedText = $resData[0]['generated_text'];
            // Extract only the assistant's response part
            $parts = explode("Diagnostic Analysis and Service Recommendation:", $generatedText);
            if (count($parts) > 1) {
                $botReply = trim($parts[1]);
            } else {
                $botReply = trim($generatedText);
            }
            // Limit response length
            if (strlen($botReply) > CHATBOT_MAX_RESPONSE_LENGTH) {
                $botReply = substr($botReply, 0, CHATBOT_MAX_RESPONSE_LENGTH) . "...";
            }
        } else {
            $botReply = getDiagnosticFallback($userMessageLower);
        }
    }
}

// Save conversation history
try {
    $stmt = $conn->prepare("INSERT INTO chat_history (user_message, bot_reply) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ss", $message, $botReply);
        $stmt->execute();
        $stmt->close();
    }
    
    // Log the conversation
    @logActivity('chatbot_conversation', "User Problem: $message | Bot Recommendation: $botReply");
    
    // Log detailed chatbot interaction in audit trail
    @logAuditTrail(
        'CHATBOT_INTERACTION',
        'chat_history',
        null,
        null,
        [
            'user_message' => $message,
            'bot_reply' => $botReply,
            'is_service_query' => $isServiceQuery,
            'similarity_distance' => $smallestDistance ?? 'N/A',
            'api_used' => !$isServiceQuery && ($smallestDistance ?? 0) > CHATBOT_SIMILARITY_THRESHOLD
        ],
        "Chatbot interaction: User asked about vehicle problem"
    );
} catch (Exception $e) {
    // Silently fail logging - don't break the response
}

// Clear any buffered output (errors, warnings, etc.) and send clean JSON
ob_clean();

// Send JSON reply back to JS
echo json_encode([
    'reply' => $botReply,
    'type' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'bot_name' => CHATBOT_NAME
]);

// Flush the buffer
ob_end_flush();

// Function to get services from database
function getServicesFromDatabase($conn) {
    $services = [];
    $result = $conn->query("SELECT service_name, price, duration FROM services ORDER BY service_name");
    
    while ($row = $result->fetch_assoc()) {
        $services[] = $row['service_name'] . ' ($' . number_format($row['price'], 0) . ')';
    }
    
    return implode(', ', $services);
}

// Function to get services as bulleted list
function getServicesAsBullets($conn) {
    $services = [];
    $result = $conn->query("SELECT service_name, price, duration FROM services ORDER BY service_name");
    
    while ($row = $result->fetch_assoc()) {
        $services[] = "• " . $row['service_name'] . " - $" . number_format($row['price'], 0) . " (" . $row['duration'] . ")";
    }
    
    return implode("\n", $services);
}

// Function to provide intelligent diagnostic fallbacks
function getDiagnosticFallback($userMessage) {
    global $conn;
    $message = strtolower($userMessage);
    
    // Get services from database for dynamic responses
    $services = [];
    $result = $conn->query("SELECT service_name, price, duration FROM services ORDER BY service_name");
    while ($row = $result->fetch_assoc()) {
        $services[strtolower($row['service_name'])] = [
            'name' => $row['service_name'],
            'price' => $row['price'],
            'duration' => $row['duration']
        ];
    }
    
    // Enhanced problem detection with broader keywords and fuzzy matching
    $problemKeywords = [
        'engine' => ['engine', 'motor', 'motor', 'power', 'performance', 'acceleration', 'rpm', 'rev', 'revving', 'idle', 'idling', 'stall', 'stalling', 'rough', 'jerky', 'hesitation', 'misfire', 'backfire', 'smoke', 'exhaust', 'emission', 'check engine', 'cel', 'light'],
        'brake' => ['brake', 'braking', 'stop', 'stopping', 'pedal', 'squeak', 'squeal', 'grind', 'grinding', 'soft', 'spongy', 'hard', 'stiff', 'vibration', 'pulsing', 'pulse', 'abs', 'anti-lock'],
        'air_conditioning' => ['ac', 'air', 'aircon', 'air conditioning', 'cool', 'cooling', 'cold', 'hot', 'warm', 'temperature', 'climate', 'vent', 'ventilation', 'blower', 'fan', 'refrigerant', 'freon', 'condenser', 'compressor', 'evaporator', 'heater', 'heating', 'defrost', 'defroster', 'getting', 'not getting', 'is not getting', 'doesn\'t get', 'does not get', 'isn\'t getting'],
        'oil' => ['oil', 'lubricant', 'lubrication', 'filter', 'dirty', 'black', 'leak', 'leaking', 'burn', 'burning', 'consumption', 'level', 'pressure', 'light', 'change', 'maintenance', 'service'],
        'electrical' => ['electrical', 'electric', 'battery', 'dead', 'start', 'starting', 'crank', 'cranking', 'alternator', 'charging', 'voltage', 'amp', 'current', 'fuse', 'fuses', 'relay', 'relays', 'wiring', 'wire', 'spark', 'spark plug', 'ignition', 'key', 'remote', 'alarm', 'horn', 'light', 'lights', 'bulb', 'bulbs', 'radio', 'stereo', 'speaker', 'speakers'],
        'transmission' => ['transmission', 'trans', 'gear', 'gears', 'shift', 'shifting', 'clutch', 'automatic', 'manual', 'neutral', 'reverse', 'drive', 'park', 'slip', 'slipping', 'jerk', 'jerky', 'bump', 'bumping', 'noise', 'whine', 'whining', 'fluid', 'leak'],
        'suspension' => ['suspension', 'shock', 'shocks', 'strut', 'struts', 'spring', 'springs', 'bounce', 'bouncing', 'bumpy', 'rough', 'ride', 'handling', 'steering', 'wheel', 'wheels', 'tire', 'tires', 'alignment', 'balance', 'vibration', 'shake', 'shaking', 'noise', 'clunk', 'clunking', 'rattle', 'rattling']
    ];
    
    // Check for problem matches with fuzzy logic
    $matchedProblems = [];
    foreach ($problemKeywords as $problemType => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $matchedProblems[] = $problemType;
                break;
            }
        }
    }
    
    // If we found specific problems, provide targeted recommendations
    if (!empty($matchedProblems)) {
        $recommendations = [];
        
        foreach ($matchedProblems as $problem) {
            switch ($problem) {
                case 'engine':
                    if (strpos($message, 'noise') !== false || strpos($message, 'knock') !== false || strpos($message, 'rattle') !== false) {
                        $recommendations[] = "**Engine Diagnostics** - to identify the source of the noise";
                    } elseif (strpos($message, 'overheat') !== false || strpos($message, 'hot') !== false || strpos($message, 'temperature') !== false) {
                        $recommendations[] = "**Engine Diagnostics** - to check cooling system issues";
                    } else {
                        $recommendations[] = "**Engine Diagnostics** - to identify the exact problem";
                    }
                    break;
                    
                case 'brake':
                    if (strpos($message, 'squeak') !== false || strpos($message, 'squeal') !== false || strpos($message, 'noise') !== false) {
                        $recommendations[] = "**Brake Service** - likely worn brake pads need replacement";
                    } elseif (strpos($message, 'soft') !== false || strpos($message, 'spongy') !== false) {
                        $recommendations[] = "**Brake Service** - possible air in brake lines or fluid issues";
                    } else {
                        $recommendations[] = "**Brake Service** - to inspect and repair brake system";
                    }
                    break;
                    
                case 'air_conditioning':
                    if (strpos($message, 'warm') !== false || strpos($message, 'hot') !== false || strpos($message, 'not cool') !== false) {
                        $recommendations[] = "**Air Conditioning Service** - to diagnose and fix cooling issues";
                    } else {
                        $recommendations[] = "**Air Conditioning Service** - to check and maintain AC system";
                    }
                    break;
                    
                case 'oil':
                    if (strpos($message, 'change') !== false) {
                        $oilService = isset($services['change oil']) ? $services['change oil'] : null;
                        if ($oilService) {
                            $recommendations[] = "**{$oilService['name']}** - $" . number_format($oilService['price'], 0) . " (" . $oilService['duration'] . ")";
                        } else {
                            $recommendations[] = "**Oil Change Service** - includes new oil, filter, and inspection";
                        }
                    } elseif (strpos($message, 'leak') !== false) {
                        $recommendations[] = "**Engine Diagnostics** - to identify oil leak source";
                    } else {
                        $recommendations[] = "**Oil Change Service** - for maintenance and inspection";
                    }
                    break;
                    
                case 'electrical':
                    if (strpos($message, 'won\'t start') !== false || strpos($message, 'dead') !== false || strpos($message, 'battery') !== false) {
                        $recommendations[] = "**Electrical Diagnostics** - to check battery, alternator, and starting system";
                    } else {
                        $recommendations[] = "**Electrical Diagnostics** - to identify electrical problems";
                    }
                    break;
                    
                case 'transmission':
                    $recommendations[] = "**Transmission Diagnostics** - to identify transmission issues";
                    break;
                    
                case 'suspension':
                    $recommendations[] = "**Suspension Inspection** - to check for worn components";
                    break;
            }
        }
        
        if (!empty($recommendations)) {
            $recommendationText = implode("\n• ", $recommendations);
            return "Based on your description, I recommend:\n\n• " . $recommendationText . "\n\nWould you like to schedule an appointment for any of these services?";
        }
    }
    
    // If no specific problems detected, try to match based on general symptoms
    $generalSymptoms = [
        'not starting' => '**Electrical Diagnostics** - to check battery, alternator, and starting system',
        'won\'t start' => '**Electrical Diagnostics** - to check battery, alternator, and starting system',
        'dead battery' => '**Electrical Diagnostics** - to check battery and charging system',
        'running rough' => '**Engine Diagnostics** - to identify engine performance issues',
        'poor performance' => '**Engine Diagnostics** - to check engine and fuel system',
        'bad fuel economy' => '**Engine Diagnostics** - to check engine efficiency and fuel system',
        'check engine light' => '**Engine Diagnostics** - to read diagnostic codes and identify problems',
        'service light' => '**General Diagnostics** - to check all systems and identify issues',
        'maintenance' => '**Oil Change Service** - for regular maintenance and inspection',
        'tune up' => '**Tune Up Service** - to improve engine performance and efficiency'
    ];
    
    foreach ($generalSymptoms as $symptom => $recommendation) {
        if (strpos($message, $symptom) !== false) {
            return "Based on your symptom, I recommend $recommendation. Would you like to schedule an appointment?";
        }
    }
    
    // Final fallback with general guidance
    return "I understand you're having vehicle issues. To provide the best recommendation, I'd need to know more about your specific problem. You can describe the symptoms, or I can recommend our **General Diagnostics** to identify the issue. What specific problem are you experiencing?";
}
?>
