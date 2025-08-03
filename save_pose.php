<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Save a new pose
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['pose']) && is_array($input['pose']) && count($input['pose']) === 6) {
            $pose = $input['pose'];
            
            $stmt = $pdo->prepare("INSERT INTO saved_poses (motor1, motor2, motor3, motor4, motor5, motor6) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$pose[0], $pose[1], $pose[2], $pose[3], $pose[4], $pose[5]]);
            
            $pose_id = $pdo->lastInsertId();
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Pose saved successfully',
                'pose_id' => $pose_id
            ]);
            
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid pose data']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all saved poses
        $stmt = $pdo->query("SELECT * FROM saved_poses ORDER BY created_at DESC");
        $poses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'poses' => $poses]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Delete a pose
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['pose_id'])) {
            $pose_id = intval($input['pose_id']);
            
            $stmt = $pdo->prepare("DELETE FROM saved_poses WHERE id = ?");
            $stmt->execute([$pose_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Pose deleted successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Pose not found']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Pose ID required']);
        }
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>