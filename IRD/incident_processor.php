
<?php
require_once 'config/database.php';
require_once 'ai/emergency_allocator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_incident':
            createIncident();
            break;
        case 'update_incident':
            updateIncident();
            break;
        case 'get_incident':
            getIncident();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_incident') {
        getIncident();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function createIncident() {
    global $pdo, $firebase;
    
    try {
        $pdo->beginTransaction();
        
        // Validate required fields
        $required = ['incident_type', 'location', 'description', 'priority'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Insert incident
        $stmt = $pdo->prepare("
            INSERT INTO incidents 
            (incident_type, location, latitude, longitude, description, reporter_name, reporter_phone, priority, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        
        $stmt->execute([
            $_POST['incident_type'],
            $_POST['location'],
            $_POST['latitude'] ?? null,
            $_POST['longitude'] ?? null,
            $_POST['description'],
            $_POST['reporter_name'] ?? '',
            $_POST['reporter_phone'] ?? '',
            $_POST['priority']
        ]);
        
        $incidentId = $pdo->lastInsertId();
        
        // Log action
        $stmt = $pdo->prepare("
            INSERT INTO incident_actions 
            (incident_id, action_type, description, performed_by, performed_at) 
            VALUES (?, 'create', 'Incident reported', ?, NOW())
        ");
        $stmt->execute([$incidentId, $_SESSION['user_id'] ?? 1]);
        
        $pdo->commit();
        
        // Sync to Firebase
        if ($firebase) {
            $incidentData = [
                'id' => $incidentId,
                'incident_type' => $_POST['incident_type'],
                'location' => $_POST['location'],
                'latitude' => $_POST['latitude'] ?? null,
                'longitude' => $_POST['longitude'] ?? null,
                'priority' => $_POST['priority'],
                'status' => 'pending',
                'description' => $_POST['description'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $firebase->syncIncident($incidentData);
            
            // Send notification
            $firebase->sendNotification(
                'New Incident Reported',
                $_POST['incident_type'] . ' at ' . $_POST['location'],
                'incidents',
                ['incident_id' => $incidentId, 'type' => 'new_incident']
            );
        }
        
        echo json_encode([
            'success' => true, 
            'incident_id' => $incidentId,
            'message' => 'Incident created successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateIncident() {
    global $pdo, $firebase;
    
    try {
        if (empty($_POST['incident_id'])) {
            throw new Exception('Incident ID is required');
        }
        
        $incidentId = $_POST['incident_id'];
        $updatableFields = ['status', 'priority', 'location', 'description'];
        $updates = [];
        $params = [];
        
        foreach ($updatableFields as $field) {
            if (isset($_POST[$field])) {
                $updates[] = "$field = ?";
                $params[] = $_POST[$field];
            }
        }
        
        if (empty($updates)) {
            throw new Exception('No fields to update');
        }
        
        $updates[] = 'updated_at = NOW()';
        $params[] = $incidentId;
        
        $stmt = $pdo->prepare("
            UPDATE incidents 
            SET " . implode(', ', $updates) . " 
            WHERE id = ?
        ");
        
        $stmt->execute($params);
        
        // Log action
        if (isset($_POST['status'])) {
            $actionType = 'status_update';
            $description = "Status changed to: " . $_POST['status'];
        } else {
            $actionType = 'update';
            $description = 'Incident details updated';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO incident_actions 
            (incident_id, action_type, description, performed_by, performed_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $incidentId, 
            $actionType, 
            $description,
            $_SESSION['user_id'] ?? 1
        ]);
        
        // Sync to Firebase
        if ($firebase && isset($_POST['status'])) {
            $firebase->syncUnitStatus($incidentId, $_POST['status']);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Incident updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getIncident() {
    global $pdo;
    
    try {
        if (empty($_REQUEST['id'])) {
            throw new Exception('Incident ID is required');
        }
        
        $incidentId = $_REQUEST['id'];
        
        $stmt = $pdo->prepare("
            SELECT i.*, u.first_name, u.last_name 
            FROM incidents i 
            LEFT JOIN users u ON i.reported_by = u.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$incidentId]);
        $incident = $stmt->fetch();
        
        if (!$incident) {
            throw new Exception('Incident not found');
        }
        
        // Get incident actions
        $stmt = $pdo->prepare("
            SELECT ia.*, u.first_name, u.last_name 
            FROM incident_actions ia 
            LEFT JOIN users u ON ia.performed_by = u.id 
            WHERE ia.incident_id = ? 
            ORDER BY ia.performed_at ASC
        ");
        $stmt->execute([$incidentId]);
        $actions = $stmt->fetchAll();
        
        // Get dispatched units
        $stmt = $pdo->prepare("
            SELECT d.*, u.unit_name, u.unit_type 
            FROM dispatches d 
            JOIN units u ON d.unit_id = u.id 
            WHERE d.incident_id = ? 
            ORDER BY d.dispatched_at ASC
        ");
        $stmt->execute([$incidentId]);
        $dispatches = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'incident' => $incident,
            'actions' => $actions,
            'dispatches' => $dispatches
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
