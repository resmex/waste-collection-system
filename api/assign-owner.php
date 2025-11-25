<?php
// api/assign-owner.php
require_once __DIR__ . '/../includes/db.php';
@require_once __DIR__ . '/../includes/auth-check.php';

header('Content-Type: application/json; charset=utf-8');

$request_id  = (int)($_POST['request_id'] ?? 0);
$owner_id    = (int)($_POST['owner_id'] ?? 0);
$assigned_by = isset($currentUserId) ? (int)$currentUserId : null;

if (!$request_id || !$owner_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing request or owner.'
    ]);
    exit;
}

// Insert or update assignment – requires UNIQUE KEY on assignments.request_id
$stmt = $conn->prepare("
    INSERT INTO assignments (request_id, owner_id, assigned_by)
    VALUES (?,?,?)
    ON DUPLICATE KEY UPDATE 
        owner_id    = VALUES(owner_id),
        assigned_by = VALUES(assigned_by)
");
$stmt->bind_param('iii', $request_id, $owner_id, $assigned_by);

if ($stmt->execute()) {
    // We KEEP requests.status as 'pending'
    echo json_encode([
        'success' => true,
        'message' => 'Owner assigned successfully.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database error while assigning owner.'
    ]);
}

$stmt->close();
