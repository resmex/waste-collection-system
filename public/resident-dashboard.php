<?php
require_once __DIR__ . '/../includes/db.php';
@require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/rgeocode-client.php';

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$residentName   = isset($currentUserName) ? $currentUserName : 'Maria';
$latestRequest  = null;
$driver         = null;
$vehicle        = null;

// read result from api/request-pickup.php
$pickupSuccess = isset($_GET['pickup_success']) ? (int)$_GET['pickup_success'] : 0;
$pickupError   = $_GET['pickup_error'] ?? '';

/** Fetch latest request + driver (if assigned) for this resident **/
if (!empty($currentUserId)) {
    $sql = "SELECT 
                r.id AS request_id,
                r.address,
                r.status,
                r.created_at,
                r.lat  AS latitude,
                r.lng  AS longitude,
                r.ward_id,
                w.ward_name,
                w.municipal,
                a.driver_id,
                u.name  AS driver_name,
                u.phone AS driver_phone,
                u.email AS driver_email
            FROM requests r
            LEFT JOIN wards w      ON w.id = r.ward_id
            LEFT JOIN assignments a ON a.request_id = r.id
            LEFT JOIN users u       ON u.id = a.driver_id
            WHERE r.resident_id = ?
            ORDER BY r.created_at DESC
            LIMIT 1";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $currentUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $latestRequest = $row;

            if (!empty($row['driver_id'])) {
                $driver = [
                    'id'    => (int)$row['driver_id'],
                    'name'  => $row['driver_name'] ?: 'Assigned Driver',
                    'phone' => $row['driver_phone'] ?? '',
                    'email' => $row['driver_email'] ?? '',
                ];

                // Try get one vehicle for this driver (via owner)
                $vehSql = "SELECT v.plate_no, v.capacity_kg
                           FROM vehicles v
                           JOIN users du ON du.owner_id = v.owner_id
                           WHERE du.id = " . (int)$row['driver_id'] . "
                           ORDER BY v.created_at DESC
                           LIMIT 1";

                if ($vr = $conn->query($vehSql)) {
                    if ($v = $vr->fetch_assoc()) {
                        $vehicle = $v;
                    }
                    $vr->close();
                }
            }
        }
        $stmt->close();
    }
}

// Treat only pending/in_progress as ACTIVE
$activeStatuses = ['pending','in_progress'];
if ($latestRequest) {
    $s = strtolower((string)$latestRequest['status']);
    if (!in_array($s, $activeStatuses, true)) {
        $latestRequest = null;
        $driver        = null;
        $vehicle       = null;
    }
}

function status_class($status) {
    $s = strtolower(trim((string)$status));
    if (in_array($s, ['pending', 'completed', 'cancelled'], true)) {
        return $s;
    }
    if ($s === 'in_progress' || $s === 'on transit') {
        return 'assigned';
    }
    return '';
}

function status_label($status) {
    $s = strtolower(trim((string)$status));
    return match ($s) {
        'pending'       => 'Pending',
        'in_progress'   => 'On The Way',
        'completed'     => 'Completed',
        'cancelled'     => 'Cancelled',
        'no_active'     => 'No Active Request',
        default         => $status ?: 'No Active Request',
    };
}

// Build display vars
$driverName      = $driver ? $driver['name'] : 'Assigned Driver';
$vehicleLine     = $vehicle
    ? ('🚛 ' . h($vehicle['plate_no']))
    : '🚛 Waiting for assignment';

$rawStatus       = $latestRequest['status'] ?? 'no_active';
$driverStatus    = status_label($rawStatus);
$driverStatusCls = status_class($rawStatus);

// NO STATIC LOCATIONS - Only real GPS data
$pickupAddress   = $latestRequest['address']  ?? 'Your Current Location';
$fullAddress     = $latestRequest['address']  ?? 'Your Current Location';

// Ward / municipal label from DB
$wardName      = null;
$municipalName = null;
$areaLabel     = null;

