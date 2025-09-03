<?php
session_start();
require_once 'config/database.php';
require_once 'config/firebase.php';

// Get all active incidents
$stmt = $pdo->prepare("
    SELECT * FROM incidents 
    WHERE status IN ('pending', 'dispatched', 'responding')
    ORDER BY created_at DESC
");
$stmt->execute();
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all units
$stmt = $pdo->prepare("SELECT * FROM units ORDER BY status");
$stmt->execute();
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get hydrants
$stmt = $pdo->prepare("SELECT * FROM hydrants WHERE status = 'active'");
$stmt->execute();
$hydrants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Incident Map - Quezon City Fire Department</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        :root {
            --primary: #d9534f;
            --secondary: #343a40;
            --accent: #f0ad4e;
            --light: #f8f9fa;
            --dark: #343a40;
            --success: #5cb85c;
            --warning: #f0ad4e;
            --danger: #d9534f;
            --info: #5bc0de;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, #c9302c 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        #map-container {
            height: calc(100vh - 76px);
            width: 100%;
        }
        
        .sidebar {
            position: absolute;
            top: 76px;
            right: 20px;
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        
        .sidebar-content {
            padding: 15px;
        }
        
        .incident-item {
            border-left: 4px solid var(--danger);
            padding: 10px 15px;
            margin-bottom: 10px;
            background-color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .incident-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .incident-item.medical {
            border-left-color: var(--info);
        }
        
        .incident-item.rescue {
            border-left-color: var(--warning);
        }
        
        .incident-item.hazmat {
            border-left-color: var(--success);
        }
        
        .priority-high {
            color: var(--danger);
            font-weight: 700;
        }
        
        .priority-medium {
            color: var(--warning);
            font-weight: 700;
        }
        
        .priority-low {
            color: var(--success);
            font-weight: 700;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-dispatched {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-responding {
            background-color: #d4edda;
            color: #155724;
        }
        
        .map-controls {
            position: absolute;
            top: 90px;
            left: 20px;
            z-index: 1000;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            padding: 15px;
            width: 250px;
        }
        
        .control-title {
            font-weight: 600;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .legend {
            margin-top: 15px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .filter-group {
            margin-bottom: 15px;
        }
        
        .filter-title {
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .form-check {
            margin-bottom: 5px;
        }
        
        .unit-marker {
            width: 30px;
            height: 30px;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .unit-marker i {
            transform: rotate(45deg);
            color: white;
        }
        
        .unit-available {
            background-color: var(--success);
        }
        
        .unit-dispatched {
            background-color: var(--warning);
        }
        
        .unit-responding {
            background-color: var(--info);
        }
        
        .unit-onscene {
            background-color: var(--primary);
        }
        
        .unit-maintenance {
            background-color: var(--secondary);
        }
        
        .incident-marker {
            width: 24px;
            height: 24px;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .incident-marker i {
            transform: rotate(45deg);
            color: white;
            font-size: 12px;
        }
        
        .incident-fire {
            background-color: var(--danger);
        }
        
        .incident-medical {
            background-color: var(--info);
        }
        
        .incident-rescue {
            background-color: var(--warning);
        }
        
        .incident-hazmat {
            background-color: var(--success);
        }
        
        .hydrant-marker {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #007bff;
            border: 2px solid white;
        }
        
        .hydrant-marker i {
            color: white;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-fire-extinguisher me-2"></i>QCFD AI Dispatch
            </a>
            <div class="d-flex">
                <a href="index.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-home me-1"></i> Dashboard
                </a>
                <span class="navbar-text me-3">
                    <i class="fas fa-user-shield me-1"></i> Dispatcher #<?php echo rand(100, 999); ?>
                </span>
            </div>
        </div>
    </nav>

    <!-- Map Container -->
    <div id="map-container"></div>
    
    <!-- Map Controls -->
    <div class="map-controls">
        <div class="control-title">Map Controls</div>
        
        <div class="filter-group">
            <div class="filter-title">Incident Types</div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="show-fires" checked>
                <label class="form-check-label" for="show-fires">Fires</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="show-medical" checked>
                <label class="form-check-label" for="show-medical">Medical</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="show-rescue" checked>
                <label class="form-check-label" for="show-rescue">Rescue</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="show-hazmat" checked>
                <label class="form-check-label" for="show-hazmat">HazMat</label>
            </div>
        </div>
        
        <div class="filter-group">
            <div class="filter-title">Units</div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="show-engines" checked>
                <label class="form-check-label" for="show-engines">Engines</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="show-ladders" checked>
                <label class="form-check-label" for="show-ladders">Ladders</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="show-ambulances" checked>
                <label class="form-check-label" for="show-ambulances">Ambulances</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="show-special" checked>
                <label class="form-check-label" for="show-special">Special Units</label>
            </div>
        </div>
        
        <div class="filter-group">
            <div class="filter-title">Resources</div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="show-hydrants" checked>
                <label class="form-check-label" for="show-hydrants">Hydrants</label>
            </div>
        </div>
        
        <div class="legend">
            <div class="control-title">Legend</div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #d9534f;"></div>
                <span>Fire Incidents</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #5bc0de;"></div>
                <span>Medical Incidents</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #f0ad4e;"></div>
                <span>Rescue Incidents</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #5cb85c;"></div>
                <span>HazMat Incidents</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #007bff;"></div>
                <span>Hydrants</span>
            </div>
        </div>
    </div>
    
    <!-- Sidebar for Incident List -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Active Incidents</h5>
        </div>
        <div class="sidebar-content">
            <?php if (count($incidents) > 0): ?>
                <?php foreach ($incidents as $incident): ?>
                    <div class="incident-item <?php echo $incident['incident_type']; ?>" 
                         data-id="<?php echo $incident['id']; ?>"
                         data-lat="<?php echo $incident['latitude']; ?>"
                         data-lng="<?php echo $incident['longitude']; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-uppercase"><?php echo $incident['incident_type']; ?></h6>
                            <span class="status-badge status-<?php echo $incident['status']; ?>">
                                <?php echo ucfirst($incident['status']); ?>
                            </span>
                        </div>
                        <p class="mb-1 mt-2"><?php echo $incident['location']; ?></p>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="priority-<?php echo $incident['priority']; ?>">
                                Priority: <?php echo ucfirst($incident['priority']); ?>
                            </small>
                            <small><?php echo date('H:i', strtotime($incident['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                    <p class="mb-0">No active incidents</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Initialize map centered on Quezon City
        const map = L.map('map-container').setView([14.6760, 121.0437], 13);
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Create layer groups
        const incidentLayer = L.layerGroup().addTo(map);
        const unitLayer = L.layerGroup().addTo(map);
        const hydrantLayer = L.layerGroup().addTo(map);
        
        // Add incidents to map
        <?php foreach ($incidents as $incident): ?>
            (function() {
                const incident = <?php echo json_encode($incident); ?>;
                const lat = parseFloat(incident.latitude) || 14.6760;
                const lng = parseFloat(incident.longitude) || 121.0437;
                
                // Create custom marker based on incident type
                let markerHtml = '';
                let markerClass = '';
                
                switch(incident.incident_type) {
                    case 'structure-fire':
                    case 'vehicle-fire':
                    case 'wildfire':
                        markerHtml = '<i class="fas fa-fire"></i>';
                        markerClass = 'incident-marker incident-fire';
                        break;
                    case 'medical':
                        markerHtml = '<i class="fas fa-plus-circle"></i>';
                        markerClass = 'incident-marker incident-medical';
                        break;
                    case 'rescue':
                        markerHtml = '<i class="fas fa-life-ring"></i>';
                        markerClass = 'incident-marker incident-rescue';
                        break;
                    case 'hazmat':
                        markerHtml = '<i class="fas fa-radiation-alt"></i>';
                        markerClass = 'incident-marker incident-hazmat';
                        break;
                    default:
                        markerHtml = '<i class="fas fa-exclamation-circle"></i>';
                        markerClass = 'incident-marker incident-fire';
                }
                
                const marker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: markerClass,
                        html: markerHtml,
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    })
                }).addTo(incidentLayer);
                
                // Add popup with incident details
                marker.bindPopup(`
                    <div class="fw-bold text-uppercase">${incident.incident_type.replace('-', ' ')}</div>
                    <div class="my-2">${incident.location}</div>
                    <div class="d-flex justify-content-between">
                        <span class="badge bg-${incident.priority === 'high' ? 'danger' : incident.priority === 'medium' ? 'warning' : 'success'}">
                            ${incident.priority} priority
                        </span>
                        <span class="badge bg-secondary">${incident.status}</span>
                    </div>
                    <div class="mt-2">
                        <small>Reported: ${new Date(incident.created_at).toLocaleTimeString()}</small>
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary w-100" onclick="viewIncident(${incident.id})">
                            View Details
                        </button>
                    </div>
                `);
            })();
        <?php endforeach; ?>
        
        // Add units to map
        <?php foreach ($units as $unit): ?>
            (function() {
                const unit = <?php echo json_encode($unit); ?>;
                const lat = parseFloat(unit.latitude) || 14.6760 + (Math.random() - 0.5) * 0.05;
                const lng = parseFloat(unit.longitude) || 121.0437 + (Math.random() - 0.5) * 0.05;
                
                // Create custom marker based on unit status
                let markerHtml = '';
                let markerClass = '';
                
                switch(unit.status) {
                    case 'available':
                        markerHtml = '<i class="fas fa-check"></i>';
                        markerClass = 'unit-marker unit-available';
                        break;
                    case 'dispatched':
                        markerHtml = '<i class="fas fa-paper-plane"></i>';
                        markerClass = 'unit-marker unit-dispatched';
                        break;
                    case 'responding':
                        markerHtml = '<i class="fas fa-running"></i>';
                        markerClass = 'unit-marker unit-responding';
                        break;
                    case 'onscene':
                        markerHtml = '<i class="fas fa-first-aid"></i>';
                        markerClass = 'unit-marker unit-onscene';
                        break;
                    case 'maintenance':
                        markerHtml = '<i class="fas fa-tools"></i>';
                        markerClass = 'unit-marker unit-maintenance';
                        break;
                    default:
                        markerHtml = '<i class="fas fa-question"></i>';
                        markerClass = 'unit-marker unit-maintenance';
                }
                
                const marker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: markerClass,
                        html: markerHtml,
                        iconSize: [30, 30],
                        iconAnchor: [15, 30]
                    })
                }).addTo(unitLayer);
                
                // Add popup with unit details
                marker.bindPopup(`
                    <div class="fw-bold">${unit.unit_type} ${unit.unit_number}</div>
                    <div class="my-2">Status: <span class="text-capitalize">${unit.status}</span></div>
                    <div class="d-flex justify-content-between">
                        <span>Crew: ${unit.crew_size || 'N/A'}</span>
                        <span>Capacity: ${unit.capacity || 'N/A'}</span>
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary w-100" onclick="viewUnit(${unit.id})">
                            View Details
                        </button>
                    </div>
                `);
            })();
        <?php endforeach; ?>
        
        // Add hydrants to map
        <?php foreach ($hydrants as $hydrant): ?>
            (function() {
                const hydrant = <?php echo json_encode($hydrant); ?>;
                const lat = parseFloat(hydrant.latitude) || 14.6760 + (Math.random() - 0.5) * 0.05;
                const lng = parseFloat(hydrant.longitude) || 121.0437 + (Math.random() - 0.5) * 0.05;
                
                const marker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'hydrant-marker',
                        html: '<i class="fas fa-faucet"></i>',
                        iconSize: [18, 18],
                        iconAnchor: [9, 9]
                    })
                }).addTo(hydrantLayer);
                
                // Add popup with hydrant details
                marker.bindPopup(`
                    <div class="fw-bold">Hydrant ${hydrant.hydrant_id}</div>
                    <div class="my-2">Type: ${hydrant.type}</div>
                    <div>Pressure: ${hydrant.pressure || 'N/A'} PSI</div>
                    <div>Last inspected: ${hydrant.last_inspected || 'N/A'}</div>
                `);
            })();
        <?php endforeach; ?>
        
        // Add event listeners to sidebar items
        document.querySelectorAll('.incident-item').forEach(item => {
            item.addEventListener('click', function() {
                const lat = parseFloat(this.dataset.lat);
                const lng = parseFloat(this.dataset.lng);
                map.setView([lat, lng], 16);
            });
        });
        
        // Add event listeners to filter controls
        document.getElementById('show-fires').addEventListener('change', function() {
            // Implementation would filter fire incidents
            console.log('Filter fires:', this.checked);
        });
        
        // Similar event listeners for other filters...
        
        // Function to view incident details
        function viewIncident(id) {
            window.location.href = `incident_details.php?id=${id}`;
        }
        
        // Function to view unit details
        function viewUnit(id) {
            window.location.href = `unit_details.php?id=${id}`;
        }
        
        // Auto-refresh map every 30 seconds
        setInterval(() => {
            // In a real implementation, this would fetch new data
            console.log('Refreshing map data...');
        }, 30000);
    </script>
</body>
</html>
