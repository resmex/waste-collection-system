<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
require_login(['owner']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ownerId = (int)($_SESSION['user_id'] ?? 0);
$status  = isset($_GET['status']) ? trim($_GET['status']) : '';

// 1) Fetch drivers for dropdown
$drivers = [];
$drs = $conn->prepare("
    SELECT id, name, phone 
    FROM users 
    WHERE owner_id = ? 
      AND role = 'driver' 
      AND status = 'active' 
    ORDER BY name
");
$drs->bind_param('i', $ownerId);
$drs->execute();
$r = $drs->get_result();
while($row = $r->fetch_assoc()) {
    $drivers[] = $row;
}
$drs->close();

// 2) Fetch requests: ONLY those assigned to this owner
$sql = "
  SELECT 
    r.id,
    r.address,
    r.status,
    r.created_at,
    a.owner_id,
    a.driver_id,
    drv.name   AS driver_name,
    drv.phone  AS driver_phone,
    res.name   AS resident_name,
    v.plate_no AS vehicle_plate
  FROM requests r
  JOIN assignments a ON a.request_id = r.id
  LEFT JOIN users drv ON drv.id = a.driver_id
  LEFT JOIN users res ON res.id = r.resident_id
  LEFT JOIN vehicles v ON v.id  = a.vehicle_id
  WHERE a.owner_id = ?
";

$types = "i";
$args  = [$ownerId];

if ($status !== '') {
    $sql   .= " AND r.status = ? ";
    $types .= "s";
    $args[] = $status;
} else {
    $sql .= " AND r.status <> 'cancelled' ";
}

$sql .= " ORDER BY r.created_at DESC";

$rows = [];
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$args);
$stmt->execute();
$rs = $stmt->get_result();
while($row = $rs->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Trip Management - E-Waste</title>
  <link rel="stylesheet" href="../assets/css/owner.css">
</head>
<body>

<div class="page-container">
  <div class="page-header">
      <h1>Trip management</h1>
      <p class="subtitle">View all trips under your fleet.</p>
  </div>

  <div class="content-wrapper">
    <div class="main-content">
      <div class="left-column">
        <div class="card">
          <div class="section-header">
            <div class="card-title">All Trips</div>
            <?php if(count($rows) > 5): ?>
              <span class="total-count"><?= count($rows) ?> trips</span>
            <?php endif; ?>
          </div>

          <?php if(isset($_GET['assigned'])): ?>
            <div class="success-message">✅ Successfully assigned request to your fleet.</div>
          <?php endif; ?>

          <!-- Filter bar -->
          <div class="filter-section" style="margin-bottom: 20px;">
            <form method="get" class="filter-form" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
              <select name="status" class="form-control" style="min-width: 200px;">
                <option value="">All statuses (except cancelled)</option>
                <option value="pending"     <?= $status==='pending'?'selected':''; ?>>Pending</option>
                <option value="in_progress" <?= $status==='in_progress'?'selected':''; ?>>In progress</option>
                <option value="completed"   <?= $status==='completed'?'selected':''; ?>>Completed</option>
              </select>
              <button type="submit" class="btn btn-primary" style="width: auto; padding: 10px 20px;">Apply filter</button>
            </form>
          </div>

          <!-- Trips list -->
          <div class="scroll-container">
            <?php if(!$rows): ?>
              <div class="empty-state">
                <h3>No requests found</h3>
                <p>
                  <?= $status 
                      ? "There are no {$status} trips at the moment." 
                      : "No active trips assigned to your fleet right now." ?>
                </p>
              </div>
            <?php else: foreach($rows as $r): ?>
              <div class="trip-card">
                <div class="trip-header">
                  <div class="trip-info">
                    <div class="trip-id">Request #<?= (int)$r['id'] ?></div>
                    <?php if(!empty($r['resident_name'])): ?>
                      <div class="trip-customer">Customer: <?= h($r['resident_name']) ?></div>
                    <?php endif; ?>
                    <div class="trip-address"><?= h($r['address'] ?: 'Address not specified') ?></div>
                    <div class="trip-date">Created: <?= h(date('F j, Y g:i A', strtotime($r['created_at']))) ?></div>
                  </div>

                  <span class="status-badge <?= 'status-'.str_replace(' ','_', $r['status']) ?>">
                    <?= h(ucfirst(str_replace('_',' ', $r['status']))) ?>
                  </span>
                </div>

                <?php if(!empty($r['driver_name'])): ?>
                  <div class="driver-details" style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 12px 0;">
                    <div class="driver-name" style="font-weight: 600; margin-bottom: 4px;">Driver: <?= h($r['driver_name']) ?></div>
                    <?php if($r['driver_phone']): ?>
                      <div class="driver-phone" style="font-size: 13px; color: #666;">Phone: <?= h($r['driver_phone']) ?></div>
                    <?php endif; ?>
                    <?php if(!empty($r['vehicle_plate'])): ?>
                      <div class="vehicle-plate" style="font-size: 13px; color: #666;">Vehicle: <?= h($r['vehicle_plate']) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <?php if($r['status'] !== 'completed' && !empty($drivers)): ?>
                  <form method="post"
                        action="../api/assign-driver.php"
                        class="assign-form">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <select name="driver_id" class="form-control" required style="margin-bottom: 12px;">
                      <option value="">-- Select driver --</option>
                      <?php foreach($drivers as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= ((int)$r['driver_id'] === (int)$d['id']) ? 'selected' : '' ?>>
                          <?= h($d['name']) ?> (<?= h($d['phone'] ?: 'No phone') ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit">
                      <?= $r['driver_id'] ? '🔄 Reassign driver' : '👤 Assign driver' ?>
                    </button>
                  </form>
                <?php elseif($r['status'] !== 'completed' && empty($drivers)): ?>
                  <div class="no-drivers-warning">
                    No active drivers found in your fleet. Please add drivers first.
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const assignForms = document.querySelectorAll('.assign-form');

  assignForms.forEach(form => {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(this);
      const tripCard = this.closest('.trip-card');
      const button   = this.querySelector('button');

      if (button) {
        button.classList.add('btn-loading');
        button.disabled = true;
      }

      fetch(this.action, {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (button) {
          button.classList.remove('btn-loading');
          button.disabled = false;
        }

        if (data.success) {
          tripCard.style.transition = 'all 0.3s ease';
          tripCard.style.opacity = '0';
          tripCard.style.transform = 'translateX(-20px)';
          setTimeout(() => {
            tripCard.remove();
            const container = document.querySelector('.scroll-container');
            if (container && container.children.length === 0) {
              const empty = document.createElement('div');
              empty.className = 'empty-state';
              empty.innerHTML = '<h3>No requests found</h3><p>All assigned trips are currently handled.</p>';
              container.appendChild(empty);
            }
          }, 300);

          showToast(data.message || 'Driver assigned successfully.', 'success');
        } else {
          showToast(data.message || 'Failed to assign driver.', 'error');
        }
      })
      .catch(err => {
        console.error(err);
        if (button) {
          button.classList.remove('btn-loading');
          button.disabled = false;
        }
        showToast('Error assigning driver.', 'error');
      });
    });
  });

  function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.textContent = msg;
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.padding = '12px 18px';
    toast.style.borderRadius = '8px';
    toast.style.fontSize = '14px';
    toast.style.fontWeight = '600';
    toast.style.zIndex = '9999';
    toast.style.color = '#fff';
    toast.style.background = type === 'success' ? '#4caf50' : '#e53935';
    toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-5px)';
      setTimeout(() => toast.remove(), 300);
    }, 2500);
  }
});
</script>

</body>
</html>