if ($latestRequest) {
    $wardName      = $latestRequest['ward_name']   ?? null;
    $municipalName = $latestRequest['municipal']   ?? null;

    if ($wardName && $municipalName) {
        $areaLabel = $wardName . ', ' . $municipalName . ', Dar es Salaam';
    }
}

// used in JS
$jsAreaLabel = $areaLabel ? h($areaLabel) : '';

// NO STATIC COORDINATES - Will be set by live GPS
$pickupLat       = null;
$pickupLng       = null;
$requestId       = $latestRequest['request_id'] ?? 0;
$driverIdHidden  = $driver['id'] ?? 0;
$hasActiveRequest = (bool)$latestRequest;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident</title>
    <link rel="stylesheet" href="../assets/css/resident.css">

    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>

    <link rel="stylesheet"
          href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
</head>
<body>
<div class="container">
    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            <div class="menu-icon" style="display:none;">
                <span></span><span></span><span></span>
            </div>
            <div class="welcome-text">
                Hi, <strong><?= h($residentName) ?></strong>
            </div>
        </div>
        <div class="header-right">
            <div class="notification-badge"></div>
            <div class="profile-wrapper" onclick="toggleProfileMenu(event)">
                <div class="profile-pic"></div>
                <div class="profile-menu" id="profileMenu">
                    <button type="button">Settings</button>
                    <button type="button" onclick="window.location.href='../auth/logout.php'">Logout</button>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <!-- LIVE TRACKING CARD -->
        <div class="card map-card-full">
            <div class="map-header">
                <div class="card-title">Live Tracking</div>
                <div class="map-controls">
                    <button class="control-btn" type="button" onclick="centerOnMe()">📍 My Location</button>
                    <?php if ($driver): ?>
                        <button class="control-btn" type="button" onclick="centerOnDriver()">🚗 Driver Location</button>
                        <button class="control-btn" type="button" onclick="centerOnRoute()">👣 Show Route</button>
                    <?php endif; ?>
                    <button class="control-btn" type="button" id="trackBtn" onclick="toggleLiveTracking()">
                        <?= $driver ? '▶ Start Live Tracking' : '⏳ Wait for Driver' ?>
                    </button>
                </div>
            </div>

            <?php if ($areaLabel): ?>
                <div style="padding: 8px 16px; font-size: 13px; color:#4b5563;">
                    📍 Service area: <strong><?= h($areaLabel) ?></strong>
                </div>
            <?php endif; ?>

            <!-- Map -->
            <div id="map" class="full-map-container"></div>

            <!-- Real-time tracking stats -->
            <div class="tracking-info">
                <div class="tracking-stats">
                    <div class="tracking-stat">
                        <span class="tracking-label">Your Location</span>
                        <span class="tracking-value" id="yourLocationText">
                            📍 Acquiring your live location...
                        </span>
                    </div>
                    <!-- <div class="tracking-stat">
                        <span class="tracking-label">Driver Location</span>
                        <span class="tracking-value" id="driverLocationText">
                            <?= $driver ? '🚗 Getting driver location...' : '🚗 No driver assigned' ?>
                        </span>
                    </div> -->
                    <div class="tracking-stat">
                        <span class="tracking-label">Live Distance</span>
                        <span class="tracking-value" id="liveDistance">-- km</span>
                    </div>
                    <div class="tracking-stat">
                        <span class="tracking-label">Live ETA</span>
                        <span class="tracking-value" id="liveETA">-- min</span>
                    </div>
                </div>
            </div>

            <!-- Status + request + driver -->
            <div class="status-row">
                <!-- REQUEST BUTTON -->
                <div class="status-box">
                    <div class="status-title">Pickup Request</div>
                    <div class="status-value">
                        <form method="post"
                              action="../api/request-pickup.php"
                              class="inline-form"
                              id="pickupForm">
                            <input type="hidden" name="address" id="requestAddress" value="">
                            <!-- NO STATIC VALUES - Will be set by live GPS -->
                            <input type="hidden" name="lat" id="requestLat" value="">
                            <input type="hidden" name="lng" id="requestLng" value="">
                            <input type="hidden" name="resident_id" value="<?= (int)$currentUserId ?>">
                            <button class="button button-primary"
                                    type="submit"
                                    id="requestBtn"
                                <?= $hasActiveRequest ? 'disabled' : '' ?>>
                                <?= $hasActiveRequest ? '✅ Pickup Requested' : '🚛 REQUEST PICKUP' ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- STATUS -->
                <div class="status-box">
                    <div class="status-title">Request Status</div>
                    <div class="status-value">
                        <span class="status-badge <?= h($driverStatusCls) ?>" id="driverStatus">
                            <?= h($driverStatus) ?>
                        </span>
                    </div>
                </div>

                <!-- ASSIGNED DRIVER -->
                <div class="status-box">
                    <div class="status-title">Assigned Driver</div>
                    <div class="status-value">
                        <?php if ($driver): ?>
                            <button type="button"
                                    class="assigned-driver-btn"
                                    onclick="openDriverModal()">
                                👤 <?= h($driverName) ?> • View &amp; Rate
                            </button>
                        <?php else: ?>
                            <span class="pending-box">⏳ Not yet assigned</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($pickupSuccess): ?>
                <div class="success-message">
                    ✅ Pickup request submitted successfully! Driver will be assigned soon.
                </div>
            <?php elseif ($pickupError): ?>
                <div class="error-message">
                    <?php
                    switch ($pickupError) {
                        case 'login_required':
                            echo 'You must be logged in to request a pickup.';
                            break;
                        case 'missing_location':
                            echo 'We could not get your location. Please enable GPS and try again.';
                            break;
                        case 'ward_not_found':
                            echo 'We could not match your location to any ward. Please contact support.';
                            break;
                        case 'db_error':
                            echo 'Failed to submit pickup request. Please try again.';
                            break;
                        default:
                            echo 'Something went wrong. Please try again.';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DRIVER MODAL -->
<div class="driver-modal-overlay" id="driverModal" onclick="overlayClick(event)">
    <div class="driver-modal">
        <span class="driver-modal-close" onclick="closeDriverModal()">×</span>
        <div class="driver-modal-title">Your Driver</div>

        <?php if ($driver): ?>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Driver</span>
                    <span class="detail-value">👤 <?= h($driver['name']) ?></span>
                </div>

                <?php if (!empty($driver['phone'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Phone</span>
                        <span class="detail-value">📞 <?= h($driver['phone']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($vehicle): ?>
                    <div class="detail-item">
                        <span class="detail-label">Vehicle</span>
                        <span class="detail-value">🚛 <?= h($vehicle['plate_no']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p style="font-size:14px;color:#555;line-height:1.6;">
                No driver assigned yet for your latest request.
                Status: <strong><?= h($driverStatus) ?></strong>
            </p>
        <?php endif; ?>

        <!-- Rating -->
        <div class="rating-card">
            <div class="rating-label">Rate your driver</div>
            <div class="stars" id="starRating">
                <span class="star" data-rating="1">☆</span>
                <span class="star" data-rating="2">☆</span>
                <span class="star" data-rating="3">☆</span>
                <span class="star" data-rating="4">☆</span>
                <span class="star" data-rating="5">☆</span>
            </div>
            <input type="hidden" id="selectedRating" value="0">
        </div>

        <div class="feedback-label">Feedback</div>
        <textarea class="feedback-textarea" id="feedbackText"
                  placeholder="Share your experience with the service..."></textarea>

        <form method="post" action="../api/submit-feedback.php" id="feedbackForm">
            <input type="hidden" name="rating" id="formRating" value="0">
            <input type="hidden" name="request_id" value="<?= (int)$requestId ?>">
            <input type="hidden" name="driver_id" value="<?= (int)$driverIdHidden ?>">
            <input type="hidden" name="comments" id="formComments" value="">
            <button class="button button-primary" type="button" onclick="submitFeedback();">
                Submit Feedback
            </button>
        </form>
    </div>
</div>

<script>
    // ======== CONSTANTS ========
    const DRIVER_ID = <?= (int)$driverIdHidden ?>;
    const DRIVER_POLL_URL = DRIVER_ID
        ? '../api/get-live-location.php?user_id=' + DRIVER_ID + '&role=driver'
        : null;
    const UPDATE_URL = '../api/update-location.php';
    const SELF_POLL_URL = '../api/get-live-location.php?user_id=<?= (int)$currentUserId ?>&role=resident';
    const AREA_LABEL_FROM_DB = "<?= $jsAreaLabel ?>";

    // ======== MAP & LIVE LOCATIONS ========
    let map, userMarker, driverMarker, routeLine;
    let userLocation = null, driverLocation = null;
    let isLiveTracking = false;
    let trackingInterval = null;
    let hasInitialUserFix = false;
    let lastRouteRequest = 0;
    let locationWatchId = null;

    // Custom icons for better visualization
    const userIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    const driverIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    function initMap() {
        // Start with Tanzania view since we don't have static coordinates
        map = L.map('map', {
            scrollWheelZoom: true,
            zoomControl: true,
            dragging: true
        }).setView([-6.8160, 39.2800], 10); // Wider zoom to find user

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Start REAL live location tracking immediately
        startRealTimeLocationTracking();

        // Start polling for driver location if assigned
        if (DRIVER_ID && DRIVER_POLL_URL) {
            startDriverPolling();
        }

        // Poll for location label updates
        setInterval(updateLocationLabels, 8000);
    }

    // REAL-TIME LOCATION TRACKING - No static locations at all
    function startRealTimeLocationTracking() {
        if (!navigator.geolocation) {
            updateLocationText('yourLocationText', '📍 GPS not supported by browser');
            alert('GPS is not supported by your browser. Please use a different device.');
            return;
        }

        updateLocationText('yourLocationText', '📍 Acquiring your live location...');

        // Get immediate high-accuracy position
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                userLocation = [lat, lng];

                updateUserMarker(lat, lng);
                sendLocationToServer(lat, lng);
                updateFormCoordinates(lat, lng);
                updateLocationText('yourLocationText', '📍 Live: Getting address...');

                // Then start continuous watching
                startContinuousLocationWatching();
            },
            (error) => {
                handleLocationError(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    }

    function startContinuousLocationWatching() {
        locationWatchId = navigator.geolocation.watchPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const accuracy = pos.coords.accuracy;

                userLocation = [lat, lng];

                updateUserMarker(lat, lng);
                sendLocationToServer(lat, lng);
                updateFormCoordinates(lat, lng);

                // Update tracking info if driver is available
                if (driverLocation && isLiveTracking) {
                    updateLiveTrackingInfo();
                    updateLiveRoute();
                }

                const accuracyText = accuracy < 50 ? 'High accuracy' :
                                   accuracy < 100 ? 'Good accuracy' : 'Approximate location';
                updateLocationText('yourLocationText', `📍 Live: ${accuracyText}`);
            },
            (error) => {
                console.warn('Location watch error:', error.message);
                updateLocationText('yourLocationText', '📍 GPS signal weak - trying to reconnect...');
            },
            {
                enableHighAccuracy: true,
                maximumAge: 3000,
                timeout: 10000
            }
        );
    }

    function updateUserMarker(lat, lng) {
        const latLng = [lat, lng];

        if (!userMarker) {
            userMarker = L.marker(latLng, { icon: userIcon })
                .addTo(map)
                .bindPopup('Your Live Location<br><small>Updated: ' + new Date().toLocaleTimeString() + '</small>');

            // Center map on user's ACTUAL location when first acquired
            map.setView(latLng, 16);
            hasInitialUserFix = true;
            userMarker.openPopup();
        } else {
            userMarker.setLatLng(latLng);
            userMarker.setPopupContent('Your Live Location<br><small>Updated: ' + new Date().toLocaleTimeString() + '</small>');
        }

        const debugEl = document.getElementById('debugCoords');
        if (debugEl) {
            debugEl.innerHTML = `📍 Live GPS: ${lat.toFixed(6)}, ${lng.toFixed(6)}<br>` +
                               `Last update: ${new Date().toLocaleTimeString()}`;
        }
    }

    function updateFormCoordinates(lat, lng) {
        document.getElementById('requestLat').value = lat;
        document.getElementById('requestLng').value = lng;
        console.log('Form coordinates updated:', lat, lng);
    }

    function sendLocationToServer(lat, lng) {
        const formData = new FormData();
        formData.append('lat', lat);
        formData.append('lng', lng);
        formData.append('role', 'resident');

        fetch(UPDATE_URL, {
            method: 'POST',
            body: formData,
            cache: 'no-store'
        })
        .then(response => response.json())
        .then(data => {
            if (data.ok) {
                console.log('Live location sent successfully');
            }
        })
        .catch(e => console.error('Error sending location', e));
    }

    function handleLocationError(error) {
        let message = '📍 ';
        switch(error.code) {
            case error.PERMISSION_DENIED:
                message += 'Location access denied. Please enable GPS permissions.';
                alert('Please enable location access in your browser settings to request a pickup.');
                break;
            case error.POSITION_UNAVAILABLE:
                message += 'Location unavailable. Check your GPS connection.';
                break;
            case error.TIMEOUT:
                message += 'Location request timeout. Retrying...';
                setTimeout(startRealTimeLocationTracking, 3000);
                break;
            default:
                message += 'Location error. Retrying...';
                setTimeout(startRealTimeLocationTracking, 3000);
                break;
        }
        updateLocationText('yourLocationText', message);
    }

    // Reverse geocoding function (for display address only)
    async function getReverseGeocode(lat, lng) {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`,
                {
                    headers: { 'User-Agent': 'WasteManagementApp/1.0' }
                }
            );
            const data = await response.json();

            if (data.display_name) {
                return data.display_name;
            } else {
                return `GPS Location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            }
        } catch (error) {
            console.error('Geocoding error:', error);
            return `GPS Location: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
    }

    // Driver location polling
    function startDriverPolling() {
        if (!DRIVER_POLL_URL) return;
        fetchDriverLocation();
        setInterval(fetchDriverLocation, 4000);
    }

    async function fetchDriverLocation() {
        if (!DRIVER_POLL_URL) return;

        try {
            const resp = await fetch(DRIVER_POLL_URL, { cache: 'no-store' });
            const data = await resp.json();

            if (data.ok && data.location) {
                const { lat, lng } = data.location;
                driverLocation = [lat, lng];

                updateDriverMarker(lat, lng);

                if (data.location_label) {
                    updateLocationText('driverLocationText', '🚗 Live: ' + data.location_label);
                } else {
                    updateLocationText('driverLocationText', '🚗 Driver is moving...');
                }

                updateLiveTrackingInfo();

                if (isLiveTracking && userLocation) {
                    updateLiveRoute();
                }
            } else {
                updateLocationText('driverLocationText', '🚗 Waiting for driver location...');
            }
        } catch (e) {
            console.error('Error fetching driver location:', e);
            updateLocationText('driverLocationText', '🚗 Connection issue...');
        }
    }

    function updateDriverMarker(lat, lng) {
        const latLng = [lat, lng];

        if (!driverMarker) {
            driverMarker = L.marker(latLng, { icon: driverIcon })
                .addTo(map)
                .bindPopup('Driver Live Location<br><?= h($driverName) ?><br><small>Updated: ' + new Date().toLocaleTimeString() + '</small>');
        } else {
            driverMarker.setLatLng(latLng);
            driverMarker.setPopupContent('Driver Live Location<br><?= h($driverName) ?><br><small>Updated: ' + new Date().toLocaleTimeString() + '</small>');
        }
    }

    // REAL-TIME ROUTING WITH OSRM
    function updateLiveRoute() {
        if (!userLocation || !driverLocation) return;

        const now = Date.now();
        if (now - lastRouteRequest < 5000) return;
        lastRouteRequest = now;

        const url = `https://router.project-osrm.org/route/v1/driving/` +
            `${driverLocation[1]},${driverLocation[0]};` +
            `${userLocation[1]},${userLocation[0]}?overview=full&geometries=geojson`;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (!data.routes || !data.routes.length) {
                    console.log('No route found - using straight line');
                    drawStraightLine();
                    return;
                }

                const route = data.routes[0];
                const coords = route.geometry.coordinates.map(c => [c[1], c[0]]);

                if (routeLine) {
                    routeLine.setLatLngs(coords);
                } else {
                    routeLine = L.polyline(coords, {
                        color: '#4caf50',
                        weight: 6,
                        opacity: 0.8,
                        lineJoin: 'round'
                    }).addTo(map);
                }

                const distance = (route.distance / 1000).toFixed(1);
                const duration = Math.round(route.duration / 60);

                document.getElementById('liveDistance').textContent = distance + ' km';
                document.getElementById('liveETA').textContent = duration + ' min';

            })
            .catch(err => {
                console.error('Routing error, using straight line:', err);
                drawStraightLine();
            });
    }

    function drawStraightLine() {
        if (!userLocation || !driverLocation) return;

        const coords = [driverLocation, userLocation];

        if (routeLine) {
            routeLine.setLatLngs(coords);
        } else {
            routeLine = L.polyline(coords, {
                color: '#ff9800',
                weight: 4,
                opacity: 0.6,
                dashArray: '10, 10'
            }).addTo(map);
        }

        updateLiveTrackingInfo();
    }

    function updateLiveTrackingInfo() {
        if (userLocation && driverLocation) {
            const distance = calculateDistance(driverLocation, userLocation).toFixed(1);
            const time = Math.round((distance / 35) * 60);

            document.getElementById('liveDistance').textContent = distance + ' km';
            document.getElementById('liveETA').textContent = time + ' min';
        }
    }

    function calculateDistance(coord1, coord2) {
        const [lat1, lon1] = coord1;
        const [lat2, lon2] = coord2;
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) *
            Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    // ======== MAP CONTROLS ========
    function centerOnMe() {
        if (userMarker) {
            const userPos = userMarker.getLatLng();
            map.setView(userPos, 16, {
                animate: true,
                duration: 1.0
            });
            userMarker.openPopup();
            updateLocationText('yourLocationText', '📍 Centered on your live location');
        } else {
            alert('Your live location is being acquired. Please wait...');
        }
    }

    function centerOnDriver() {
        if (driverMarker) {
            const driverPos = driverMarker.getLatLng();
            map.setView(driverPos, 16, {
                animate: true,
                duration: 1.0
            });
            driverMarker.openPopup();
            updateLocationText('driverLocationText', '🚗 Centered on driver location');
        } else {
            alert('Driver location not available yet. Please wait...');
        }
    }

    function centerOnRoute() {
        if (userLocation && driverLocation) {
            const bounds = L.latLngBounds([userLocation, driverLocation]);
            map.fitBounds(bounds.pad(0.1), {
                animate: true,
                duration: 1.0
            });

            if (!routeLine && isLiveTracking) {
                updateLiveRoute();
            }

            updateLocationText('driverLocationText', '👣 Showing complete route');
        } else {
            alert('Need both your live location and driver location to show route');
        }
    }

    function toggleLiveTracking() {
        const trackBtn = document.getElementById('trackBtn');

        if (!DRIVER_ID) {
            alert('No driver assigned yet. Please wait for driver assignment.');
            return;
        }

        if (!userLocation) {
            alert('Please wait for your live location to be acquired.');
            return;
        }

        if (isLiveTracking) {
            isLiveTracking = false;
            trackBtn.textContent = '▶ Start Live Tracking';
            trackBtn.style.background = '#4caf50';

            if (routeLine) {
                map.removeLayer(routeLine);
                routeLine = null;
            }

            document.getElementById('liveDistance').textContent = '-- km';
            document.getElementById('liveETA').textContent = '-- min';

            updateLocationText('driverLocationText', '🚗 Live tracking stopped');

        } else {
            isLiveTracking = true;
            trackBtn.textContent = '⏹ Stop Live Tracking';
            trackBtn.style.background = '#e53935';

            centerOnRoute();

            if (driverLocation) {
                updateLiveRoute();
            }

            updateLocationText('driverLocationText', '🚗 Live tracking active');
        }
    }

    // ======== LOCATION LABELS ========
    async function updateLocationLabels() {
        // Prefer ward/municipal from DB
        if (AREA_LABEL_FROM_DB) {
            updateLocationText('yourLocationText', '📍 ' + AREA_LABEL_FROM_DB);
            return;
        }

        // Fallback to API label if DB unknown
        try {
            const resp = await fetch(SELF_POLL_URL, { cache: 'no-store' });
            const data = await resp.json();
            if (data.ok && data.location_label) {
                updateLocationText('yourLocationText', '📍 ' + data.location_label);
            }
        } catch (e) {
            console.error('Error updating location label', e);
        }
    }

    // Utility functions
    function updateLocationText(elementId, text) {
        const element = document.getElementById(elementId);
        if (element) element.textContent = text;
    }

    // ======== PROFILE MENU ========
    function toggleProfileMenu(e) {
        e.stopPropagation();
        const menu = document.getElementById('profileMenu');
        if (menu) menu.classList.toggle('active');
    }

    document.addEventListener('click', function () {
        const menu = document.getElementById('profileMenu');
        if (menu) menu.classList.remove('active');
    });

    // ======== DRIVER MODAL ========
    function openDriverModal() {
        const overlay = document.getElementById('driverModal');
        if (overlay) overlay.classList.add('active');
    }

    function closeDriverModal() {
        const overlay = document.getElementById('driverModal');
        if (overlay) overlay.classList.remove('active');
    }

    function overlayClick(e) {
        if (e.target.id === 'driverModal') {
            closeDriverModal();
        }
    }

    // ======== FORM SUBMISSION WITH LIVE COORDINATES ========
    document.addEventListener('DOMContentLoaded', function () {
        initMap();

        document.getElementById('pickupForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('requestBtn');

            if (!userLocation) {
                alert('⚠️ Please wait for your live GPS location to be detected before requesting pickup.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = '🔄 Requesting Pickup...';

            try {
                const currentLat = userLocation[0];
                const currentLng = userLocation[1];

                console.log('Submitting pickup with live coordinates:', currentLat, currentLng);

                document.getElementById('requestLat').value = currentLat;
                document.getElementById('requestLng').value = currentLng;

                const address = await getReverseGeocode(currentLat, currentLng);
                document.getElementById('requestAddress').value = address;

                console.log('Address:', address);

                this.submit();

            } catch (error) {
                console.error('Error preparing pickup request:', error);
                alert('❌ Error preparing your request. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = '🚛 REQUEST PICKUP';
            }
        });

        // Rating stars
        const stars = document.querySelectorAll('.star');
        const selectedRating = document.getElementById('selectedRating');
        const formRating = document.getElementById('formRating');

        stars.forEach(star => {
            star.addEventListener('click', function () {
                const rating = parseInt(this.getAttribute('data-rating'), 10);
                selectedRating.value = rating;
                formRating.value = rating;

                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.textContent = '★';
                        s.style.color = '#ffc107';
                    } else {
                        s.textContent = '☆';
                        s.style.color = '#ccc';
                    }
                });
            });

            star.addEventListener('mouseover', function () {
                const rating = parseInt(this.getAttribute('data-rating'), 10);
                stars.forEach((s, index) => {
                    s.style.color = index < rating ? '#ffc107' : '#ccc';
                });
            });

            star.addEventListener('mouseout', function () {
                const current = parseInt(selectedRating.value || '0', 10);
                stars.forEach((s, index) => {
                    if (index < current) {
                        s.textContent = '★';
                        s.style.color = '#ffc107';
                    } else {
                        s.textContent = '☆';
                        s.style.color = '#ccc';
                    }
                });
            });
        });
    });

    function submitFeedback() {
        const rating = document.getElementById('selectedRating').value;
        const comments = document.getElementById('feedbackText').value.trim();
        const formComments = document.getElementById('formComments');

        if (rating === '0') {
            alert('Please select a rating before submitting feedback.');
            return;
        }

        formComments.value = comments;
        document.getElementById('feedbackForm').submit();
    }
</script>
</body>
</html>
