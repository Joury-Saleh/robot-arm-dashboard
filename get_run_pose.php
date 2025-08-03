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
    // Create PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === [GET] ===
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get the currently active pose (status = 1)
        $stmt = $pdo->query("SELECT motor1, motor2, motor3, motor4, motor5, motor6 FROM poses WHERE status = 1 ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode([
                'status' => 'success',
                'pose' => array_values($result)
            ]);
        } else {
            // If no active pose found, get the most recent pose
            $stmt = $pdo->query("SELECT motor1, motor2, motor3, motor4, motor5, motor6 FROM poses ORDER BY id DESC LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'pose' => array_values($result)
                ]);
            } else {
                // Return default pose if no poses exist
                echo json_encode([
                    'status' => 'default',
                    'pose' => [90, 90, 90, 90, 90, 90]
                ]);
            }
        }

    // === [POST] ===
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['motors']) && is_array($input['motors']) && count($input['motors']) === 6) {
            $motors = $input['motors'];

            // First, set all poses to inactive
            $pdo->prepare("UPDATE poses SET status = 0")->execute();

            // Then save new pose as active
            $stmt = $pdo->prepare("
                INSERT INTO poses (motor1, motor2, motor3, motor4, motor5, motor6, status)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute($motors);

            echo json_encode(['status' => 'success', 'message' => 'Pose saved and activated']);
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
