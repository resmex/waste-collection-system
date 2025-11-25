<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/rgeocode-client.php';

require_role(['councillor']); 

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$councillorName = isset($currentUserName) ? $currentUserName : 'Councillor';
$wardId         = isset($currentWardId) ? (int)$currentWardId : 0;

$totalReq = 0; 
$completed = 0; 
$pending = 0;

if ($wardId){
    if($r = $conn->query("SELECT COUNT(*) c FROM requests WHERE ward_id=$wardId")){
        $totalReq = (int)$r->fetch_assoc()['c']; 
        $r->close(); 
    }
    if($r = $conn->query("SELECT COUNT(*) c FROM requests WHERE ward_id=$wardId AND status='completed'")){
        $completed = (int)$r->fetch_assoc()['c']; 
        $r->close(); 
    }
    if($r = $conn->query("SELECT COUNT(*) c FROM requests WHERE ward_id=$wardId AND status='pending'")){
        $pending = (int)$r->fetch_assoc()['c']; 
        $r->close(); 
    }
}

/** Requests in ward (latest 30) – with truck/owner/driver info + lat/lng **/
$requests = [];
if ($wardId){
    $sql = "
        SELECT 
            r.id,
            r.address,
            r.status,
            r.created_at,
            r.lat,
            r.lng,
            u.name          AS resident_name,
            a.owner_id,
            a.driver_id,
            o.name          AS owner_name,
            d.name          AS driver_name,
            v.plate_no      AS vehicle_plate
        FROM requests r 
        LEFT JOIN users u        ON u.id = r.resident_id
        LEFT JOIN assignments a  ON a.request_id = r.id
        LEFT JOIN users o        ON o.id = a.owner_id   -- truck owner
        LEFT JOIN users d        ON d.id = a.driver_id  -- driver
        LEFT JOIN vehicles v     ON v.id = a.vehicle_id
        WHERE r.ward_id = $wardId 
        ORDER BY r.created_at DESC 
        LIMIT 30
    ";
    if($res = $conn->query($sql)){
        while($row = $res->fetch_assoc()) $requests[] = $row; 
        $res->close(); 
    }
}

/** Truck owners serving this ward (based on assigned vehicles in this ward) **/
$owners = [];
if ($wardId){
    $sqlOwn = "
        SELECT 
            o.id,
            o.name, 
            COUNT(DISTINCT v.id) AS total_vehicles
        FROM users o
        JOIN vehicles v    ON v.owner_id = o.id
        JOIN assignments a ON a.vehicle_id = v.id
        JOIN requests r    ON r.id = a.request_id AND r.ward_id = $wardId
        WHERE o.role='owner'
        GROUP BY o.id, o.name
        ORDER BY o.name
    ";
    if ($res = $conn->query($sqlOwn)){
        while($row = $res->fetch_assoc()) $owners[] = $row; 
        $res->close(); 
    }
}

