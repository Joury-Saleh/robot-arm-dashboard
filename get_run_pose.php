<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "robot-control-panel";

try {
    // Create connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get current robot pose
        $stmt = $pdo->query("SELECT motor1, motor2, motor3, motor4, motor5, motor6 FROM current_pose ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $pose = implode(',', [
                $result['motor1'],
                $result['motor2'], 
                $result['motor3'],
                $result['motor4'],
                $result['motor5'],
                $result['motor6']
            ]);
            echo $pose;
        } else {
            echo "0,190,290,390,459,515,634"; // Default values
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update robot pose
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['motors']) && is_array($input['motors']) && count($input['motors']) === 6) {
            $motors = $input['motors'];
            
            // Update current pose in database
            $stmt = $pdo->prepare("INSERT INTO current_pose (motor1, motor2, motor3, motor4, motor5, motor6, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE motor1=?, motor2=?, motor3=?, motor4=?, motor5=?, motor6=?, timestamp=NOW()");
            
            $stmt->execute([
                $motors[0], $motors[1], $motors[2], $motors[3], $motors[4], $motors[5],
                $motors[0], $motors[1], $motors[2], $motors[3], $motors[4], $motors[5]
            ]);
            
            // Log the movement
            $logStmt = $pdo->prepare("INSERT INTO movement_log (motor1, motor2, motor3, motor4, motor5, motor6, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $logStmt->execute($motors);
            
            echo json_encode(['status' => 'success', 'message' => 'Robot pose updated successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid motor data']);
        }
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>