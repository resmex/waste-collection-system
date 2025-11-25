<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/rgeocode-client.php';


require_role(['driver']); 

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$driverId   = isset($currentUserId) ? (int)$currentUserId : 0;
$driverName = isset($currentUserName) ? $currentUserName : 'Driver';

$minRating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : 0;
if ($minRating < 0 || $minRating > 5) $minRating = 0;

$feedback = [];
if ($driverId) {
    $sql = "
        SELECT f.comments, f.rating, f.created_at,
               u.name AS resident_name, f.request_id
        FROM feedback f
        LEFT JOIN users u ON u.id = f.resident_id
        WHERE f.driver_id = {$driverId}
    ";
    if ($minRating > 0) {
        $sql .= " AND f.rating >= {$minRating}";
    }
    $sql .= " ORDER BY f.created_at DESC";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $feedback[] = $row;
        }
        $res->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feedback - Driver</title>
  <link rel="stylesheet" href="../assets/css/driver.css">
</head>
<body>
<div class="container">
  <div class="header">
    <div class="header-left">
      <div class="menu-icon" onclick="window.location.href='driver-dashboard.php'">
        <span></span><span></span><span></span>
      </div>
      <div class="welcome-text">Feedback — <strong><?= h($driverName) ?></strong></div>
    </div>
    <div class="header-right">
      <div class="notification-badge"><div class="notification-count"><?= count($feedback) ?></div></div>
      <div class="profile-pic"></div>
    </div>
  </div>

  <div class="main-content">
    <div class="left-column">
      <div class="card">
        <div class="card-title">Filter by rating</div>
        <div class="filter-buttons">
          <a href="?min_rating=0" class="trip-btn <?= $minRating===0?'start':'' ?>">All</a>
          <a href="?min_rating=3" class="trip-btn <?= $minRating===3?'start':'' ?>">3★+</a>
          <a href="?min_rating=4" class="trip-btn <?= $minRating===4?'start':'' ?>">4★+</a>
          <a href="?min_rating=5" class="trip-btn <?= $minRating===5?'completed':'' ?>">5★ only</a>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Feedback (<?= count($feedback) ?>)</div>
        <?php if(!$feedback): ?>
          <div class="empty">No feedback found for this filter.</div>
        <?php else: ?>
          <div class="scroll-container">
            <?php foreach($feedback as $f): ?>
              <div class="feedback-item">
                <div class="feedback-header">
                  <div class="feedback-avatar"></div>
                  <strong><?= h($f['resident_name'] ?: 'Resident') ?></strong>
                </div>
                <div class="feedback-text">
                  ★<?= (int)$f['rating'] ?> — <?= nl2br(h($f['comments'])) ?>
                </div>
                <div class="trip-time">
                  Trip #<?= (int)$f['request_id'] ?> • <?= h($f['created_at']) ?>
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