/** Feedback in ward **/
$feedback = [];
if ($wardId){
    $sql = "
        SELECT 
            f.rating, 
            f.comments, 
            u.name as resident_name,
            f.created_at
        FROM feedback f 
        JOIN requests r ON r.id=f.request_id
        LEFT JOIN users u ON u.id=f.resident_id
        WHERE r.ward_id=$wardId
        ORDER BY f.created_at DESC 
        LIMIT 20
    ";
    if($res = $conn->query($sql)){
        while($row = $res->fetch_assoc()) $feedback[] = $row; 
        $res->close(); 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Councillor Dashboard</title>
  <link rel="stylesheet" href="../assets/css/councillor.css">
</head>
<body>
<div class="container">
  <div class="header">
    <div class="header-left">
      <div class="menu-icon"><span></span><span></span><span></span></div>
      <div class="welcome-text">Welcome, <strong><?= h($councillorName) ?></strong></div>
    </div>
    <div class="header-right">
      <div class="notification-badge"></div>
      <div class="profile-pic"></div>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Request</div>
      <div class="stat-value"><?= (int)$totalReq ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Completed</div>
      <div class="stat-value green"><?= (int)$completed ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pending</div>
      <div class="stat-value yellow"><?= (int)$pending ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pickup history</div>
      <div class="stat-value">📋</div>
    </div>
  </div>

  <div class="main-content">
    <div class="left-column">
      <div class="card">
        <div class="card-title">Pickup requests</div>
        <div class="scroll-container">
          <?php if(!$requests): ?>
            <div class="empty">No requests in ward.</div>
          <?php else: foreach($requests as $r): 
            $cls = ($r['status']==='completed'
                    ? 'status-completed'
                    : ($r['status']==='in_progress'
                       ? 'status-progress'
                       : 'status-pending'));

            // Build truck / owner / driver summary
            $truckParts = [];
            if (!empty($r['vehicle_plate'])) {
              $truckParts[] = 'Truck: '.h($r['vehicle_plate']);
            }
            if (!empty($r['owner_name'])) {
              $truckParts[] = 'Owner: '.h($r['owner_name']);
            }
            if (!empty($r['driver_name'])) {
              $truckParts[] = 'Driver: '.h($r['driver_name']);
            }
            $truckLine = $truckParts ? implode(' • ', $truckParts) : 'No truck assigned yet';

            // rGeocode: area label from lat/lng
            $areaLabel = '';
            if (!empty($r['lat']) && !empty($r['lng'])) {
                $geo = rgeocode_lookup((float)$r['lat'], (float)$r['lng']);
                if (is_array($geo) && empty($geo['error'])) {
                    $parts = [];
                    if (!empty($geo['level4_name'])) $parts[] = $geo['level4_name'];
                    if (!empty($geo['level3_name'])) $parts[] = $geo['level3_name'];
                    if (!empty($geo['level2_name'])) $parts[] = $geo['level2_name'];
                    $areaLabel = $parts ? implode(', ', $parts) : '';
                }
            }
          ?>
            <div class="pickup-item">
              <div class="pickup-info">
                <div class="pickup-avatar"></div>
                <div class="pickup-details">
                  <h4><?= h($r['resident_name']?:'Resident') ?></h4>
                  <div class="pickup-location"><?= h($r['address']?:'—') ?></div>

                  <?php if ($areaLabel): ?>
                    <div class="pickup-location" style="font-size: 12px; color:#4b5563;">
                      📍 <?= h($areaLabel) ?>
                    </div>
                  <?php endif; ?>

                  <div class="pickup-truck"><?= h($truckLine) ?></div>
                  <div class="pickup-date"><?= h(date('Y-m-d H:i', strtotime($r['created_at']))) ?></div>
                </div>
              </div>
              <div class="status-badge <?= $cls ?>"><?= h($r['status']) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div class="right-column">
      <div class="card">
        <div class="card-title">Truck owners</div>
        <?php if(!$owners): ?>
          <div class="empty">No owners found.</div>
        <?php else: foreach($owners as $o): ?>
          <div class="truck-owner-item">
            <div class="owner-info">
              <div class="owner-avatar"></div>
              <div class="owner-details">
                <h4><?= h($o['name']?:('Owner #'.$o['id'])) ?></h4>
                <div class="owner-vehicles">
                  Total vehicles: <strong><?= (int)$o['total_vehicles'] ?></strong>
                </div>
              </div>
            </div>
            <a class="view-btn" href="../public/owner-dashboard.php?owner_id=<?= (int)$o['id'] ?>">View</a>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="card">
        <div class="card-title">Customer Feedback</div>
        <div class="scroll-container">
          <?php if(!$feedback): ?>
            <div class="empty">No feedback yet.</div>
          <?php else: foreach($feedback as $f): ?>
            <div class="feedback-item">
              <div class="feedback-header">
                <div class="feedback-avatar"></div>
                <div class="feedback-name"><?= h($f['resident_name']?:'Resident') ?></div>
              </div>
              <div class="feedback-text">
                ★<?= (int)$f['rating'] ?> — <?= nl2br(h($f['comments'])) ?>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/councillor.js"></script>
</body>
</html>
