<?php
require_once __DIR__ . '/../includes/db.php';
@require_once __DIR__ . '/../includes/auth-check.php';
header('Content-Type: text/html; charset=utf-8');

$request_id = (int)($_POST['request_id'] ?? 0);
$status     = trim($_POST['status'] ?? '');

$allowedStatuses = ['pending','in_progress','completed'];
if (!$request_id || !in_array($status, $allowedStatuses, true)) {
    die('invalid');
}

// 1) Load request + driver info first
$stmt = $conn->prepare("
    SELECT r.*, a.driver_id
    FROM requests r
    LEFT JOIN assignments a ON a.request_id = r.id
    WHERE r.id = ?
");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    die('request_not_found');
}

$driverForRequest   = isset($row['driver_id']) ? (int)$row['driver_id'] : 0;
$residentForRequest = isset($row['resident_id']) ? (int)$row['resident_id'] : 0;

// 2) Extra safety: if trying to set to in_progress, make sure this driver has no other in_progress
if ($status === 'in_progress' && $driverForRequest) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM requests r2
        JOIN assignments a2 ON a2.request_id = r2.id
        WHERE a2.driver_id = ?
          AND r2.status = 'in_progress'
          AND r2.id <> ?
    ");
    $stmt->bind_param('ii', $driverForRequest, $request_id);
    $stmt->execute();
    $checkRes = $stmt->get_result();
    $checkRow = $checkRes->fetch_assoc();
    $stmt->close();

    if (!empty($checkRow['c']) && (int)$checkRow['c'] > 0) {
        // This driver already has an active trip – block this state change
        die('driver_already_has_active_trip');
    }
}

// 3) Update status safely with prepared statement
$stmt = $conn->prepare("UPDATE requests SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $request_id);
if (!$stmt->execute()) {
    $stmt->close();
    die('update_failed');
}
$stmt->close();

// 4) Log this status change to JSON file for history/analytics
//    (similar style to resident requests.json)
$actorId   = isset($currentUserId) ? (int)$currentUserId : null;
$actorRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// handle possible column names for coordinates (lat/lng vs latitude/longitude)
$lat = null;
$lng = null;
if (isset($row['lat'])) {
    $lat = (float)$row['lat'];
} elseif (isset($row['latitude'])) {
    $lat = (float)$row['latitude'];
}

if (isset($row['lng'])) {
    $lng = (float)$row['lng'];
} elseif (isset($row['longitude'])) {
    $lng = (float)$row['longitude'];
}

$logEntry = [
    'request_id'          => $request_id,
    'driver_id'           => $driverForRequest,
    'resident_id'         => $residentForRequest,
    'address'             => $row['address'] ?? '',
    'lat'                 => $lat,
    'lng'                 => $lng,
    'new_status'          => $status,
    'changed_by_user_id'  => $actorId,
    'changed_by_role'     => $actorRole,
    'changed_at'          => date('Y-m-d H:i:s'),
    'type'                => 'trip_status_change'
];

$logFile     = __DIR__ . '/../assets/location/trips.json'; // api -> (..) -> assets/location
$existingLog = [];

if (file_exists($logFile)) {
    $decoded = json_decode(file_get_contents($logFile), true);
    if (is_array($decoded)) {
        $existingLog = $decoded;
    }
}

$existingLog[] = $logEntry;
file_put_contents($logFile, json_encode($existingLog, JSON_PRETTY_PRINT));

// 5) Simple response (driver dashboard uses fetch but doesn't need JSON)
echo "Status updated to {$status}";
