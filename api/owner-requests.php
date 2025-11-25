<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
require_login(['owner']);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ownerId = (int)($_SESSION['user_id'] ?? 0);
$status = isset($_GET['status'])? trim($_GET['status']) : '';

$q="SELECT r.id, r.address, r.status, r.created_at
    FROM requests r JOIN assignments a ON a.request_id=r.id
    WHERE a.owner_id={$ownerId}";
if($status!=='') $q.=" AND r.status='".$conn->real_escape_string($status)."'";
$q.=" ORDER BY r.created_at DESC";

$rows=[]; $res=$conn->query($q);
while($res && $row=$res->fetch_assoc()) $rows[]=$row; if($res) $res->close();
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>My Requests</title>
<link rel="stylesheet" href="../assets/css/owner.css"></head>
<body><div class="container">
  <h2>My Assigned Requests <?= $status? '('.h($status).')':'' ?></h2>
  <form method="get" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:8px 0;">
    <select name="status">
      <option value="">-- Status --</option>
      <option value="pending"     <?= $status==='pending'?'selected':'' ?>>pending</option>
      <option value="in_progress" <?= $status==='in_progress'?'selected':'' ?>>in_progress</option>
      <option value="completed"   <?= $status==='completed'?'selected':'' ?>>completed</option>
    </select>
    <button>Filter</button>
  </form>

  <?php if(!$rows): ?><div class="empty">No requests.</div>
  <?php else: foreach($rows as $r): ?>
    <div class="trip-item">
      <div class="trip-info">
        <div class="trip-name">#<?= (int)$r['id'] ?></div>
        <div class="trip-location"><?= h($r['address']?:'—') ?></div>
        <div class="trip-date"><?= h(date('Y-m-d H:i', strtotime($r['created_at']))) ?></div>
      </div>
      <form method="post" action="../api/assign-driver.php" style="display:flex;gap:.5rem;">
        <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
        <input type="number" name="driver_id" placeholder="Driver ID" required style="width:140px">
        <button class="trip-btn assign" type="submit">Assign Driver</button>
      </form>
    </div>
  <?php endforeach; endif; ?>
</div></body></html>
