<?php
// includes/rgeocode-client.php

/**
 * Call local rGeocode API to reverse geocode a point.
 * Expects rGeocode server at:
 *   http://localhost:7000/reverse-geocode/?latitude=...&longitude=...
 *
 * @return array ['display_name'=>..., 'level4_name'=>..., 'level3_name'=>..., ...] or ['error'=>...]
 */
function rgeocode_lookup(float $lat, float $lng): array
{
    $baseUrl = 'http://localhost:7000/reverse-geocode/';
    $query   = http_build_query([
        'latitude'  => $lat,
        'longitude' => $lng
    ]);

    $url = $baseUrl . '?' . $query;

    $opts = [
        'http' => [
            'method'  => 'GET',
            'timeout' => 5,
            'header'  => "Accept: application/json\r\n"
        ]
    ];

    $context = stream_context_create($opts);

    try {
        $json = @file_get_contents($url, false, $context);
        if ($json === false) {
            error_log("rgeocode_lookup: HTTP request failed for URL: $url");
            return ['error' => 'http_failed'];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            error_log("rgeocode_lookup: JSON decode failed: $json");
            return ['error' => 'json_failed'];
        }

        return $data;
    } catch (Throwable $e) {
        error_log("rgeocode_lookup exception: " . $e->getMessage());
        return ['error' => 'exception'];
    }
}

/**
 * Fallback: nearest ward by lat/lng (using wards.lat/lng).
 */
function findWardByLatLng(mysqli $conn, float $lat, float $lng): ?int
{
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

/**
 * Sync ward info from rGeocode response into `wards` table.
 *
 * New columns in `wards`:
 *   level4_pcode, level3_pcode, level4_name_api, level3_name_api
 */
function syncWardFromGeocode(mysqli $conn, array $geo, float $lat, float $lng): ?int
{
    if (empty($geo['level4_name']) || empty($geo['level3_name'])) {
        error_log("syncWardFromGeocode: missing level4_name or level3_name");
        return null;
    }

    $wardNameApi  = trim($geo['level4_name']);   // e.g. Goba
    $muniApi      = trim($geo['level3_name']);   // e.g. Kinondoni (may be wrong)
    $regionApi    = !empty($geo['level2_name']) ? trim($geo['level2_name']) : 'Dar es Salaam';

    $pcode4       = $geo['level4_pcode'] ?? null;
    $pcode3       = $geo['level3_pcode'] ?? null;

    // Normalise for DB
    $wardName     = ucwords(strtolower($wardNameApi));
    $municipalApi = ucwords(strtolower($muniApi));
    $regionName   = ucwords(strtolower($regionApi));

    // 1) Try match by ward code (if we already saw it)
    if ($pcode4) {
        $sql = "SELECT id FROM wards WHERE level4_pcode = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $pcode4);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $stmt->close();
                $id = (int)$row['id'];
                error_log("syncWardFromGeocode: matched by level4_pcode=$pcode4 → id=$id");
                return $id;
            }
            $stmt->close();
        }
    }

    // 2) Try match by (municipal, ward_name) from your seed data
    $sql = "SELECT id FROM wards WHERE municipal = ? AND ward_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $municipalApi, $wardName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $stmt->close();
            $wardId = (int)$row['id'];
            error_log("syncWardFromGeocode: matched by municipal+ward → id=$wardId");

            // Update codes/API names if empty
            if ($pcode4 || $pcode3) {
                $up = $conn->prepare("
                    UPDATE wards 
                    SET level4_pcode    = COALESCE(level4_pcode, ?),
                        level3_pcode    = COALESCE(level3_pcode, ?),
                        level4_name_api = COALESCE(level4_name_api, ?),
                        level3_name_api = COALESCE(level3_name_api, ?)
                    WHERE id = ?
                ");
                if ($up) {
                    $up->bind_param('ssssi', $pcode4, $pcode3, $wardNameApi, $muniApi, $wardId);
                    $up->execute();
                    $up->close();
                }
            }

            return $wardId;
        }
        $stmt->close();
    }

    // 3) No match → create new ward from API
    $sql = "INSERT INTO wards (
                region, municipal, ward_name,
                lat, lng,
                level4_pcode, level3_pcode,
                level4_name_api, level3_name_api
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("syncWardFromGeocode insert prepare failed: " . $conn->error);
        return null;
    }

    $stmt->bind_param(
        'sssddssss',
        $regionName,
        $municipalApi,
        $wardName,
        $lat,
        $lng,
        $pcode4,
        $pcode3,
        $wardNameApi,
        $muniApi
    );

    if ($stmt->execute()) {
        $newId = (int)$stmt->insert_id;
        $stmt->close();
        error_log("syncWardFromGeocode: inserted new ward id=$newId ($wardName, $municipalApi)");
        return $newId;
    } else {
        error_log("syncWardFromGeocode insert failed: " . $stmt->error);
        $stmt->close();
        return null;
    }
}
