<?php
// Simple error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "robot-control-panel";

try {
    // Create connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get action parameter
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($action)) {
        // Default: Get all poses from database
        $tableExists = $pdo->query("SHOW TABLES LIKE 'poses'")->rowCount() > 0;
        
        if (!$tableExists) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Table poses does not exist in database robot-control-panel'
            ]);
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT id, motor1, motor2, motor3, motor4, motor5, motor6, status FROM poses ORDER BY id ASC");
        $stmt->execute();
        $poses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success', 
            'poses' => $poses,
            'count' => count($poses)
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'delete') {
        // Delete a pose via GET
        if (isset($_GET['pose_id'])) {
            $pose_id = intval($_GET['pose_id']);
            
            $stmt = $pdo->prepare("DELETE FROM poses WHERE id = ?");
            $stmt->execute([$pose_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Pose deleted successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Pose not found']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Pose ID required']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'activate') {
        // Activate a pose via GET
        if (isset($_GET['pose_id'])) {
            $pose_id = intval($_GET['pose_id']);
            
            // Set all poses to inactive
            $pdo->prepare("UPDATE poses SET status = 0")->execute();
            
            // Set specific pose to active
            $stmt = $pdo->prepare("UPDATE poses SET status = 1 WHERE id = ?");
            $stmt->execute([$pose_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Pose activated successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Pose not found']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Pose ID required']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'save') {
        // Save a new pose via GET
        if (isset($_GET['m1'], $_GET['m2'], $_GET['m3'], $_GET['m4'], $_GET['m5'], $_GET['m6'])) {
            $motors = [
                intval($_GET['m1']),
                intval($_GET['m2']),
                intval($_GET['m3']),
                intval($_GET['m4']),
                intval($_GET['m5']),
                intval($_GET['m6'])
            ];
            
            $stmt = $pdo->prepare("INSERT INTO poses (motor1, motor2, motor3, motor4, motor5, motor6, status) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute($motors);
            
            $pose_id = $pdo->lastInsertId();
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Pose saved successfully',
                'pose_id' => $pose_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'All motor values required (m1-m6)']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'run') {
        // Run motors and activate matching pose
        if (isset($_GET['m1'], $_GET['m2'], $_GET['m3'], $_GET['m4'], $_GET['m5'], $_GET['m6'])) {
            $motors = [
                intval($_GET['m1']),
                intval($_GET['m2']),
                intval($_GET['m3']),
                intval($_GET['m4']),
                intval($_GET['m5']),
                intval($_GET['m6'])
            ];
            
            // Set all poses to inactive first
            $pdo->prepare("UPDATE poses SET status = 0")->execute();
            
            // Find if this exact pose exists
            $stmt = $pdo->prepare("SELECT id FROM poses WHERE motor1=? AND motor2=? AND motor3=? AND motor4=? AND motor5=? AND motor6=?");
            $stmt->execute($motors);
            $existingPose = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingPose) {
                // If exact pose exists, activate it
                $updateStmt = $pdo->prepare("UPDATE poses SET status = 1 WHERE id = ?");
                $updateStmt->execute([$existingPose['id']]);
            }
            
            echo json_encode(['status' => 'success', 'message' => 'Motors updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'All motor values required (m1-m6)']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle POST requests for backward compatibility
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action']) && $input['action'] === 'save_pose') {
            if (isset($input['pose']) && is_array($input['pose']) && count($input['pose']) === 6) {
                $pose = $input['pose'];
                
                $stmt = $pdo->prepare("INSERT INTO poses (motor1, motor2, motor3, motor4, motor5, motor6, status) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$pose[0], $pose[1], $pose[2], $pose[3], $pose[4], $pose[5]]);
                
                $pose_id = $pdo->lastInsertId();
                
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Pose saved successfully',
                    'pose_id' => $pose_id
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid pose data']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    }
    
} catch(PDOException $e) {
    $errorMsg = $e->getMessage();
    
    if (strpos($errorMsg, 'Unknown database') !== false) {
        $errorMsg = 'Database robot-control-panel does not exist';
    } elseif (strpos($errorMsg, 'Access denied') !== false) {
        $errorMsg = 'Access denied for user root - check MySQL credentials';
    } elseif (strpos($errorMsg, 'Connection refused') !== false) {
        $errorMsg = 'Cannot connect to MySQL server - is it running?';
    }
    
    echo json_encode([
        'status' => 'error', 
        'message' => $errorMsg,
        'error_type' => 'database'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'General error: ' . $e->getMessage(),
        'error_type' => 'general'
    ]);
}
?>