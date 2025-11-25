<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/rgeocode-client.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Any logged in user can call
if (!isset($currentUserId) || !$currentUserId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$role   = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : '';

$validRoles = ['driver', 'resident'];

if (!$userId || !in_array($role, $validRoles, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid params']);
    exit;
}

// Lookup from live_locations
$stmt = $conn->prepare("
    SELECT lat, lng, updated_at
    FROM live_locations
    WHERE user_id = ? AND role = ?
    LIMIT 1
");
$stmt->bind_param('is', $userId, $role);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'No live location yet']);
    exit;
}

$lat = (float)$row['lat'];
$lng = (float)$row['lng'];

$label = null;

/**
 * 1) get ward + municipal FROM DATABASE
 
 */
$wardId = findWardByLatLng($conn, $lat, $lng);
if ($wardId) {
    $w = $conn->prepare("SELECT ward_name, municipal, region FROM wards WHERE id = ? LIMIT 1");
    if ($w) {
        $w->bind_param('i', $wardId);
        $w->execute();
        $wRes = $w->get_result();
        if ($wRes && $wRow = $wRes->fetch_assoc()) {
            
            $parts = [];
            if (!empty($wRow['ward_name'])) $parts[] = $wRow['ward_name'];
            if (!empty($wRow['municipal'])) $parts[] = $wRow['municipal'];
            if (!empty($wRow['region']))    $parts[] = $wRow['region'];

            if ($parts) {
                $label = implode(', ', $parts);
            }
        }
        $w->close();
    }
}

/** 2) IF NO WARD FOUND → FALL BACK TO rGeocode RAW LABEL */
if ($label === null) {
    $geo = rgeocode_lookup($lat, $lng);

    if (is_array($geo) && empty($geo['error'])) {
        $parts = [];
        if (!empty($geo['level4_name'])) $parts[] = $geo['level4_name']; // ward-like
        if (!empty($geo['level3_name'])) $parts[] = $geo['level3_name']; // municipal-like
        if (!empty($geo['level2_name'])) $parts[] = $geo['level2_name']; // region-like

        if ($parts) {
            $label = implode(', ', $parts);
        }
    }
}


if ($label === null) {
    $label = sprintf("GPS: %.5f, %.5f", $lat, $lng);
}

echo json_encode([
    'ok'       => true,
    'location' => [
        'lat' => $lat,
        'lng' => $lng,
    ],
    'location_label' => $label,
    'updated_at'     => $row['updated_at'],
]);
