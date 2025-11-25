<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
// rgeocode not needed here
// require_once __DIR__ . '/../includes/rgeocode-client.php';

require_login(['municipal_admin']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---------- HANDLE APPROVE / REJECT POST FIRST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'], $_POST['action'])) {
    $vehicleId = (int)$_POST['vehicle_id'];
    $action    = trim($_POST['action']);
    $reason    = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    if ($vehicleId && ($action === 'approve' || $action === 'reject')) {
        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE vehicles SET status='approved', rejection_reason=NULL WHERE id=?");
            $stmt->bind_param('i', $vehicleId);
            $ok = $stmt->execute();
            $stmt->close();

            $msg = $ok ? 'Vehicle approved successfully.' : 'Failed to approve vehicle.';
        } else {
            $stmt = $conn->prepare("UPDATE vehicles SET status='rejected', rejection_reason=? WHERE id=?");
            $stmt->bind_param('si', $reason, $vehicleId);
            $ok = $stmt->execute();
            $stmt->close();

            $msg = $ok ? 'Vehicle rejected.' : 'Failed to reject vehicle.';
        }

        // Redirect to avoid form re-submit
        header("Location: admin-vehicles.php?flash=" . urlencode($msg));
        exit;
    }
}

/* ---------- FILTERS & DATA LOADING ---------- */

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$q = "
    SELECT v.id,
           v.plate_no,
           v.capacity_kg,
           v.status,
           v.doc_url,
           v.created_at,
           v.rejection_reason,
           u.name  AS owner_name,
           u.phone AS owner_phone,
           u.email AS owner_email
    FROM vehicles v
    JOIN users u ON u.id = v.owner_id
    WHERE 1=1
";

if ($status !== '') {
    $q .= " AND v.status='" . $conn->real_escape_string($status) . "'";
}
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $q   .= " AND (v.plate_no LIKE '%$safe%' OR u.name LIKE '%$safe%')";
}
$q .= " ORDER BY v.created_at DESC";

$rows = [];
$res  = $conn->query($q);
while ($res && $row = $res->fetch_assoc()) {
    $rows[] = $row;
}
if ($res) $res->close();

// Get statistics
$stats = [
    'total'    => 0,
    'pending'  => 0,
    'approved' => 0,
    'rejected' => 0
];

