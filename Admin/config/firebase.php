<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class FirebaseIntegration {
    private $firebase;
    private $database;
    private $auth;
    private $messaging;
    private $isInitialized = false;
    
    public function __construct() {
        try {
            $serviceAccountPath = __DIR__ . '/frsm-470418-7671325dd08c.json';
            
            if (!file_exists($serviceAccountPath)) {
                throw new Exception("Firebase service account file not found");
            }
            
            $this->firebase = (new Factory)
                ->withServiceAccount($serviceAccountPath)
                ->withDatabaseUri('https://frsm-89ad8-default-rtdb.firebaseio.com/');
                
            $this->database = $this->firebase->createDatabase();
            $this->auth = $this->firebase->createAuth();
            $this->messaging = $this->firebase->createMessaging();
            $this->isInitialized = true;
            
        } catch (Exception $e) {
            error_log("Firebase initialization error: " . $e->getMessage());
            $this->isInitialized = false;
        }
    }
    
    public function isInitialized() {
        return $this->isInitialized;
    }
    
    /**
     * Sync incident to Firebase Realtime Database
     */
    public function syncIncident($incidentData) {
        if (!$this->isInitialized || !$this->database) {
            error_log("Firebase not initialized, skipping incident sync");
            return false;
        }
        
        try {
            $incidentRef = $this->database->getReference('incidents/' . $incidentData['id']);
            $incidentRef->set([
                'type' => $incidentData['incident_type'],
                'location' => $incidentData['location'],
                'latitude' => $incidentData['latitude'] ?? null,
                'longitude' => $incidentData['longitude'] ?? null,
                'priority' => $incidentData['priority'],
                'status' => $incidentData['status'],
                'description' => $incidentData['description'],
                'reportedAt' => $incidentData['created_at'],
                'updatedAt' => date('Y-m-d H:i:s')
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Firebase sync error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sync unit status to Firebase
     */
    public function syncUnitStatus($unitId, $status, $location = null) {
        if (!$this->isInitialized || !$this->database) {
            error_log("Firebase not initialized, skipping unit sync");
            return false;
        }
        
        try {
            $unitRef = $this->database->getReference('units/' . $unitId);
            $updateData = [
                'status' => $status,
                'lastUpdated' => date('Y-m-d H:i:s')
            ];
            
            if ($location) {
                $updateData['currentLocation'] = $location;
            }
            
            $unitRef->update($updateData);
            
            return true;
        } catch (Exception $e) {
            error_log("Firebase unit sync error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get real-time updates from Firebase
     */
    public function listenForUpdates($callback) {
        if (!$this->isInitialized || !$this->database) {
            return false;
        }
        
        try {
            $reference = $this->database->getReference('updates');
            $reference->onChildChanged(function($snapshot) use ($callback) {
                $update = $snapshot->getValue();
                $callback($update);
            });
            
            return true;
        } catch (Exception $e) {
            error_log("Firebase listener error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Push notification to mobile apps via Firebase
     */
    public function sendNotification($title, $body, $topic = 'incidents', $data = []) {
        if (!$this->isInitialized || !$this->messaging) {
            error_log("Firebase messaging not initialized, skipping notification");
            return false;
        }
        
        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('topic', $topic)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);
                
            $this->messaging->send($message);
            
            return true;
        } catch (Exception $e) {
            error_log("Firebase notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active incidents from Firebase
     */
    public function getActiveIncidents() {
        if (!$this->isInitialized || !$this->database) {
            return [];
        }
        
        try {
            $reference = $this->database->getReference('incidents')
                ->orderByChild('status')
                ->equalTo('active');
                
            $snapshot = $reference->getSnapshot();
            return $snapshot->getValue() ?: [];
        } catch (Exception $e) {
            error_log("Firebase get incidents error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get available units from Firebase
     */
    public function getAvailableUnits() {
        if (!$this->isInitialized || !$this->database) {
            return [];
        }
        
        try {
            $reference = $this->database->getReference('units')
                ->orderByChild('status')
                ->equalTo('available');
                
            $snapshot = $reference->getSnapshot();
            return $snapshot->getValue() ?: [];
        } catch (Exception $e) {
            error_log("Firebase get units error: " . $e->getMessage());
            return [];
        }
    }
}

$firebase = null;
try {
    $firebase = new FirebaseIntegration();
    if (!$firebase->isInitialized()) {
        error_log("Firebase failed to initialize properly");
    }
} catch (Exception $e) {
    error_log("Failed to create Firebase instance: " . $e->getMessage());
    $firebase = null;
}
?>
