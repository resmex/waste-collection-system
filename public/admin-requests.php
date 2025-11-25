<?php
// admin-requests.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/rgeocode-client.php';

require_login(['municipal_admin']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- filters ---
$status  = isset($_GET['status']) ? trim($_GET['status']) : '';
$ward_id = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;
$from    = $_GET['from'] ?? '';
$to      = $_GET['to']   ?? '';
$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- get all truck owners for dropdown ---
$owners = [];
$oRes = $conn->query("SELECT id, name FROM users WHERE role='owner' AND status='active' ORDER BY name");
if ($oRes) {
    while ($row = $oRes->fetch_assoc()) {
        $owners[] = $row;
    }
    $oRes->close();
}

// --- get wards for filter ---
$wards = [];
$wRes = $conn->query("SELECT id, ward_name as name FROM wards ORDER BY ward_name");
if ($wRes) {
    while ($row = $wRes->fetch_assoc()) {
        $wards[] = $row;
    }
    $wRes->close();
}

// --- get requests (ONLY those without owner yet) ---
$q = "
  SELECT 
         r.id,
         r.address,
         r.status,
         r.created_at,
         r.ward_id,
         r.lat,
         r.lng,
         w.ward_name AS ward_name,
         w.municipal,
         w.region,
         COALESCE(
             NULLIF(r.address, ''),
             CONCAT_WS(', ', w.ward_name, w.municipal, w.region)
         ) AS display_address,
         u.name  AS resident,
         u.phone AS resident_phone,
         u.email AS resident_email,
         a.owner_id,
         o.name AS owner_name,
         d.name AS driver_name
  FROM requests r
  LEFT JOIN users u   ON u.id   = r.resident_id
  LEFT JOIN wards w   ON w.id   = r.ward_id
  LEFT JOIN assignments a ON a.request_id = r.id
  LEFT JOIN users o   ON o.id   = a.owner_id
  LEFT JOIN users d   ON d.id   = a.driver_id
  WHERE 1=1
    AND a.owner_id IS NULL
";
if ($status !== '') $q .= " AND r.status='" . $conn->real_escape_string($status) . "'";
if ($ward_id > 0)   $q .= " AND r.ward_id=$ward_id";
if ($from !== '')   $q .= " AND DATE(r.created_at) >= '" . $conn->real_escape_string($from) . "'";
if ($to !== '')     $q .= " AND DATE(r.created_at) <= '" . $conn->real_escape_string($to) . "'";
if ($id > 0)        $q .= " AND r.id=$id";
$q .= " ORDER BY r.created_at DESC";

$rows = [];
$res = $conn->query($q);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->close();
}

