<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';


require_login(['owner']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ownerId   = (int)($_SESSION['user_id'] ?? 0);
$ownerName = isset($currentUserName) && $currentUserName ? $currentUserName : 'Owner';

$myDrivers = $myVehicles = $assignedTrips = $pendingCount = 0;
$ownerTrips = [];
$totalUnassignedTrips = 0;
$driverTrips = [];
$totalDriverTrips = 0;
$drivers = [];
$availableDrivers = [];

if ($ownerId) {
    /*Stats */
    // Drivers count
    if ($res = $conn->query("SELECT COUNT(*) c FROM users WHERE role='driver' AND owner_id={$ownerId}")) {
        $myDrivers = (int)$res->fetch_assoc()['c'];
        $res->close();
    }

    // Vehicles count
    if ($res = $conn->query("SELECT COUNT(*) c FROM vehicles WHERE owner_id={$ownerId}")) {
        $myVehicles = (int)$res->fetch_assoc()['c'];
        $res->close();
    }

    // Trips assigned to this owner 
    if ($res = $conn->query("SELECT COUNT(*) c FROM assignments WHERE owner_id={$ownerId}")) {
        $assignedTrips = (int)$res->fetch_assoc()['c'];
        $res->close();
    }

    // Pending trips 
    if ($res = $conn->query("
        SELECT COUNT(*) c
        FROM requests r
        JOIN assignments a ON a.request_id = r.id
        WHERE a.owner_id = {$ownerId}
          AND r.status = 'pending'
    ")) {
        $pendingCount = (int)$res->fetch_assoc()['c'];
        $res->close();
    }

    
    // total unassigned-to-driver count
    if ($res = $conn->query("
        SELECT COUNT(*) AS total
        FROM requests r
        JOIN assignments a ON a.request_id = r.id
        WHERE a.owner_id = {$ownerId}
          AND (a.driver_id IS NULL OR a.driver_id = 0)
    ")) {
        $totalUnassignedTrips = (int)$res->fetch_assoc()['total'];
        $res->close();
    }

    // only 2 items for dashboard
    $sql = "
        SELECT r.id, r.address, r.created_at, r.status,
               u.name  AS resident_name,
               u.phone AS resident_phone
        FROM requests r
        JOIN assignments a ON a.request_id = r.id
        LEFT JOIN users u ON u.id = r.resident_id
        WHERE a.owner_id = {$ownerId}
          AND (a.driver_id IS NULL OR a.driver_id = 0)
        ORDER BY r.created_at DESC
        LIMIT 2
    ";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) $ownerTrips[] = $row;
        $res->close();
    }

   
    // total driver trip count
    if ($res = $conn->query("
        SELECT COUNT(*) AS total
        FROM requests r
        JOIN assignments a ON a.request_id = r.id
        JOIN users u ON u.id = a.driver_id
        WHERE a.owner_id = {$ownerId}
    ")) {
        $totalDriverTrips = (int)$res->fetch_assoc()['total'];
        $res->close();
    }

    // last 2 driver trips
    $sql = "
        SELECT r.id, r.address, r.status, r.created_at,
               u.name  AS driver_name,
               u.phone AS driver_phone,
               res.name AS resident_name,
               v.plate_no AS vehicle_plate
        FROM requests r
        JOIN assignments a ON a.request_id = r.id
        JOIN users u       ON u.id = a.driver_id
        LEFT JOIN users res ON res.id = r.resident_id
        LEFT JOIN vehicles v ON v.id = a.vehicle_id
        WHERE a.owner_id = {$ownerId}
        ORDER BY r.created_at DESC
        LIMIT 2
    ";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) $driverTrips[] = $row;
        $res->close();
    }

    /* My drivers with stats */
    $sql = "
        SELECT u.id, u.name, u.phone,
          (SELECT COUNT(*)
             FROM assignments a
             JOIN requests r ON r.id = a.request_id
            WHERE a.driver_id = u.id) AS total_trips,
          (SELECT ROUND(AVG(rating), 1)
             FROM feedback f
            WHERE f.driver_id = u.id) AS avg_rating
        FROM users u
        WHERE u.role = 'driver'
          AND u.owner_id = {$ownerId}
        ORDER BY u.name
    ";
    if ($res = $conn->query($sql)) {
        while ($row = $res->fetch_assoc()) $drivers[] = $row;
        $res->close();
    }

    /** ===== Available drivers for dropdown ===== */
    if ($res = $conn->query("SELECT id, name, phone FROM users WHERE role='driver' AND owner_id={$ownerId} ORDER BY name")) {
        while ($row = $res->fetch_assoc()) $availableDrivers[] = $row;
        $res->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Truck Owner dashboard</title>
  <link rel="stylesheet" href="../assets/css/owner.css">
  <style>
   
   
  </style>
</head>
<body>
<!-- Side Menu -->
<div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>
<div class="side-menu" id="sideMenu">
    <div class="menu-header">
        <!-- <h3>Hi, <?= h($ownerName) ?></h3> -->
        <p>Truck owner dashboard</p>
    </div>
    <div class="menu-items">
        <!-- <div class="menu-item" onclick="window.location.href='owner-dashboard.php'">
            <div class="menu-item-icon">📊</div>
            <div class="menu-item-text">Dashboard</div>
        </div> -->
        <div class="menu-item" onclick="window.location.href='owner-requests.php'">
            <div class="menu-item-icon"></div>
            <div class="menu-item-text">Trip management</div>
        </div>
        <div class="menu-item" onclick="window.location.href='owner-drivers.php'">
            <div class="menu-item-icon"></div>
            <div class="menu-item-text">Driver management</div>
        </div>
        <div class="menu-item" onclick="window.location.href='owner-vehicles.php'">
            <div class="menu-item-icon"></div>
            <div class="menu-item-text">Vehicle management</div>
        </div>
        <div class="menu-item" onclick="window.location.href='owner-feedback.php'">
            <div class="menu-item-icon"></div>
            <div class="menu-item-text">Customer feedback</div>
        </div>
        <div class="menu-item" onclick="window.location.href='../auth/logout.php'">
            <div class="menu-item-icon"></div>
            <div class="menu-item-text">Logout</div>
        </div>
    </div>
</div>

<div class="container">
  <div class="header">
    <div class="header-left">
      <div class="menu-icon" onclick="toggleMenu()">
        <span></span><span></span><span></span>
      </div>
      <div class="welcome-text">Welcome, <strong><?= h($ownerName) ?></strong></div>
    </div>
    <div class="header-right">
      <div class="notification-badge"></div>
      <div class="profile-pic"></div>
    </div>
  </div>

  <!-- Stats and Actions (same as your original, using $myDrivers etc.) -->
  <div class="card">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">My drivers</div>
        <a class="stat-value orange" href="owner-drivers.php"><?= (int)$myDrivers ?></a>
      </div>
      <div class="stat-card">
        <div class="stat-label">My vehicles</div>
        <a class="stat-value orange" href="owner-vehicles.php"><?= (int)$myVehicles ?></a>
      </div>
      <div class="stat-card">
        <div class="stat-label">Assigned trips</div>
        <a class="stat-value orange" href="owner-requests.php"><?= (int)$assignedTrips ?></a>
      </div>
      <!-- <div class="stat-card">
        <div class="stat-label">Pending Trips</div>
        <a class="stat-value yellow" href="owner-requests.php?status=pending"><?= (int)$pendingCount ?></a>
      </div> -->
    </div>

    <div class="action-grid">
      <div class="action-card">
        <div class="action-label">Manage drivers</div>
        <a class="action-icon" href="owner-drivers.php">👥</a>
      </div>
      <div class="action-card">
        <div class="action-label">Register vehicle</div>
        <a class="action-icon" href="owner-vehicles.php">🚗</a>
      </div>
      <div class="action-card">
        <div class="action-label">View all trips</div>
        <a class="action-icon" href="owner-requests.php">📋</a>
      </div>
      
    </div>
  </div>

  <div class="main-content">
    <div class="left-column">
      <div class="card">
        <div class="section-header">
          <div class="card-title">Unassigned trips</div>
          <?php if ($totalUnassignedTrips > 2): ?>
            <a href="owner-requests.php?status=pending" class="see-all-link">
              See all (<?= $totalUnassignedTrips ?>) →
            </a>
          <?php endif; ?>
        </div>
        <div class="scroll-container">
          <?php if (!$ownerTrips): ?>
            <div class="empty-state">No unassigned trips</div>
          <?php else: foreach ($ownerTrips as $t): ?>
            <div class="trip-card">
              <div class="trip-header">
                <div class="trip-info">
                  <div class="trip-id">Request #<?= (int)$t['id'] ?></div>
                  <?php if($t['resident_name']): ?>
                  <div class="trip-customer">Customer: <?= h($t['resident_name']) ?></div>
                  <?php endif; ?>
                  <div class="trip-address"><?= h($t['address'] ?: 'Address not specified') ?></div>
                  <div class="trip-date">Created: <?= h(date('F j, Y g:i A', strtotime($t['created_at']))) ?></div>
                </div>
                <span class="status-badge status-pending">Pending assignment</span>
              </div>

              <?php if(!empty($availableDrivers)): ?>
              <form method="post" action="../api/assign-driver.php" class="assign-form">
                <input type="hidden" name="request_id" value="<?= (int)$t['id'] ?>">
                <select name="driver_id" class="form-control" required>
                  <option value="">-- Select Driver --</option>
                  <?php foreach($availableDrivers as $driver): ?>
                  <option value="<?= (int)$driver['id'] ?>">
                    <?= h($driver['name']) ?> (<?= h($driver['phone'] ?: 'No phone') ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Assign Driver</button>
              </form>
              <?php else: ?>
              <div style="padding: 12px; background: #fff5f5; border-radius: 6px; color: #742a2a; font-size: 13px;">
                No drivers available.
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="section-header">
          <div class="card-title">Driver Trips</div>
          <?php if ($totalDriverTrips > 2): ?>
            <a href="owner-requests.php" class="see-all-link">
              See All (<?= $totalDriverTrips ?>) →
            </a>
          <?php endif; ?>
        </div>
        <div class="scroll-container">
          <?php if (!$driverTrips): ?>
            <div class="empty-state">No driver trips yet</div>
          <?php else: foreach ($driverTrips as $t):
            $statusClass = 'status-' . $t['status'];
          ?>
            <div class="trip-card">
              <div class="trip-header">
                <div class="trip-info">
                  <div class="trip-id">Request #<?= (int)$t['id'] ?></div>
                  <div class="driver-details">
                    <div class="driver-name">Driver: <?= h($t['driver_name'] ?: 'Unknown Driver') ?></div>
                    <?php if($t['driver_phone']): ?>
                    <div class="driver-phone">Phone: <?= h($t['driver_phone']) ?></div>
                    <?php endif; ?>
                    <?php if($t['vehicle_plate']): ?>
                    <div class="vehicle-plate">Vehicle: <?= h($t['vehicle_plate']) ?></div>
                    <?php endif; ?>
                  </div>
                  <?php if($t['resident_name']): ?>
                  <div class="trip-customer">Customer: <?= h($t['resident_name']) ?></div>
                  <?php endif; ?>
                  <div class="trip-address"><?= h($t['address'] ?: 'Address not specified') ?></div>
                  <div class="trip-date">Created: <?= h(date('F j, Y g:i A', strtotime($t['created_at']))) ?></div>
                </div>
                <span class="status-badge <?= $statusClass ?>">
                  <?= h(ucfirst(str_replace('_', ' ', $t['status']))) ?>
                </span>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div class="right-column">
      <div class="card">
        <div class="card-title">My drivers</div>
        <?php if (!$drivers): ?>
          <div class="empty-state">No drivers found</div>
        <?php else: foreach ($drivers as $d): ?>
          <div class="driver-item">
            <div class="driver-avatar"></div>
            <div class="driver-details">
              <div class="driver-name"><?= h($d['name']) ?></div>
              <div class="driver-stats">
                Total trips: <strong><?= (int)$d['total_trips'] ?></strong> |
                Ratings: <strong><?= h($d['avg_rating'] ?: '—') ?></strong>
              </div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="card">
        <div class="card-title">Customer feedback</div>
        <div class="scroll-container">
          <?php
          $fbSql = "
            SELECT f.comments, f.rating, u.name AS resident_name, f.created_at
            FROM feedback f
            LEFT JOIN users u ON u.id = f.resident_id
            WHERE f.driver_id IN (SELECT id FROM users WHERE role='driver' AND owner_id={$ownerId})
            ORDER BY f.created_at DESC
            LIMIT 20";
          $fres = $conn->query($fbSql);
          if (!$fres || $fres->num_rows === 0): ?>
            <div class="empty-state">No feedback yet</div>
          <?php else: while ($f = $fres->fetch_assoc()): ?>
            <div class="feedback-item">
              <div class="feedback-header">
                <div class="feedback-avatar"></div>
                <div class="feedback-name"><?= h($f['resident_name'] ?: 'Resident') ?></div>
              </div>
              <div class="feedback-text">★<?= (int)$f['rating'] ?> — <?= nl2br(h($f['comments'])) ?></div>
            </div>
          <?php endwhile; $fres->close(); endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Menu toggle functions
function toggleMenu() {
    const menu = document.getElementById('sideMenu');
    const overlay = document.getElementById('menuOverlay');
    menu.classList.toggle('active');
    overlay.classList.toggle('active');
}

function closeMenu() {
    const menu = document.getElementById('sideMenu');
    const overlay = document.getElementById('menuOverlay');
    menu.classList.remove('active');
    overlay.classList.remove('active');
}

// Handle form submission and remove trip from dashboard after assignment
document.addEventListener('DOMContentLoaded', function() {
  const assignForms = document.querySelectorAll('.assign-form');
  
  assignForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      const tripCard = this.closest('.trip-card');
      const submitBtn = this.querySelector('.btn-primary');
      const originalText = submitBtn.textContent;
      
      // Show loading state
      submitBtn.disabled = true;
      submitBtn.textContent = 'Assigning...';
      submitBtn.style.opacity = '0.7';
      
      fetch(this.action, {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          // Success - remove trip card with animation
          tripCard.style.transition = 'all 0.5s ease';
          tripCard.style.opacity = '0';
          tripCard.style.transform = 'translateX(-100%)';
          tripCard.style.height = '0';
          tripCard.style.margin = '0';
          tripCard.style.padding = '0';
          tripCard.style.overflow = 'hidden';
          
          setTimeout(() => {
            tripCard.remove();
            showNotification(data.message, 'success');
            
            // Update the unassigned trips count
            updateUnassignedCount();
            
          }, 500);
        } else {
          // Error
          showNotification(data.message || 'Error assigning driver', 'error');
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
          submitBtn.style.opacity = '1';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showNotification('Network error. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        submitBtn.style.opacity = '1';
      });
    });
  });
  
  function updateUnassignedCount() {
    // Update the "See All" link count
    const seeAllLink = document.querySelector('.see-all-link');
    if (seeAllLink) {
      const currentText = seeAllLink.textContent;
      const match = currentText.match(/\((\d+)\)/);
      if (match) {
        const currentCount = parseInt(match[1]);
        const newCount = Math.max(0, currentCount - 1);
        seeAllLink.textContent = `See All (${newCount}) →`;
        
        // If no more trips, show empty state
        if (newCount === 0) {
          const unassignedSection = document.querySelector('.left-column .card:first-child .scroll-container');
          const existingEmptyState = unassignedSection.querySelector('.empty-state');
          if (!existingEmptyState) {
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state';
            emptyState.textContent = 'No unassigned trips available';
            unassignedSection.appendChild(emptyState);
          }
        }
      }
    }
  }
  
  function showNotification(message, type) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(notif => notif.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'custom-notification';
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      border-radius: 8px;
      color: white;
      font-weight: 600;
      z-index: 10000;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      ${type === 'success' ? 'background: #4caf50;' : 'background: #e53e3e;'}
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
      notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Remove notification after 4 seconds
    setTimeout(() => {
      notification.style.transform = 'translateX(100%)';
      setTimeout(() => {
        notification.remove();
      }, 300);
    }, 4000);
  }
});
</script>

</body>
</html>