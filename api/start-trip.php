<?php
// api/start-trip.php
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

// Make sure this request is assigned to this driver
$sql = "
    SELECT r.id, r.status
    FROM requests r
    JOIN assignments a ON a.request_id = r.id
    WHERE r.id = ? AND a.driver_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $requestId, $driverId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'This trip is not assigned to you.']);
    exit;
}

// Only allow starting if currently pending
if ($row['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Trip cannot be started from current status.']);
    exit;
}

// Update to in_progress (valid enum value)
$upd = $conn->prepare("UPDATE requests SET status = 'in_progress' WHERE id = ?");
$upd->bind_param('i', $requestId);
$ok = $upd->execute();
$upd->close();

if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Trip started. Drive safely!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update trip status.']);
}