// Get statistics (overall, not filtered)
$stats = [
    'total'       => 0,
    'pending'     => 0,
    'in_progress' => 0,
    'completed'   => 0,
    'cancelled'   => 0
];
$statsRes = $conn->query("SELECT status, COUNT(*) as cnt FROM requests GROUP BY status");
if ($statsRes) {
    while ($row = $statsRes->fetch_assoc()) {
        $stats[$row['status']] = (int)$row['cnt'];
        $stats['total']       += (int)$row['cnt'];
    }
    $statsRes->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - E-Waste System</title>
    <link rel="stylesheet" href="../assets/css/requests.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Make cards a bit more compact/clean */
        .request-item {
            padding: 16px 18px;
        }
        .request-header {
            margin-bottom: 8px;
        }
        .request-contact,
        .assigned-line {
            margin-top: 4px;
        }
        .request-meta-line {
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="menu-icon">
                    <span></span><span></span><span></span>
                </div>
                <div class="welcome-text">Waste Collection Requests</div>
            </div>
            <div class="header-right">
                <div class="notification-badge">
                    <div class="notification-count"><?php echo (int)$stats['pending']; ?></div>
                </div>
                <div class="profile-pic"></div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Requests</div>
                <div class="stat-value cyan"><?php echo (int)$stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value orange"><?php echo (int)$stats['pending']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">In Progress</div>
                <div class="stat-value yellow"><?php echo (int)$stats['in_progress']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-value green"><?php echo (int)$stats['completed']; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="card-title">Filter Requests</div>
            <form method="get">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="pending"     <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $status==='in_progress'?'selected':''; ?>>In Progress</option>
                            <option value="completed"   <?php echo $status==='completed'?'selected':''; ?>>Completed</option>
                            <option value="cancelled"   <?php echo $status==='cancelled'?'selected':''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ward_id">Ward</label>
                        <select name="ward_id" id="ward_id" class="form-control">
                            <option value="0">All Wards</option>
                            <?php foreach($wards as $w): ?>
                            <option value="<?php echo (int)$w['id']; ?>" <?php echo $ward_id==$w['id']?'selected':''; ?>>
                                <?php echo h($w['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="from">From Date</label>
                        <input type="date" name="from" id="from" class="form-control" value="<?php echo h($from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="to">To Date</label>
                        <input type="date" name="to" id="to" class="form-control" value="<?php echo h($to); ?>">
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        Apply Filters
                    </button>
                    <a href="admin-requests.php" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        </svg>
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Requests List -->
        <div>
            <div class="section-title">
                <?php 
                $count = count($rows);
                echo $count . ' Request' . ($count !== 1 ? 's' : '') . ' Found';
                ?>
            </div>

            <?php if($count === 0): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3>No Requests Found</h3>
                    <p>Try adjusting your filters or check back later for new requests</p>
                </div>
            <?php else: ?>
                <?php foreach($rows as $r): 
                    // Get initials for avatar
                    if ($r['resident']) {
                        $nameParts = explode(' ', $r['resident']);
                        $initials = strtoupper(
                            substr($nameParts[0], 0, 1) . 
                            (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : '')
                        );
                    } else {
                        $initials = 'U';
                    }

                    // Badge styling based on status
                    $badgeClass = 'status-badge status-active';
                    if ($r['status'] === 'completed')       $badgeClass = 'status-badge status-active';
                    elseif ($r['status'] === 'in_progress') $badgeClass = 'status-badge status-active';
                    elseif ($r['status'] === 'cancelled')   $badgeClass = 'status-badge status-suspended';

                    // rGeocode – build human-readable area if we have lat/lng
                    $locationLabel = '';
                    if (!empty($r['lat']) && !empty($r['lng'])) {
                        $geo = rgeocode_lookup((float)$r['lat'], (float)$r['lng']); // from rgeocode-client.php

                        if (is_array($geo) && empty($geo['error'])) {
                            $parts = [];
                            if (!empty($geo['level4_name'])) $parts[] = $geo['level4_name'];
                            if (!empty($geo['level3_name'])) $parts[] = $geo['level3_name'];
                            if (!empty($geo['level2_name'])) $parts[] = $geo['level2_name'];

                            if ($parts) {
                                $locationLabel = implode(', ', $parts);
                            }
                        }
                    }
                ?>
                <div class="request-item">
                    <!-- Compact header -->
                    <div class="request-header">
                        <div class="request-info">
                            <div class="request-avatar">
                                <?php echo $initials; ?>
                            </div>
                            <div class="request-details">
                                <!-- Address as main title -->
                                <h4><?php echo h($r['display_address']); ?></h4>

                                <!-- Small line: resident + ward -->
                                <div class="request-meta-line" style="font-size: 13px; color:#666;">
                                    <span><?php echo h($r['resident'] ?: 'Unknown resident'); ?></span>
                                    <?php if($r['ward_name']): ?>
                                        <span> • <?php echo h($r['ward_name']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- rGeocode location label -->
                                <?php if ($locationLabel): ?>
                                    <div class="request-meta-line" style="font-size: 12px; color:#4b5563; margin-top:2px;">
                                        📍 <?php echo h($locationLabel); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Date & time -->
                                <div class="request-meta">
                                    <div class="meta-tag date">
                                        <?php echo h(date('M j, Y', strtotime($r['created_at']))); ?>
                                    </div>
                                    <div class="meta-tag address">
                                        <?php echo h(date('g:i A', strtotime($r['created_at']))); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="<?php echo $badgeClass; ?>">
                            <?php echo h(ucfirst(str_replace('_', ' ', $r['status']))); ?>
                        </div>
                    </div>

                    <!-- Compact contact row -->
                    <div class="request-contact" style="font-size: 13px; color:#555; margin: 4px 0 4px;">
                        <?php if($r['resident_phone']): ?>
                            <span>📞 <?php echo h($r['resident_phone']); ?></span>
                        <?php endif; ?>
                        <?php if($r['resident_email']): ?>
                            <?php if($r['resident_phone']) echo ' • '; ?>
                            <span>✉ <?php echo h($r['resident_email']); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Assignment Form -->
                    <div class="assignment-section">
                        <form method="post" action="../api/assign-owner.php" class="assignment-form">
                            <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                            
                            <div class="assignment-controls">
                                <div class="form-group assignment-select">
                                    <select name="owner_id" class="form-control" required>
                                        <option value="">-- Select Vehicle Owner --</option>
                                        <?php foreach($owners as $o): ?>
                                        <option value="<?php echo (int)$o['id']; ?>">
                                            <?php echo h($o['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-success assign-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <line x1="19" y1="8" x2="19" y2="14"></line>
                                        <line x1="22" y1="11" x2="16" y2="11"></line>
                                    </svg>
                                    Assign Owner
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // AJAX form submission for assigning owner
        document.querySelectorAll('.assignment-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData  = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const original  = submitBtn.innerHTML;
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Assigning...';
                
                fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Owner assigned successfully.');
                        location.reload();
                    } else {
                        alert(data.message || 'Error assigning owner.');
                    }
                })
                .catch(error => {
                    alert('Network error: ' + error);
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = original;
                });
            });
        });
    </script>
</body>
</html>