$statsRes = $conn->query("SELECT status, COUNT(*) AS cnt FROM vehicles GROUP BY status");
while ($statsRes && $row = $statsRes->fetch_assoc()) {
    $stats[$row['status']] = (int)$row['cnt'];
    $stats['total']      += (int)$row['cnt'];
}
if ($statsRes) $statsRes->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Approvals - E-Waste System</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Force the stats grid into a 2-column layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* 2 columns */
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Essential styles for the Vehicle List Items to match dashboard aesthetic */
        .vehicle-item {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            border-left: 4px solid #4caf50;
            transition: all 0.3s;
        }

        .vehicle-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .vehicle-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .vehicle-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
        }

        .vehicle-details h4 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            color: #333;
        }

        .vehicle-email {
            font-size: 12px;
            color: #999;
        }

        .vehicle-meta {
            display: flex;
            gap: 8px;
            margin-top: 5px;
        }

        .meta-tag {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            color: #fff;
        }
        .meta-tag.id { background: #9c27b0; }
        .meta-tag.date { background: #666; }
        .meta-tag.capacity { background: #2196F3; }

        .vehicle-plate {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
        }

        /* Detail grid for owner info */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px 20px;
            margin-top: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #999;
            font-weight: 500;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .detail-value svg {
            color: #4caf50;
            stroke-width: 2.5;
        }

        .rejection-reason {
            margin-top: 15px;
            padding: 10px 15px;
            background: #ffebee;
            border: 1px solid #e53935;
            border-radius: 6px;
        }

        .rejection-reason-label {
            font-size: 12px;
            font-weight: 700;
            color: #e53935;
            margin-bottom: 5px;
        }

        .rejection-reason-text {
            font-size: 13px;
            color: #333;
        }

        .document-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .document-label {
            font-size: 12px;
            font-weight: 700;
            color: #4caf50;
            margin-bottom: 8px;
        }

        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #2196F3;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s;
        }

        .document-link:hover {
            color: #1976D2;
        }

        /* Action buttons/messages */
        .action-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .approve-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-success {
            padding: 10px 15px;
            background: #4caf50;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-danger {
            padding: 10px 15px;
            background: #f44336;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-danger:hover {
            background: #e53935;
        }

        .status-message {
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-rejected {
            background: #ffebee;
            color: #c62828;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
            color: #999;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #666;
            margin-bottom: 8px;
        }
        
        /* Flash message */
        .success-message {
            margin: 15px 0;
            padding: 10px 15px;
            border-radius: 8px;
            background: #e8f5e9;
            color: #2e7d32;
            font-size: 14px;
            font-weight: 500;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-grid, .detail-grid {
                grid-template-columns: 2fr; /* Stack columns on small screens */
            }
            .vehicle-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <?php if (!empty($_GET['flash'])): ?>
        <div class="success-message">
            <?= h($_GET['flash']) ?>
        </div>
    <?php endif; ?>

    <div class="header">
        <div class="header-left">
            <div class="menu-icon">
                <span></span><span></span><span></span>
            </div>
            <div class="welcome-text">Vehicle Management</div>
        </div>
        <div class="header-right">
            <div class="notification-badge">
                <div class="notification-count"><?php echo (int)$stats['pending']; ?></div>
            </div>
            <div class="profile-pic"></div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Vehicles</div>
            <div class="stat-value cyan"><?php echo (int)$stats['total']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending Approval</div>
            <div class="stat-value orange"><?php echo (int)$stats['pending']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Approved</div>
            <div class="stat-value green"><?php echo (int)$stats['approved']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Rejected</div>
            <div class="stat-value red"><?php echo (int)$stats['rejected']; ?></div>
        </div>
    </div>

    <div class="filter-section">
        <div class="card-title">Filter Vehicles</div>
        <form method="get">
            <div class="filter-grid">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input
                        type="text"
                        name="search"
                        id="search"
                        class="form-control"
                        placeholder="Search by plate number or owner name..."
                        value="<?php echo h($search); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="pending"  <?php echo $status==='pending'  ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status==='approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status==='rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        Search
                    </button>
                </div>
            </div>

            <?php if ($status || $search): ?>
                <div style="margin-top: 16px;">
                    <a href="admin-vehicles.php" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div>
        <div class="section-title">
            <?php
            $count = count($rows);
            echo $count . ' Vehicle' . ($count !== 1 ? 's' : '') . ' Found';
            ?>
        </div>

        <?php if (!$rows): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
                <h3>No Vehicles Found</h3>
                <p>No vehicle registrations match your search criteria</p>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $v): ?>
                <?php
                // Avatar initials
                if ($v['owner_name']) {
                    $nameParts = explode(' ', $v['owner_name']);
                    $initials  = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                } else {
                    $initials = 'U';
                }

                // Status badge styling
                $statusClass = 'status-badge badge-pending';
                if ($v['status'] === 'approved') {
                    $statusClass = 'status-badge badge-approved';
                } elseif ($v['status'] === 'rejected') {
                    $statusClass = 'status-badge badge-danger';
                }
                ?>
                <div class="vehicle-item" data-vehicle-id="<?php echo (int)$v['id']; ?>">
                    <div class="vehicle-header">
                        <div class="vehicle-info">
                            <div class="vehicle-avatar">
                                <?php echo h($initials); ?>
                            </div>
                            <div class="vehicle-details">
                                <h4><?php echo h($v['owner_name']); ?></h4>
                                <div class="vehicle-email">
                                    <?php echo h($v['owner_email'] ?: 'No email provided'); ?>
                                </div>
                                <div class="vehicle-meta">
                                    <div class="meta-tag id">#<?php echo (int)$v['id']; ?></div>
                                    <div class="meta-tag date">
                                        <?php echo h(date('M j, Y', strtotime($v['created_at']))); ?>
                                    </div>
                                    <div class="meta-tag capacity">
                                        <?php echo (int)$v['capacity_kg']; ?> kg capacity
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="<?php echo $statusClass; ?>">
                            <?php echo h(ucfirst($v['status'])); ?>
                        </div>
                    </div>

                    <div class="vehicle-plate">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" style="vertical-align: middle; margin-right: 8px; color: #4caf50;">
                            <path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"></path>
                            <circle cx="6.5" cy="16.5" r="2.5"></circle>
                            <circle cx="16.5" cy="16.5" r="2.5"></circle>
                        </svg>
                        Plate No: <?php echo h($v['plate_no']); ?>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Vehicle Owner</span>
                            <span class="detail-value">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <?php echo h($v['owner_name']); ?>
                            </span>
                        </div>

                        <?php if ($v['owner_phone']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    <?php echo h($v['owner_phone']); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if ($v['owner_email']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Email</span>
                                <span class="detail-value">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                                    </svg>
                                    <?php echo h($v['owner_email']); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="detail-item">
                            <span class="detail-label">Capacity</span>
                            <span class="detail-value">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 3h5v5"></path>
                                    <path d="M8 3H3v5"></path>
                                    <path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"></path>
                                    <path d="m15 9 6-6"></path>
                                </svg>
                                <?php echo (int)$v['capacity_kg']; ?> kg
                            </span>
                        </div>
                    </div>

                    <?php if ($v['status'] === 'rejected' && $v['rejection_reason']): ?>
                        <div class="rejection-reason">
                            <div class="rejection-reason-label">Rejection Reason</div>
                            <div class="rejection-reason-text">
                                <?php echo h($v['rejection_reason']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($v['doc_url']): ?>
                        <div class="document-section">
                            <div class="document-label">VEHICLE DOCUMENT</div>
                            <a href="../<?php echo h($v['doc_url']); ?>" target="_blank" class="document-link">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="12" y1="18" x2="12" y2="12"></line>
                                    <line x1="9" y1="15" x2="15" y2="15"></line>
                                </svg>
                                View Registration Document
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($v['status'] === 'pending'): ?>
                        <div class="action-section">
                            <!-- POST to same page, no JS/AJAX -->
                            <form method="post" action="admin-vehicles.php" class="approve-form">
                                <input type="hidden" name="vehicle_id" value="<?php echo (int)$v['id']; ?>">

                                <button type="submit" name="action" value="approve" class="btn-success">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    Approve Vehicle
                                </button>

                                <button type="submit" name="action" value="reject" class="btn-danger">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                    </svg>
                                    Reject Vehicle
                                </button>
                            </form>
                        </div>
                    <?php elseif ($v['status'] === 'approved'): ?>
                        <div class="action-section">
                            <div class="status-message status-approved">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                This vehicle has been approved and is active in the fleet
                            </div>
                        </div>
                    <?php elseif ($v['status'] === 'rejected'): ?>
                        <div class="action-section">
                            <div class="status-message status-rejected">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                </svg>
                                This vehicle registration has been rejected
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Search on Enter key
    document.getElementById('search')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.form.submit();
        }
    });
</script>
</body>
</html>
