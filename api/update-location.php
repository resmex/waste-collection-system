<?php
// api/update-location.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Expect: lat, lng, role (driver|resident)
$lat  = isset($_POST['lat']) ? (float)$_POST['lat'] : 0.0;
$lng  = isset($_POST['lng']) ? (float)$_POST['lng'] : 0.0;
$role = isset($_POST['role']) ? strtolower(trim($_POST['role'])) : '';

$validRoles = ['driver', 'resident'];

if (!$lat || !$lng || !in_array($role, $validRoles, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// $currentUserId should be set by auth-check.php
if (!isset($currentUserId) || !$currentUserId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = (int)$currentUserId;

// Upsert into live_locations (unique key on user_id + role)
$stmt = $conn->prepare("
    INSERT INTO live_locations (user_id, role, lat, lng)
    VALUES (?,?,?,?)
    ON DUPLICATE KEY UPDATE
        lat = VALUES(lat),
        lng = VALUES(lng),
        updated_at = NOW()
");
$stmt->bind_param('isdd', $user_id, $role, $lat, $lng);
$stmt->execute();
$stmt->close();

// If this is a driver, also update driver_status table
if ($role === 'driver') {
    // Check if driver_status row exists
    $check = $conn->prepare("SELECT id FROM driver_status WHERE driver_id = ? LIMIT 1");
    $check->bind_param('i', $user_id);
    $check->execute();
    $res  = $check->get_result();
    $row  = $res->fetch_assoc();
    $check->close();

    if ($row) {
        // Update existing
        $id  = (int)$row['id'];
        $upd = $conn->prepare("
            UPDATE driver_status
            SET last_lat = ?, last_lng = ?, last_seen_at = NOW(), is_available = 1
            WHERE id = ?
        ");
        $upd->bind_param('ddi', $lat, $lng, $id);
        $upd->execute();
        $upd->close();
    } else {
        // Insert new
        $ins = $conn->prepare("
            INSERT INTO driver_status (driver_id, is_available, last_lat, last_lng, last_seen_at)
            VALUES (?, 1, ?, ?, NOW())
        ");
        $ins->bind_param('idd', $user_id, $lat, $lng);
        $ins->execute();
        $ins->close();
    }
}

echo json_encode(['ok' => true]);
