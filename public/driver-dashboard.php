<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/rgeocode-client.php';

require_role(['driver']); 

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$driverId   = isset($currentUserId) ? (int)$currentUserId : 0;
$driverName = isset($currentUserName) ? $currentUserName : 'Driver';

/** Stats **/
$totalTrips = 0; 
$todayTrips = 0; 
$avgRating  = '—';

if ($driverId) {
    // total trips
    if ($res = $conn->query("
        SELECT COUNT(*) c 
        FROM requests r 
        JOIN assignments a ON a.request_id = r.id 
        WHERE a.driver_id = {$driverId}
    ")) {
        $totalTrips = (int)$res->fetch_assoc()['c']; 
        $res->close();
    }

    // today trips
    if ($res = $conn->query("
        SELECT COUNT(*) c 
        FROM requests r 
        JOIN assignments a ON a.request_id = r.id 
        WHERE a.driver_id = {$driverId} 
          AND DATE(r.created_at) = CURDATE()
    ")) {
        $todayTrips = (int)$res->fetch_assoc()['c']; 
        $res->close();
    }

    // average rating
    if ($res = $conn->query("
        SELECT ROUND(AVG(rating),1) avg_r 
        FROM feedback 
        WHERE driver_id = {$driverId}
    ")) {
        $avg = $res->fetch_assoc()['avg_r']; 
        $avgRating = $avg ? $avg : '—'; 
        $res->close();
    }
}

/** Active trips (pending / in_progress only) **/
$activeTrips = [];
$currentTrip = null;
$otherActive = [];

if ($driverId) {
    $sql = "
        SELECT 
            r.id,
            r.resident_id,
            r.address,
            r.lat      AS lat,
            r.lng      AS lng,
            r.status,
            r.created_at,
            u.name     AS resident_name,
            u.phone    AS resident_phone,
            u.email    AS resident_email
        FROM requests r
        JOIN assignments a ON a.request_id = r.id
        LEFT JOIN users u ON u.id = r.resident_id
        WHERE a.driver_id = {$driverId} 
          AND r.status IN ('pending','in_progress')
        ORDER BY r.created_at DESC
    ";

    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $activeTrips[] = $row;
        }
        $res->close();
    }

    // choose current trip: first in_progress, else first pending
    foreach ($activeTrips as $row) {
        if ($row['status'] === 'in_progress' && !$currentTrip) {
            $currentTrip = $row;
        } else {
            $otherActive[] = $row;
        }
    }
    if (!$currentTrip && $otherActive) {
        $currentTrip = array_shift($otherActive);
    }
}

/**
 * Build a nice pickup label:
 * 1) use requests.address if not empty
 * 2) else use rgeocode
 */
$pickupLabel = 'Pickup location';
if ($currentTrip) {
    if (!empty($currentTrip['address'])) {
        $pickupLabel = $currentTrip['address'];
    } elseif ($currentTrip['lat'] !== null && $currentTrip['lng'] !== null) {
        $geo = rgeocode_lookup((float)$currentTrip['lat'], (float)$currentTrip['lng']);
        if (is_array($geo) && empty($geo['error'])) {
            $parts = [];
            if (!empty($geo['level4_name'])) $parts[] = $geo['level4_name'];
            if (!empty($geo['level3_name'])) $parts[] = $geo['level3_name'];
            if (!empty($geo['level2_name'])) $parts[] = $geo['level2_name'];
            if ($parts) {
                $pickupLabel = implode(', ', $parts);
            }
        }
    }
}

// prepare current trip + resident id for JS
$currentTripJs = $currentTrip ? [
    'id'            => (int)$currentTrip['id'],
    'resident_id'   => (int)$currentTrip['resident_id'],
    'lat'           => $currentTrip['lat'] !== null ? (float)$currentTrip['lat'] : null,
    'lng'           => $currentTrip['lng'] !== null ? (float)$currentTrip['lng'] : null,
    'address'       => $currentTrip['address'],
    'display_label' => $pickupLabel,
    'status'        => $currentTrip['status'],
] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Dashboard</title>

  <link rel="stylesheet" href="../assets/css/driver.css">

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin="">
  </script>

  <style>
    /* Status pills */
    .trip-status .status-pending,
    .trip-status .status-in_progress,
    .trip-status .status-completed {
        display: inline-flex;
        align-items: center;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        text-transform: capitalize;
        letter-spacing: 0.02em;
    }
    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .status-in_progress {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .status-completed {
        background: #dcfce7;
        color: #166534;
    }
  </style>
</head>
<body>

<div class="container">
  <!-- HEADER -->
  <div class="header">
    <div class="header-left">
      <div class="welcome-text">Hi, <strong><?= h($driverName) ?></strong></div>
    </div>
    <div class="header-right">
      <div class="notification-badge">
        <div class="notification-count"><?= max(0, count($activeTrips)) ?></div>
      </div>
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
    <div class="left-column">
      <!-- STATS BAR -->
      <div class="stats-grid">
        <a href="driver-trips.php" class="stat-card stat-card-link">
          <div class="stat-label">Total Trips</div>
          <div class="stat-value"><?= (int)$totalTrips ?></div>
        </a>

        <div class="stat-card">
          <div class="stat-label">Today</div>
          <div class="stat-value green"><?= (int)$todayTrips ?></div>
        </div>

        <a href="driver-feedback.php" class="stat-card stat-card-link">
          <div class="stat-label">Average rating</div>
          <div class="stat-value yellow"><?= h($avgRating) ?></div>
        </a>
      </div>

      <!-- LIVE MAP CARD -->
      <div class="card map-card-full">
        <div class="map-header">
          <div class="card-title">Navigation Map</div>
          <div class="map-controls">
              <button class="control-btn" type="button" onclick="centerOnMe()">📍 My Location</button>
              <?php if ($currentTripJs): ?>
                  <button class="control-btn" type="button" onclick="centerOnPickup()">📌 Pickup Point</button>
                  <button class="control-btn" type="button" onclick="centerOnRoute()">👣 Track Route</button>
              <?php endif; ?>
          </div>
        </div>
        <div id="driverMap" class="full-map-container"></div>

        <!-- Location info -->
        <div class="location-info">
            <span class="location-icon">📍</span>
            <div class="location-text">
                <div class="location-name" id="mapAddress">
                    <?php if($currentTrip): ?>
                        <?= h($pickupLabel) ?>
                    <?php else: ?>
                        No active pickup yet
                    <?php endif; ?>
                </div>
                <div class="location-distance" id="mapStatusText">
                    <?php if($currentTrip): ?>
                        Follow navigation and keep GPS on while driving.
                    <?php else: ?>
                        Waiting for trip assignment.
                    <?php endif; ?>
                </div>
            </div>
        </div>
      </div>

      <!-- CURRENT TRIP -->
      <?php if($currentTrip): ?>
      <div class="card">
        <div class="card-title">Current Trip</div>
        <div class="trip-item">
          <div class="trip-info">
            <div class="trip-avatar"></div>
            <div class="trip-details">
              <h4>#<?= (int)$currentTrip['id'] ?></h4>
              <div class="trip-location"><?= h($pickupLabel) ?></div>
              <div class="trip-status">
                Status:
                <span class="status-<?= h($currentTrip['status']) ?>">
                  <?= ucfirst(str_replace('_',' ', $currentTrip['status'])) ?>
                </span>
              </div>
              <div class="trip-meta">
                  <small>Requested: <?= h(date('M j, Y g:i A', strtotime($currentTrip['created_at']))) ?></small>
              </div>
            </div>
          </div>
          <div class="trip-actions">
            <?php if($currentTrip['status'] === 'pending'): ?>
              <button class="trip-btn start" type="button" onclick="startTrip()">
                Start Trip
              </button>
            <?php elseif($currentTrip['status'] === 'in_progress'): ?>
              <button class="trip-btn completed" type="button" onclick="completeTrip()">
                Complete Trip
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Resident Details</div>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Resident</span>
                <span class="detail-value">
                    <?= h($currentTrip['resident_name'] ?: 'Unknown') ?>
                </span>
            </div>

            <?php if(!empty($currentTrip['resident_phone'])): ?>
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value">
                    <?= h($currentTrip['resident_phone']) ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if(!empty($currentTrip['resident_email'])): ?>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value">
                    <?= h($currentTrip['resident_email']) ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="detail-item" style="grid-column: 1 / -1;">
                <span class="detail-label">Pickup Address</span>
                <span class="detail-value">
                    <?= h($pickupLabel) ?>
                </span>
            </div>
        </div>
      </div>
      <?php else: ?>
      <div class="card">
        <div class="card-title">No Active Trip</div>
        <p style="font-size:14px;color:#555;line-height:1.6;">
            No active pickup.
        </p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  const currentTripJs = <?= json_encode($currentTripJs ?? null, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
  const UPDATE_URL   = '../api/update-location.php';

  const RESIDENT_ID  = currentTripJs ? currentTripJs.resident_id : 0;
  const RESIDENT_POLL_URL = RESIDENT_ID
      ? '../api/get-live-location.php?user_id=' + RESIDENT_ID + '&role=resident'
      : null;

  let map, driverMarker, pickupMarker, residentMarker, routeLine;
  let pickupLatLng = null;
  let residentLatLng = null;
  let hasInitialFix = false;
  let lastRouteRequest = 0;

  function initMap() {
      map = L.map('driverMap', {
          scrollWheelZoom: false,
          zoomControl: true,
          dragging: true
      }).setView([-6.8, 39.28], 12);

      map.scrollWheelZoom.disable();

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      if (currentTripJs && currentTripJs.lat && currentTripJs.lng) {
          pickupLatLng = L.latLng(currentTripJs.lat, currentTripJs.lng);
          const label = currentTripJs.display_label || currentTripJs.address || 'Resident location';
          pickupMarker = L.marker(pickupLatLng).addTo(map)
              .bindPopup('Pickup (initial request): ' + label);
          map.setView(pickupLatLng, 14);
      }

      if (RESIDENT_POLL_URL) {
          setTimeout(fetchResidentLocation, 2000);
      }
  }

  function updateDriverMarker(lat, lng) {
      const latLng = [lat, lng];

      if (!driverMarker) {
          driverMarker = L.marker(latLng).addTo(map).bindPopup('You (Driver)');
      } else {
          driverMarker.setLatLng(latLng);
      }

      if (!hasInitialFix) {
          map.setView(latLng, 14);
          hasInitialFix = true;
      }
  }

  function centerOnMe() {
      if (driverMarker) {
          map.setView(driverMarker.getLatLng(), 15);
      }
  }

  function centerOnPickup() {
      if (residentLatLng) {
          map.setView(residentLatLng, 15);
          if (residentMarker) residentMarker.openPopup();
      } else if (pickupLatLng) {
          map.setView(pickupLatLng, 15);
          if (pickupMarker) pickupMarker.openPopup();
      }
  }

  // OSRM route
  function drawRoute(fromLatLng, toLatLng) {
      const now = Date.now();
      if (now - lastRouteRequest < 8000) {
          return;
      }
      lastRouteRequest = now;

      const url = 'https://router.project-osrm.org/route/v1/driving/' +
          fromLatLng.lng + ',' + fromLatLng.lat + ';' +
          toLatLng.lng   + ',' + toLatLng.lat +
          '?overview=full&geometries=geojson';

      fetch(url)
          .then(r => r.json())
          .then(data => {
              if (!data.routes || !data.routes.length) return;

              const coords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);

              if (routeLine) {
                  routeLine.setLatLngs(coords);
              } else {
                  routeLine = L.polyline(coords, { weight: 5, opacity: 0.9 }).addTo(map);
              }

              map.fitBounds(routeLine.getBounds().pad(0.2));
          })
          .catch(err => {
              console.error('Routing error', err);
          });
  }

  function centerOnRoute() {
      if (driverMarker && (residentLatLng || pickupLatLng)) {
          const driverPos = driverMarker.getLatLng();
          const target    = residentLatLng || pickupLatLng;
          drawRoute(driverPos, target);
      }
  }

  // Driver live GPS → backend
  function startLocationUpdates() {
      if (!navigator.geolocation) {
          console.warn('Geolocation is not supported by this browser.');
          return;
      }

      navigator.geolocation.watchPosition(
          (pos) => {
              const lat = pos.coords.latitude;
              const lng = pos.coords.longitude;

              updateDriverMarker(lat, lng);

              const target = residentLatLng || pickupLatLng;
              if (target && currentTripJs && currentTripJs.status === 'in_progress') {
                  const driverPos = L.latLng(lat, lng);
                  drawRoute(driverPos, target);
              }

              const formData = new FormData();
              formData.append('lat', lat);
              formData.append('lng', lng);
              formData.append('role', 'driver');

              fetch(UPDATE_URL, {
                  method: 'POST',
                  body: formData,
                  cache: 'no-store'
              }).catch(e => console.error('Error sending location', e));
          },
          (err) => {
              console.warn('Geolocation error:', err.message);
          },
          {
              enableHighAccuracy: true,
              maximumAge: 10000,
              timeout: 15000
          }
      );
  }

  async function fetchResidentLocation() {
      if (!RESIDENT_POLL_URL) return;

      try {
          const resp = await fetch(RESIDENT_POLL_URL, { cache: 'no-store' });
          const data = await resp.json();

          if (!data.ok || !data.location) {
              console.log('No resident live location yet:', data.error || '');
              return;
          }

          const { lat, lng } = data.location;
          residentLatLng = L.latLng(lat, lng);

          if (residentMarker) {
              residentMarker.setLatLng(residentLatLng);
          } else {
              residentMarker = L.marker(residentLatLng, {
                  icon: L.divIcon({
                      className: 'user-marker',
                      html: '👤',
                      iconSize: [30, 30]
                  })
              }).addTo(map).bindPopup('Resident (Live)');
          }

          if (data.location_label) {
              const el = document.getElementById('mapAddress');
              if (el) el.textContent = data.location_label;
          }

          if (driverMarker && currentTripJs && currentTripJs.status === 'in_progress') {
              drawRoute(driverMarker.getLatLng(), residentLatLng);
          }
      } catch (e) {
          console.error('Error fetching resident location:', e);
      }
  }

  // SIMPLE POLL LOOP FOR RESIDENT LIVE POS
  let residentPollInterval = null;

  function startResidentPolling() {
      if (!RESIDENT_POLL_URL) return;
      if (residentPollInterval) return;

      residentPollInterval = setInterval(fetchResidentLocation, 5000);
  }

  function startTrip() {
      if (!currentTripJs) return;

      const formData = new FormData();
      formData.append('request_id', currentTripJs.id);

      fetch('../api/start-trip.php', {
          method: 'POST',
          body: formData
      })
      .then(res => res.json())
      .then(data => {
          alert(data.message);
          if (data.success) {
              location.reload();
          }
      })
      .catch(err => {
          console.error(err);
          alert('Error starting trip');
      });
  }

  function completeTrip() {
      if (!currentTripJs) return;

      if (!confirm('Mark this trip as completed?')) {
          return;
      }

      const formData = new FormData();
      formData.append('request_id', currentTripJs.id);

      fetch('../api/complete-trip.php', {
          method: 'POST',
          body: formData
      })
      .then(res => res.json())
      .then(data => {
          alert(data.message);
          if (data.success) {
              location.reload();
          }
      })
      .catch(err => {
          console.error(err);
          alert('Error completing trip');
      });
  }

  function toggleProfileMenu(e) {
      e.stopPropagation();
      const menu = document.getElementById('profileMenu');
      if (menu) {
          menu.classList.toggle('active');
      }
  }

  document.addEventListener('click', function() {
      const menu = document.getElementById('profileMenu');
      if (menu) menu.classList.remove('active');
  });

  document.addEventListener('DOMContentLoaded', () => {
      initMap();
      startLocationUpdates();
      startResidentPolling();
  });
</script>
</body>
</html>
