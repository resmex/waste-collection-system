<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/rgeocode-client.php';

error_log("=== PICKUP REQUEST START ===");
error_log("POST data: " . print_r($_POST, true));

$resident_id = isset($currentUserId) ? (int)$currentUserId : 0;
$address     = trim($_POST['address'] ?? '');
$lat         = isset($_POST['lat']) ? (float)$_POST['lat'] : 0.0;
$lng         = isset($_POST['lng']) ? (float)$_POST['lng'] : 0.0;

error_log("Parsed - Resident: $resident_id, Lat: $lat, Lng: $lng, Address: $address");

// 1) Must be logged in
if (!$resident_id) {
    error_log("ERROR: User not logged in");
    header('Location: ../public/resident-dashboard.php?pickup_error=login_required');
    exit;
}

// 2) Validate coordinates
if (!$lat || !$lng || abs($lat) > 90 || abs($lng) > 180) {
    error_log("ERROR: Invalid coordinates - Lat: $lat, Lng: $lng");
    header('Location: ../public/resident-dashboard.php?pickup_error=missing_location');
    exit;
}

// 3) Get address + geocode info
error_log("Attempting reverse geocode...");
$geo = rgeocode_lookup($lat, $lng);

if ($address === '') {
    if (is_array($geo) && !empty($geo['display_name'])) {
        $address = $geo['display_name'];
    } else {
        $address = "GPS Location: $lat, $lng";
    }
}
error_log("Address used: $address");

// 4) Determine ward_id using syncWardFromGeocode
$ward_id = null;

if (is_array($geo) && empty($geo['error'])) {
    $ward_id = syncWardFromGeocode($conn, $geo, $lat, $lng);
    error_log("syncWardFromGeocode returned ward_id=" . ($ward_id ?: 'NULL'));
}

// Optional fallback by distance if still null
if (!$ward_id) {
    $ward_id = findWardByLatLng($conn, $lat, $lng);
    error_log("findWardByLatLng fallback ward_id=" . ($ward_id ?: 'NULL'));
}

if (!$ward_id) {
    error_log("ERROR: No ward found for coordinates");
    header('Location: ../public/resident-dashboard.php?pickup_error=ward_not_found');
    exit;
}

// Insert request
$sql = "INSERT INTO requests (resident_id, ward_id, address, lat, lng, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("ERROR: prepare failed: " . $conn->error);
    header('Location: ../public/resident-dashboard.php?pickup_error=db_error');
    exit;
}

$stmt->bind_param('iisdd', $resident_id, $ward_id, $address, $lat, $lng);

if ($stmt->execute()) {
    $request_id = $stmt->insert_id;
    error_log("SUCCESS: Request created with ID: $request_id (ward_id=$ward_id)");
    header('Location: ../public/resident-dashboard.php?pickup_success=1&request_id=' . $request_id);
    exit;
} else {
    error_log("ERROR: Database insert failed - " . $stmt->error);
    header('Location: ../public/resident-dashboard.php?pickup_error=db_error');
    exit;
}
