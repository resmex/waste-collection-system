<?php

require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
require_role(['owner']);

header('Content-Type: application/json');

$request_id = (int)($_POST['request_id'] ?? 0);
$driver_id  = (int)($_POST['driver_id'] ?? 0);
$owner_id   = (int)($_SESSION['user_id'] ?? 0);

if(!$request_id || !$driver_id){
    echo json_encode(['success' => false, 'message' => 'Request ID and Driver ID are required.']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // 1) Verify this request is assigned to THIS owner
    $stmt = $conn->prepare("
        SELECT a.id, r.status 
        FROM assignments a 
        JOIN requests r ON r.id = a.request_id 
        WHERE a.request_id = ? 
          AND a.owner_id   = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $request_id, $owner_id);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if(!$assignment){
        echo json_encode(['success' => false, 'message' => 'Request not found or not assigned to you.']);
        $conn->rollback();
        exit;
    }

    // Only allow assigning if request is still pending
    if ($assignment['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Trip is already in progress or completed.']);
        $conn->rollback();
        exit;
    }

    // 2) Validate driver belongs to this owner
    $stmt = $conn->prepare("
        SELECT id, name 
        FROM users 
        WHERE id = ? 
          AND role = 'driver' 
          AND owner_id = ? 
          AND status = 'active'
    ");
    $stmt->bind_param('ii', $driver_id, $owner_id);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if(!$driver){
        echo json_encode(['success' => false, 'message' => 'Driver not found or not active in your fleet.']);
        $conn->rollback();
        exit;
    }

    // 3) Check if driver already has active trip (in_progress only)
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS active_trips 
        FROM assignments a 
        JOIN requests r ON r.id = a.request_id 
        WHERE a.driver_id = ? 
          AND r.status = 'in_progress'
    ");
    $stmt->bind_param('i', $driver_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if(!empty($result['active_trips']) && (int)$result['active_trips'] > 0){
        echo json_encode(['success' => false, 'message' => 'This driver already has an active trip.']);
        $conn->rollback();
        exit;
    }

    // 4) Update assignments table: set driver + timestamp
    $stmt = $conn->prepare("
        UPDATE assignments 
        SET driver_id = ?, driver_assigned_at = NOW() 
        WHERE request_id = ? AND owner_id = ?
    ");
    $stmt->bind_param('iii', $driver_id, $request_id, $owner_id);
    if(!$stmt->execute()){
        throw new Exception("Failed to update assignment");
    }
    $stmt->close();

    // 5) Update request status to 'in_progress' (valid enum)
    $stmt = $conn->prepare("UPDATE requests SET status = 'in_progress' WHERE id = ?");
    $stmt->bind_param('i', $request_id);
    if(!$stmt->execute()){
        throw new Exception("Failed to update request status");
    }
    $stmt->close();

    $conn->commit();

    // 6) Optional: file-based tracking
    updateAssignmentTracking($request_id, $driver_id, $driver['name'], $owner_id);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Driver ' . $driver['name'] . ' assigned successfully! Trip is now in progress.'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * File-based tracking 
 */
function updateAssignmentTracking($request_id, $driver_id, $driver_name, $owner_id) {
    $jsonFile = __DIR__ . '/../assets/location/driver-assignments.json';

    $assignmentData = [
        'request_id'  => $request_id,
        'driver_id'   => $driver_id,
        'driver_name' => $driver_name,
        'owner_id'    => $owner_id,
        'assigned_at' => date('Y-m-d H:i:s'),
        'status'      => 'in_progress'
    ];
    
    $existingData = [];
    if (file_exists($jsonFile)) {
        $existingData = json_decode(file_get_contents($jsonFile), true) ?? [];
    }

    // Remove existing assignment for this request
    $existingData = array_filter($existingData, function($item) use ($request_id) {
        return ($item['request_id'] ?? 0) !== $request_id;
    });

    $existingData[] = $assignmentData;
    file_put_contents($jsonFile, json_encode($existingData, JSON_PRETTY_PRINT));

    // Also update the main assignments file
    $mainFile = __DIR__ . '/../assets/location/assignments.json';
    $mainData = [];
    if (file_exists($mainFile)) {
        $mainData = json_decode(file_get_contents($mainFile), true) ?? [];
    }

    $mainData = array_filter($mainData, function($item) use ($request_id) {
        return ($item['request_id'] ?? 0) !== $request_id;
    });

    $mainData[] = $assignmentData;
    file_put_contents($mainFile, json_encode($mainData, JSON_PRETTY_PRINT));
}
