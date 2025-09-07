<?php
session_start();
require_once '../config/database_manager.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$violationId = $_GET['id'];
$action = $_GET['action'];

try {
    // Get violation details with related information
    $query = "SELECT v.*, 
                     e.name as establishment_name,
                     e.address as establishment_address,
                     e.owner_name,
                     e.owner_contact,
                     ir.inspection_date,
                     ir.overall_rating,
                     ci.item_text,
                     c.name as checklist_name,
                     CONCAT(u.first_name, ' ', u.last_name) as inspector_name
              FROM ficr.violations v
              LEFT JOIN ficr.inspection_results ir ON v.inspection_id = ir.id
              LEFT JOIN ficr.establishments e ON ir.establishment_id = e.id
              LEFT JOIN ficr.checklist_items ci ON v.item_id = ci.id
              LEFT JOIN ficr.inspection_checklists c ON ci.checklist_id = c.id
              LEFT JOIN frsm.users u ON ir.inspector_id = u.id
              WHERE v.id = ?";
    
    $violation = $dbManager->fetch("ficr", $query, [$violationId]);
    
    if (!$violation) {
        http_response_code(404);
        echo json_encode(['error' => 'Violation not found']);
        exit;
    }
    
    if ($action === 'view') {
        // Format for viewing
        $deadlineDate = new DateTime($violation['deadline']);
        $today = new DateTime();
        $interval = $today->diff($deadlineDate);
        
        $deadlineClass = '';
        if ($violation['status'] === 'resolved') {
            $deadlineClass = 'deadline-passed';
        } elseif ($interval->days <= 3 && $interval->invert == 0) {
            $deadlineClass = 'deadline-near';
        } elseif ($interval->invert == 1) {
            $deadlineClass = 'deadline-passed';
        } else {
            $deadlineClass = 'deadline-future';
        }
        
        // Determine payment status
        $paymentStatus = '';
        if ($violation['fine_amount'] > 0) {
            if ($violation['paid_amount'] >= $violation['fine_amount']) {
                $paymentStatus = '<span class="payment-status payment-full">Paid</span>';
            } elseif ($violation['paid_amount'] > 0) {
                $paymentStatus = '<span class="payment-status payment-partial">Partial</span>';
            } else {
                $paymentStatus = '<span class="payment-status payment-none">Unpaid</span>';
            }
        }
        
        $html = '
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Violation Code</label>
                    <p>'.$violation['violation_code'].'</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Status</label>
                    <p><span class="violation-badge badge-'.$violation['status'].'">'.ucfirst(str_replace('_', ' ', $violation['status'])).'</span></p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Establishment</label>
                    <p>'.$violation['establishment_name'].'<br><small class="text-muted">'.$violation['establishment_address'].'</small></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Owner Information</label>
                    <p>'.$violation['owner_name'].'<br><small class="text-muted">'.$violation['owner_contact'].'</small></p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Inspection Details</label>
                    <p>Date: '.date('M d, Y', strtotime($violation['inspection_date'])).'<br>
                       Inspector: '.$violation['inspector_name'].'<br>
                       Rating: '.ucfirst($violation['overall_rating']).'</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Checklist Item</label>
                    <p>'.$violation['checklist_name'].' - '.$violation['item_text'].'</p>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Description</label>
            <p>'.$violation['description'].'</p>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Severity</label>
            <p><span class="violation-badge badge-'.$violation['severity'].'">'.ucfirst($violation['severity']).'</span></p>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Corrective Action Required</label>
            <p>'.$violation['corrective_action'].'</p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Compliance Deadline</label>
                    <p class="'.$deadlineClass.'">'.date('M d, Y', strtotime($violation['deadline'])).'</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Fine Amount</label>
                    <p>'.($violation['fine_amount'] > 0 ? '₱'.number_format($violation['fine_amount'], 2) : 'No fine').' '.$paymentStatus.'</p>
                </div>
            </div>
        </div>';
        
        if ($violation['paid_amount'] > 0) {
            $html .= '
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Paid Amount</label>
                        <p>₱'.number_format($violation['paid_amount'], 2).'</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Date</label>
                        <p>'.($violation['payment_date'] ? date('M d, Y', strtotime($violation['payment_date'])) : 'Not paid').'</p>
                    </div>
                </div>
            </div>';
        }
        
        if ($violation['resolution_notes']) {
            $html .= '
            <div class="mb-3">
                <label class="form-label fw-bold">Resolution Notes</label>
                <p>'.$violation['resolution_notes'].'</p>
            </div>';
        }
        
        $html .= '
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Created At</label>
                    <p>'.date('M d, Y H:i', strtotime($violation['created_at'])).'</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Last Updated</label>
                    <p>'.date('M d, Y H:i', strtotime($violation['updated_at'])).'</p>
                </div>
            </div>
        </div>';
        
        echo $html;
        
    } elseif ($action === 'edit') {
        // Get inspections for dropdown
        $inspections = $dbManager->fetchAll("ficr", 
            "SELECT ir.id, e.name as establishment_name, ir.inspection_date 
             FROM inspection_results ir 
             LEFT JOIN establishments e ON ir.establishment_id = e.id 
             ORDER BY ir.inspection_date DESC");
        
        // Get checklist items for dropdown
        $checklist_items = $dbManager->fetchAll("ficr", 
            "SELECT ci.id, ci.item_text, c.name as checklist_name 
             FROM checklist_items ci 
             LEFT JOIN inspection_checklists c ON ci.checklist_id = c.id 
             ORDER BY c.name, ci.order_index");
        
        $html = '
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_inspection_id" class="form-label">Inspection</label>
                    <select class="form-select" id="edit_inspection_id" name="inspection_id" required>
                        <option value="">Select Inspection</option>';
        
        foreach ($inspections as $inspection) {
            $selected = $inspection['id'] == $violation['inspection_id'] ? 'selected' : '';
            $html .= '<option value="'.$inspection['id'].'" '.$selected.'>'.
                     htmlspecialchars($inspection['establishment_name'] . ' - ' . date('M d, Y', strtotime($inspection['inspection_date']))).
                     '</option>';
        }
        
        $html .= '
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_item_id" class="form-label">Checklist Item</label>
                    <select class="form-select" id="edit_item_id" name="item_id" required>
                        <option value="">Select Checklist Item</option>';
        
        foreach ($checklist_items as $item) {
            $selected = $item['id'] == $violation['item_id'] ? 'selected' : '';
            $html .= '<option value="'.$item['id'].'" '.$selected.'>'.
                     htmlspecialchars($item['checklist_name'] . ' - ' . $item['item_text']).
                     '</option>';
        }
        
        $html .= '
                    </select>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_violation_code" class="form-label">Violation Code</label>
                    <input type="text" class="form-control" id="edit_violation_code" name="violation_code" value="'.htmlspecialchars($violation['violation_code']).'" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_severity" class="form-label">Severity</label>
                    <select class="form-select" id="edit_severity" name="severity" required>';
        
        $severities = ['minor', 'major', 'critical'];
        foreach ($severities as $severity) {
            $selected = $severity == $violation['severity'] ? 'selected' : '';
            $html .= '<option value="'.$severity.'" '.$selected.'>'.ucfirst($severity).'</option>';
        }
        
        $html .= '
                    </select>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="edit_description" class="form-label">Description</label>
            <textarea class="form-control" id="edit_description" name="description" rows="3" required>'.htmlspecialchars($violation['description']).'</textarea>
        </div>
        
        <div class="mb-3">
            <label for="edit_corrective_action" class="form-label">Corrective Action Required</label>
            <textarea class="form-control" id="edit_corrective_action" name="corrective_action" rows="2" required>'.htmlspecialchars($violation['corrective_action']).'</textarea>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_deadline" class="form-label">Compliance Deadline</label>
                    <input type="date" class="form-control" id="edit_deadline" name="deadline" value="'.$violation['deadline'].'" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_status" class="form-label">Status</label>
                    <select class="form-select" id="edit_status" name="status" required>';
        
        $statuses = ['open', 'in_progress', 'resolved', 'overdue'];
        foreach ($statuses as $status) {
            $selected = $status == $violation['status'] ? 'selected' : '';
            $html .= '<option value="'.$status.'" '.$selected.'>'.ucfirst(str_replace('_', ' ', $status)).'</option>';
        }
        
        $html .= '
                    </select>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_fine_amount" class="form-label">Fine Amount</label>
                    <input type="number" class="form-control" id="edit_fine_amount" name="fine_amount" value="'.$violation['fine_amount'].'" min="0" step="0.01">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_paid_amount" class="form-label">Paid Amount</label>
                    <input type="number" class="form-control" id="edit_paid_amount" name="paid_amount" value="'.$violation['paid_amount'].'" min="0" step="0.01">
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="edit_payment_date" class="form-label">Payment Date</label>
            <input type="date" class="form-control" id="edit_payment_date" name="payment_date" value="'.$violation['payment_date'].'">
        </div>';
        
        echo $html;
    }
    
} catch (Exception $e) {
    error_log("Error in violation_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}