<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization');

require_once '../config/database.php';
require_once '../config/firebase.php';
require_once '../ai/emergency_allocator.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize AI allocator
$allocator = new EmergencyAllocator($pdo, $firebase);

// Get the HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Get the requested ID if provided
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if ($id) {
            // Get a specific incident
            $stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
            $stmt->execute([$id]);
            $incident = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($incident) {
                // Get units assigned to this incident
                $stmt = $pdo->prepare("
                    SELECT u.* FROM units u 
                    INNER JOIN incident_units iu ON u.id = iu.unit_id 
                    WHERE iu.incident_id = ?
                ");
                $stmt->execute([$id]);
                $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $incident['assigned_units'] = $units;
                
                echo json_encode([
                    'success' => true,
                    'data' => $incident
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Incident not found'
                ]);
            }
        } else {
            // Get all incidents with optional filters
            $filters = [];
            $params = [];
            
            $sql = "SELECT * FROM incidents WHERE 1=1";
            
            if (isset($_GET['status'])) {
                $sql .= " AND status = ?";
                $params[] = $_GET['status'];
            }
            
            if (isset($_GET['type'])) {
                $sql .= " AND incident_type = ?";
                $params[] = $_GET['type'];
            }
            
            if (isset($_GET['priority'])) {
                $sql .= " AND priority = ?";
                $params[] = $_GET['priority'];
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $incidents,
                'count' => count($incidents)
            ]);
        }
        break;
        
    case 'POST':
        // Create a new incident
        if (!isset($input['incident_type']) || !isset($input['location']) || 
            !isset($input['latitude']) || !isset($input['longitude'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: incident_type, location, latitude, longitude'
            ]);
            break;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Insert the new incident
            $stmt = $pdo->prepare("
                INSERT INTO incidents 
                (incident_type, location, latitude, longitude, priority, status, description, reporter_info, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $priority = $input['priority'] ?? 'medium';
            $status = $input['status'] ?? 'pending';
            $description = $input['description'] ?? '';
            $reporterInfo = $input['reporter_info'] ?? '';
            
            $stmt->execute([
                $input['incident_type'],
                $input['location'],
                $input['latitude'],
                $input['longitude'],
                $priority,
                $status,
                $description,
                $reporterInfo
            ]);
            
            $incidentId = $pdo->lastInsertId();
            
            // Get the full incident record
            $stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
            $stmt->execute([$incidentId]);
            $incident = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Analyze the incident using AI
            $analysis = $allocator->analyzeIncident($incident);
            
            // Update incident with analysis results
            $stmt = $pdo->prepare("
                UPDATE incidents 
                SET severity = ?, risk_level = ?, estimated_response_time = ?, recommendations = ?
                WHERE id = ?
            ");
            
            $recommendationsJson = json_encode($analysis['recommendations']);
            
            $stmt->execute([
                $analysis['severity'],
                $analysis['risk_level'],
                $analysis['estimated_response_time'],
                $recommendationsJson,
                $incidentId
            ]);
            
            // Get the updated incident record
            $stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
            $stmt->execute([$incidentId]);
            $incident = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Sync to Firebase
            if ($firebase) {
                $firebase->syncIncident($incident);
                
                // Send notification for high priority incidents
                if ($priority === 'high' || $priority === 'critical') {
                    $firebase->sendNotification(
                        "New {$priority} priority incident",
                        "{$incident['incident_type']} at {$incident['location']}",
                        'incidents',
                        ['incident_id' => $incidentId, 'type' => 'new_incident']
                    );
                }
            }
            
            $pdo->commit();
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Incident created successfully',
                'data' => $incident,
                'analysis' => $analysis
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create incident: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'PUT':
        // Update an existing incident
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Incident ID is required'
            ]);
            break;
        }
        
        try {
            // Check if incident exists
            $stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Incident not found'
                ]);
                break;
            }
            
            // Build update query dynamically based on provided fields
            $updates = [];
            $params = [];
            
            $allowedFields = ['incident_type', 'location', 'latitude', 'longitude', 
                             'priority', 'status', 'description', 'reporter_info',
                             'severity', 'risk_level', 'estimated_response_time'];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "{$field} = ?";
                    $params[] = $input[$field];
                }
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No valid fields to update'
                ]);
                break;
            }
            
            $updates[] = "updated_at = NOW()";
            
            $sql = "UPDATE incidents SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Get the updated incident
            $stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
            $stmt->execute([$id]);
            $incident = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Sync to Firebase
            if ($firebase) {
                $firebase->syncIncident($incident);
                
                // Send notification for status changes
                if (isset($input['status']) && $input['status'] !== $existing['status']) {
                    $firebase->sendNotification(
                        "Incident status updated",
                        "Incident at {$incident['location']} is now {$incident['status']}",
                        'incidents',
                        ['incident_id' => $id, 'type' => 'status_update']
                    );
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Incident updated successfully',
                'data' => $incident
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update incident: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'DELETE':
        // Delete an incident (typically not used in production, would archive instead)
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Incident ID is required'
            ]);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM incidents WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                // Also remove from Firebase
                if ($firebase) {
                    try {
                        $firebase->getDatabase()->getReference('incidents/' . $id)->remove();
                    } catch (Exception $e) {
                        // Log error but don't fail the request
                        error_log("Failed to remove incident from Firebase: " . $e->getMessage());
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Incident deleted successfully'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Incident not found'
                ]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete incident: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        break;
}
?>