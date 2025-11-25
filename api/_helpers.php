<?php
function api_json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function api_respond($data = [], int $code = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_required(array $payload, array $keys) {
    foreach ($keys as $k) {
        if (!isset($payload[$k]) || $payload[$k] === '') {
            api_respond(['ok'=>false,'message'=>"Missing field: $k"], 422);
        }
    }
}

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Find ward by bounding box.
 * Uses: wards(id, min_lat, max_lat, min_lng, max_lng)
 */
function findWardByLatLng(mysqli $conn, float $lat, float $lng): ?int {
    // Find the nearest ward using simple distance on lat/lng
    $sql = "SELECT id
            FROM wards
            ORDER BY SQRT(POW(lat - ?, 2) + POW(lng - ?, 2)) ASC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("findWardByLatLng: SQL prepare failed: " . $conn->error);
        return null;
    }

    $stmt->bind_param('dd', $lat, $lng);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int)$row['id'] : null;
}


