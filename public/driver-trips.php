<?php
require_once __DIR__ . '/../includes/db.php';
@require_once __DIR__ . '/../includes/auth-check.php';
require_role(['driver']); 

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$driverId   = isset($currentUserId) ? (int)$currentUserId : 0;
$driverName = isset($currentUserName) ? $currentUserName : 'Driver';

$statusFilter = $_GET['status'] ?? 'all';
$allowedFilter = ['all','active','completed'];
if (!in_array($statusFilter, $allowedFilter, true)) {
    $statusFilter = 'all';
}

$whereStatus = '';
if ($statusFilter === 'active') {
    $whereStatus = "AND r.status IN ('pending','in_progress')";
} elseif ($statusFilter === 'completed') {
    $whereStatus = "AND r.status = 'completed'";
}

$trips = [];
if ($driverId) {
    $sql = "
        SELECT r.id, r.address, r.lat, r.lng, r.status, r.created_at
        FROM requests r
        JOIN assignments a ON a.request_id = r.id
        WHERE a.driver_id = {$driverId}
        {$whereStatus}
        ORDER BY r.created_at DESC
    ";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $trips[] = $row;
        }
        $res->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Trips - Driver</title>
  <link rel="stylesheet" href="../assets/css/driver.css">
</head>
<body>
<div class="container">
  <div class="header">
    <div class="header-left">
      <div class="menu-icon" onclick="window.location.href='driver-dashboard.php'">
        <span></span><span></span><span></span>
      </div>
      <div class="welcome-text">My Trips — <strong><?= h($driverName) ?></strong></div>
    </div>
    <div class="header-right">
      <div class="notification-badge"><div class="notification-count"><?= count($trips) ?></div></div>
      <div class="profile-pic"></div>
    </div>
  </div>

  <div class="main-content">
    <div class="left-column">
      <div class="card">
        <div class="card-title">Filter</div>
        <div class="filter-buttons">
          <a href="?status=all" class="trip-btn <?= $statusFilter==='all'?'start':'' ?>">All</a>
          <a href="?status=active" class="trip-btn <?= $statusFilter==='active'?'start':'' ?>">Active</a>
          <a href="?status=completed" class="trip-btn <?= $statusFilter==='completed'?'completed':'' ?>">Completed</a>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Trips (<?= count($trips) ?>)</div>
        <?php if(!$trips): ?>
          <div class="empty">No trips found for this filter.</div>
        <?php else: ?>
          <div class="scroll-container">
            <?php foreach($trips as $t): ?>
              <div class="trip-item">
                <div class="trip-info">
                  <div class="trip-avatar"></div>
                  <div class="trip-details">
                    <h4>#<?= (int)$t['id'] ?></h4>
                    <div class="trip-location"><?= h($t['address'] ?: '—') ?></div>
                    <div class="trip-status">
                      Status: <span class="status-<?= h($t['status']) ?>"><?= ucfirst($t['status']) ?></span>
                    </div>
                    <div class="trip-time"><?= h($t['created_at']) ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
