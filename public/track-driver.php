<?php
// public/track-driver.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';

require_login(['resident', 'owner', 'councillor', 'municipal_admin']);

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
if (!$requestId) {
    http_response_code(400);
    echo "Missing request_id.";
    exit;
}

// Find assigned driver for this request
$sql = $conn->prepare("
    SELECT 
        a.driver_id,
        u.name  AS driver_name,
        u.phone AS driver_phone,
        r.status AS request_status
    FROM assignments a
    INNER JOIN requests r ON r.id = a.request_id
    INNER JOIN users u ON u.id = a.driver_id
    WHERE a.request_id = ?
    LIMIT 1
");
$sql->bind_param('i', $requestId);
$sql->execute();
$res  = $sql->get_result();
$info = $res->fetch_assoc();
$sql->close();

if (!$info || !$info['driver_id']) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Track Driver</title>
        <style>
            body {
                font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background: #f4f5f7;
                padding: 20px;
            }
            .card {
                max-width: 600px;
                margin: 40px auto;
                background: #fff;
                border-radius: 12px;
                padding: 24px;
                box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>No Driver Assigned Yet</h2>
            <p>This request (ID: <?php echo h($requestId); ?>) does not have a driver assigned yet.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$driverId      = (int)$info['driver_id'];
$driverName    = $info['driver_name'];
$driverPhone   = $info['driver_phone'];
$requestStatus = $info['request_status'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track Driver</title>

    <!-- Leaflet CSS -->
    <link
      rel="stylesheet"
      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin=""
    />

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f5f7;
        }
        .page {
            max-width: 1000px;
            margin: 20px auto;
            padding: 16px;
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            margin-bottom: 20px;
        }
        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 14px;
            color: #4b5563;
        }
        .meta-row span {
            background: #f3f4f6;
            padding: 6px 10px;
            border-radius: 999px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .status-pending    { background: #fef3c7; color: #92400e; }
        .status-in_progress{ background: #dbeafe; color: #1d4ed8; }
        .status-completed  { background: #dcfce7; color: #166534; }
        .status-cancelled  { background: #fee2e2; color: #b91c1c; }

        #map {
            width: 100%;
            height: 450px;
            border-radius: 16px;
            overflow: hidden;
        }

        .last-update {
            margin-top: 10px;
            font-size: 13px;
            color: #6b7280;
        }

        .location-label {
            margin-top: 6px;
            font-size: 13px;
            color: #374151;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h2>Track Driver</h2>
        <p>Request ID: <strong><?php echo h($requestId); ?></strong></p>

        <div class="meta-row">
            <span>Driver: <strong><?php echo h($driverName); ?></strong></span>
            <?php if ($driverPhone): ?>
                <span>Phone: <strong><?php echo h($driverPhone); ?></strong></span>
            <?php endif; ?>
            <span class="status-badge status-<?php echo h($requestStatus); ?>">
                <?php echo strtoupper(str_replace('_', ' ', $requestStatus)); ?>
            </span>
        </div>
    </div>

    <div class="card">
        <div id="map"></div>
        <div class="last-update">
            Last update: <span id="last-update-text">waiting for location…</span>
        </div>
        <div class="location-label">
            Driver area: <span id="location-label-text">—</span>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script
  src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
  integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
  crossorigin=""
></script>

<script>
    const driverId   = <?php echo json_encode($driverId); ?>;
    const role       = 'driver';
    // because this file is in /public, our API is in ../api
    const pollUrl    = '../api/get-live-location.php?user_id=' + driverId + '&role=' + role;

    let map, marker, hasInitialFix = false;

    function initMap() {
        // Rough centre near Dar es Salaam as a default
        map = L.map('map').setView([-6.8, 39.28], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
    }

    function updateMarker(lat, lng) {
        const latLng = [lat, lng];

        if (!marker) {
            marker = L.marker(latLng).addTo(map);
        } else {
            marker.setLatLng(latLng);
        }

        if (!hasInitialFix) {
            map.setView(latLng, 14);
            hasInitialFix = true;
        }
    }

    function setLastUpdateText(text) {
        document.getElementById('last-update-text').textContent = text;
    }

    function setLocationLabel(text) {
        document.getElementById('location-label-text').textContent = text;
    }

    async function pollLocation() {
        try {
            const resp = await fetch(pollUrl, { cache: 'no-store' });
            const data = await resp.json();

            if (!data.ok || !data.location) {
                setLastUpdateText(data.error || 'No location yet');
                setLocationLabel('—');
                return;
            }

            const { lat, lng, updated_at } = data.location;
            updateMarker(lat, lng);
            setLastUpdateText('Last seen at ' + updated_at);

            // human-readable area from rGeocode (if provided)
            if (data.location_label) {
                setLocationLabel(data.location_label);
            } else {
                setLocationLabel(lat.toFixed(5) + ', ' + lng.toFixed(5));
            }
        } catch (e) {
            setLastUpdateText('Error fetching location');
            console.error(e);
        }
    }

    initMap();
    pollLocation();
    setInterval(pollLocation, 5000); // every 5 seconds
</script>
</body>
</html>
