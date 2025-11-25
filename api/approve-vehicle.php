<?php
// api/approve-vehicle.php

// 1) Turn off HTML error output + buffer any accidental output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';

// ⚠️ If this line causes HTML redirect when not logged in, that redirect
// will be buffered and we will clear it before sending JSON.
require_login(['municipal_admin']);

function json_response($success, $message = '', $extra = []) {
    // Remove anything that was accidentally echoed before JSON
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => $success ? true : false,
        'message' => $message,
    ], $extra));
    exit;
}

// 2) Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method. Use POST.');
}

// 3) Read inputs
$vehicleId = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;
$action    = isset($_POST['action']) ? trim($_POST['action']) : '';
$reason    = isset($_POST['reason']) ? trim($_POST['reason']) : ''; // optional for reject

if (!$vehicleId || ($action !== 'approve' && $action !== 'reject')) {
    json_response(false, 'Missing or invalid parameters (vehicle_id / action).');
}

// 4) Make sure vehicle exists
$stmt = $conn->prepare("SELECT id, status FROM vehicles WHERE id = ?");
if (!$stmt) {
    json_response(false, 'Database error (prepare): ' . $conn->error);
}
$stmt->bind_param('i', $vehicleId);
$stmt->execute();
$res = $stmt->get_result();
$vehicle = $res->fetch_assoc();
$stmt->close();

if (!$vehicle) {
    json_response(false, 'Vehicle not found.');
}

// Optional: only allow from pending
/*
if ($vehicle['status'] !== 'pending') {
    json_response(false, 'Only pending vehicles can be updated.');
}
*/

// 5) Approve
if ($action === 'approve') {
    $stmt = $conn->prepare("
        UPDATE vehicles 
        SET status = 'approved', rejection_reason = NULL 
        WHERE id = ?
    ");
    if (!$stmt) {
        json_response(false, 'Database error (approve): ' . $conn->error);
    }
    $stmt->bind_param('i', $vehicleId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        json_response(false, 'Failed to approve vehicle.');
    }

    json_response(true, 'Vehicle approved successfully.', [
        'new_status' => 'approved',
        'vehicle_id' => $vehicleId,
    ]);
}

// 6) Reject
if ($action === 'reject') {
    $stmt = $conn->prepare("
        UPDATE vehicles 
        SET status = 'rejected', rejection_reason = ? 
        WHERE id = ?
    ");
    if (!$stmt) {
        json_response(false, 'Database error (reject): ' . $conn->error);
    }
    $stmt->bind_param('si', $reason, $vehicleId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        json_response(false, 'Failed to reject vehicle.');
    }

    json_response(true, 'Vehicle rejected successfully.', [
        'new_status' => 'rejected',
        'vehicle_id' => $vehicleId,
    ]);
}

// 7) Safety fallback
json_response(false, 'Unknown error.');
