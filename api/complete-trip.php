<?php
// api/driver-complete-trip.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_role(['driver']);

header('Content-Type: application/json');

$driverId  = (int)($_SESSION['user_id'] ?? 0);
$requestId = (int)($_POST['request_id'] ?? 0);

if (!$driverId || !$requestId) {
    echo json_encode(['success' => false, 'message' => 'Missing driver or request.']);
    exit;
}

// Check trip belongs to this driver + is in_progress
$sql = "
    SELECT r.id, r.status
    FROM requests r
    JOIN assignments a ON a.request_id = r.id
    WHERE r.id = ? 
      AND a.driver_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $requestId, $driverId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Trip not found or not assigned to you.']);
    exit;
}

if ($row['status'] !== 'in_progress') {
    echo json_encode(['success' => false, 'message' => 'You can only complete a trip that is in progress.']);
    exit;
}

// Set to completed
$upd = $conn->prepare("UPDATE requests SET status = 'completed' WHERE id = ?");
$upd->bind_param('i', $requestId);
$ok = $upd->execute();
$upd->close();

if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Trip marked as completed.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to complete trip.']);
}
