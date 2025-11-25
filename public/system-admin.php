<?php
require_once __DIR__ . '/../includes/db.php';
@require_once __DIR__ . '/../includes/auth-check.php';
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$adminName = isset($currentUserName) ? $currentUserName : 'Administrator';

// Handle search and filter
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

/** Build query with filters **/
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

/** Users **/
$users = [];
$sql = "SELECT id, name, email, role, status, created_at FROM users $where_sql ORDER BY created_at DESC, name";
$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $users[] = $row;
    $stmt->close();
}

// Count users by role
$userCounts = [
    'total' => count($users),
    'active' => 0,
    'suspended' => 0,
    'municipal_admin' => 0,
    'councillor' => 0,
    'owner' => 0,
    'resident' => 0
];

foreach($users as $user) {
    if($user['status'] === 'active') $userCounts['active']++;
    if($user['status'] === 'suspended') $userCounts['suspended']++;
    if($user['role'] === 'municipal_admin') $userCounts['municipal_admin']++;
    if($user['role'] === 'councillor') $userCounts['councillor']++;
    if($user['role'] === 'owner') $userCounts['owner']++;
    if($user['role'] === 'resident') $userCounts['resident']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Administrator Dashboard</title>
  <link rel="stylesheet" href="../assets/css/municipal.css">
  
</head>
<body>
    <!-- Side Menu -->
    <div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>
    <div class="side-menu" id="sideMenu">
        <div class="menu-header">
            <h3>System Administrator</h3>
            <p><?= h($adminName) ?></p>
        </div>
        <div class="menu-section">
            <div class="menu-section-title">Dashboard Navigation</div>
            <div class="menu-items">
                <div class="menu-item" onclick="window.location.href='admin-dashboard.php'">
                    <div class="menu-item-icon">🏠</div>
                    <div class="menu-item-text">Admin dashboard</div>
                </div>
                <div class="menu-item" onclick="window.location.href='admin-dashboard.php'">
                    <div class="menu-item-icon">🏢</div>
                    <div class="menu-item-text">Municipal dashboard</div>
                </div>
                <div class="menu-item" onclick="window.location.href='councillor-dashboard.php'">
                    <div class="menu-item-icon">👨‍💼</div>
                    <div class="menu-item-text">Councillor dashboard</div>
                </div>
                <div class="menu-item" onclick="window.location.href='resident-dashboard.php'">
                    <div class="menu-item-icon">👤</div>
                    <div class="menu-item-text">Resident dashboard</div>
                </div>
                <div class="menu-item" onclick="window.location.href='owner-dashboard.php'">
                    <div class="menu-item-icon">🚛</div>
                    <div class="menu-item-text">Owner dashboard</div>
                </div>
                <div class="menu-item" onclick="window.location.href='driver-dashboard.php'">
                    <div class="menu-item-icon">🚛</div>
                    <div class="menu-item-text">Driver dashboard</div>
                </div>
                <div class="menu-item" onclick="window.location.href='../auth/logout.php'">
                    <div class="menu-item-icon">🚪</div>
                    <div class="menu-item-text">Logout</div>
                </div>
            </div>
        </div>
        
        <!-- <div class="menu-section">
            <div class="menu-section-title">System Health</div>
            <div class="system-status">
                <div class="status-item">
                    <span>Server Status</span>
                    <span class="status-value online">Online</span>
                </div>
                <div class="status-item">
                    <span>Database</span>
                    <span class="status-value online">Connected</span>
                </div>
                <div class="status-item">
                    <span>Uptime</span>
                    <span class="status-value">99.9%</span>
                </div>
                <div class="status-item">
                    <span>Last Backup</span>
                    <span class="status-value">2 hours ago</span>
                </div>
            </div>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Quick Actions</div>
            <div class="menu-items">
                <div class="menu-item" onclick="executeAction('logs')">
                    <div class="menu-item-icon">📊</div>
                    <div class="menu-item-text">Monitor Logs</div>
                </div>
                <div class="menu-item" onclick="executeAction('restart')">
                    <div class="menu-item-icon">🔄</div>
                    <div class="menu-item-text">Restart Services</div>
                </div>
                <div class="menu-item" onclick="executeAction('backup')">
                    <div class="menu-item-icon">💾</div>
                    <div class="menu-item-text">Backup Database</div>
                </div>
                <div class="menu-item" onclick="executeAction('cache')">
                    <div class="menu-item-icon">🧹</div>
                    <div class="menu-item-text">Clear Cache</div>
                </div>
            </div>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Security</div>
            <div class="system-status">
                <div class="status-item">
                    <span>Firewall</span>
                    <span class="status-value online">Active</span>
                </div>
                <div class="status-item">
                    <span>SSL Certificate</span>
                    <span class="status-value online">Valid</span>
                </div>
                <div class="status-item">
                    <span>Failed Logins</span>
                    <span class="status-value">0 (24h)</span>
                </div>
                <div class="status-item">
                    <span>Last Audit</span>
                    <span class="status-value">Today</span>
                </div>
            </div>
        </div> -->
        
        
    </div>

<div class="container">
  <!-- Header -->
  <div class="header">
    <div class="header-left">
      <div class="menu-icon" onclick="toggleMenu()">
        <span></span>
        <span></span>
        <span></span>
      </div>
      <div class="welcome-text">System Administrator, <strong><?= h($adminName) ?></strong></div>
    </div>
    <div class="header-right">
      <div class="notification-badge">
        <div class="notification-count"><?= (int)max(0, count($users)-1) ?></div>
      </div>
      <div class="profile-pic"></div>
    </div>
  </div>

  <!-- Main Statistics -->
  <div class="stats-grid-extended">
    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value cyan"><?= $userCounts['total'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-value green"><?= $userCounts['active'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Suspended</div>
            <div class="stat-value orange"><?= $userCounts['suspended'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Councillors</div>
            <div class="stat-value councillor"><?= $userCounts['councillor'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Truck Owners</div>
            <div class="stat-value owner"><?= $userCounts['owner'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Residents</div>
            <div class="stat-value resident"><?= $userCounts['resident'] ?></div>
        </div>
    </div>
</div>

  

  <!-- Filter Section -->
  <div class="filter-section">
    <form method="GET" action="">
      <div class="filter-grid">
        <div class="filter-group">
          <label class="filter-label">Search Users</label>
          <input type="text" name="search" class="filter-input" placeholder="Search by name or email..." value="<?= h($search) ?>">
        </div>
        <div class="filter-group">
          <label class="filter-label">Role</label>
          <select name="role" class="filter-select">
            <option value="">All Roles</option>
            <option value="municipal_admin" <?= $role_filter === 'municipal_admin' ? 'selected' : '' ?>>Municipal Admin</option>
            <option value="councillor" <?= $role_filter === 'councillor' ? 'selected' : '' ?>>Councillor</option>
            <option value="owner" <?= $role_filter === 'owner' ? 'selected' : '' ?>>Truck Owner</option>
            <option value="resident" <?= $role_filter === 'resident' ? 'selected' : '' ?>>Resident</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">Status</label>
          <select name="status" class="filter-select">
            <option value="">All Status</option>
            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
          </select>
        </div>
        <div class="filter-group">
          <button type="submit" class="filter-btn">Apply Filters</button>
        </div>
      </div>
      <?php if($search || $role_filter || $status_filter): ?>
        <div style="margin-top: 10px;">
          <a href="system-admin.php" class="reset-btn">Clear Filters</a>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <!-- User Management -->
  <div class="main-content">
    <div class="card full-width">
      <div class="card-title">
        User Management 
        <span style="font-size: 14px; color: #666; font-weight: normal; margin-left: 10px;">
          (Showing <?= count($users) ?> users)
        </span>
      </div>
      <div class="scroll-container">
        <?php if(!$users): ?>
          <div class="empty">No users found matching your criteria.</div>
        <?php else: ?>
          <?php foreach($users as $u):
            $status = strtolower($u['status'] ?? 'active');
            $cls = ($status==='active'?'status-active':($status==='suspended'?'status-suspended':'status-inactive'));
            $roleClass = 'role-' . $u['role'];
            $initials = strtoupper(substr($u['name'] ?: 'U', 0, 1) . substr($u['name'] ?: 'ser', 1, 1));
          ?>
          <div class="user-item">
            <div class="user-header">
              <div class="user-info">
                <div class="user-avatar">
                  <?= $initials ?>
                </div>
                <div class="user-details">
                  <h4>
                    <?= h($u['name'] ?: ('User #'.$u['id'])) ?>
                    <span class="role-badge <?= $roleClass ?>"><?= h(ucfirst($u['role'])) ?></span>
                  </h4>
                  <div class="user-email"><?= h($u['email'] ?: 'No email') ?></div>
                  <div class="user-meta">
                    <span style="font-size: 11px; color: #999;">Joined: <?= date('M j, Y', strtotime($u['created_at'])) ?></span>
                  </div>
                </div>
              </div>
              <div class="status-badge <?= $cls ?>"><?= h(ucfirst($status)) ?></div>
            </div>
            
            <div class="user-tags">
              <span class="tag suspend" onclick="suspendUser(<?= (int)$u['id'] ?>)">Suspend</span>
              <span class="tag warn" onclick="warnUser(<?= (int)$u['id'] ?>)">delete</span>
              <span class="tag activity" onclick="viewActivity(<?= (int)$u['id'] ?>)">View Activity</span>
            </div>
            
            <!-- <div class="action-buttons">
              <form method="post" action="../api/user-actions.php" style="display:flex;gap:8px;flex-wrap:wrap">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <?php if($status==='suspended'): ?>
                  <button class="action-btn activate" name="action" value="activate">Activate</button>
                <?php else: ?>
                  <button class="action-btn edit" name="action" value="edit">Edit</button>
                  <button class="action-btn suspend" name="action" value="suspend">Suspend</button>
                <?php endif; ?>
                <button class="action-btn delete" name="action" value="delete" onclick="return confirm('Delete user <?= h($u['name'] ?: $u['email']) ?>?')">Delete</button>
              </form>
            </div> -->
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/admin.js"></script> 

</script>
</body>
</html>