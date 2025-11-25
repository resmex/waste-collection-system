<?php 
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
require_once __DIR__ . '/../includes/rgeocode-client.php';


require_role(['municipal_admin']);
require_login(['municipal_admin']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$adminName = $currentUserName ?: 'Admin';

// --- CSRF (safe default) ---
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf'];

// Handle user actions (delete, toggle status)
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        $errorMsg = 'Invalid request token.'; 
    } else {
        if (isset($_POST['delete_user'])) {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                // Check role
                $checkStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
                $checkStmt->bind_param("i", $userId);
                $checkStmt->execute();
                $checkStmt->bind_result($userRole);
                if ($checkStmt->fetch()) {
                    $checkStmt->close();

                    // treat both 'owner' and 'truck_owner' as owners
                    if (in_array($userRole, ['owner','truck_owner'], true)) {
                        // Check vehicles
                        $vehicleCheck = $conn->prepare("SELECT COUNT(*) FROM vehicles WHERE owner_id = ? AND (status IS NULL OR status <> 'deleted')");
                        $vehicleCheck->bind_param("i", $userId);
                        $vehicleCheck->execute();
                        $vehicleCheck->bind_result($vehicleCount);
                        $vehicleCheck->fetch();
                        $vehicleCheck->close();

                        if ($vehicleCount > 0) {
                            $errorMsg = "Cannot delete truck owner with registered vehicles. Please delete their vehicles first.";
                        } else {
                            $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                            $deleteStmt->bind_param("i", $userId);
                            $successMsg = $deleteStmt->execute() ? "User deleted successfully!" : "Error deleting user.";
                            $deleteStmt->close();
                        }
                    } else {
                        // Councillor: delete directly
                        $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $deleteStmt->bind_param("i", $userId);
                        $successMsg = $deleteStmt->execute() ? "User deleted successfully!" : "Error deleting user.";
                        $deleteStmt->close();
                    }
                } else {
                    $errorMsg = "User not found.";
                    $checkStmt->close();
                }
            }
        }

        if (isset($_POST['toggle_status'])) {
            $userId = (int)($_POST['user_id'] ?? 0);
            $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';
            if ($userId > 0) {
                $updateStmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newStatus, $userId);
                $successMsg = $updateStmt->execute() ? "User status updated successfully!" : "Error updating user status.";
                $updateStmt->close();
            }
        }
    }
}

// Filters
$roleFilter   = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery  = $_GET['search'] ?? '';

// Build base query (accept both owner role names)
$query = "
  SELECT u.id, u.name, u.email, u.phone, u.role, u.status, u.created_at,
         SUM(CASE WHEN v.id IS NOT NULL AND (v.status IS NULL OR v.status <> 'deleted') THEN 1 ELSE 0 END) as vehicle_count,
         COUNT(DISTINCT r.id) as request_count
  FROM users u
  LEFT JOIN vehicles v ON v.owner_id = u.id
  LEFT JOIN requests r ON r.resident_id = u.id
  WHERE u.role IN ('councillor', 'owner', 'truck_owner')
";
$params = []; $types = '';

// Apply filters
if ($roleFilter !== 'all') {
    if ($roleFilter === 'owner') {
        $query .= " AND u.role IN ('owner','truck_owner')";
    } else {
        $query .= " AND u.role = ?";
        $params[] = $roleFilter;
        $types .= 's';
    }
}
if ($statusFilter !== 'all') {
    $query .= " AND u.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
if (!empty($searchQuery)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm; $params[] = $searchTerm;
    $types .= 'ss';
}

$query .= "
  GROUP BY u.id, u.name, u.email, u.phone, u.role, u.status, u.created_at
  ORDER BY u.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();

$owners = [];
$councillors = [];
while ($row = $result->fetch_assoc()) {
    if (in_array($row['role'], ['owner','truck_owner'], true)) {
        $owners[] = $row;
    } else {
        $councillors[] = $row;
    }
}
$stmt->close();

// counts for badges (accept both owner names)
$totalUsers      = count($owners) + count($councillors);
$councillorCount = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role='councillor'")->fetch_row()[0];
$ownerCount      = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role IN ('owner','truck_owner')")->fetch_row()[0];
$activeCount     = (int)$conn->query("SELECT COUNT(*) FROM users WHERE status='active' AND role IN ('councillor','owner','truck_owner')")->fetch_row()[0];
$inactiveCount   = (int)$conn->query("SELECT COUNT(*) FROM users WHERE status='inactive' AND role IN ('councillor','owner','truck_owner')")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users - E-Waste System</title>
  <link rel="stylesheet" href="../assets/css/municipal.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="header-left">
        <a href="admin-dashboard.php" class="back-button">←</a>
        <div class="welcome-text">Manage Users, <strong><?=h($adminName);?></strong></div>
      </div>
      <div class="header-right">
        <div class="notification-badge"><div class="notification-count">3</div></div>
        <div class="profile-pic"></div>
      </div>
    </div>

    <!-- Alerts -->
    <?php if($successMsg): ?><div class="alert alert-success"><?=h($successMsg);?></div><?php endif; ?>
    <?php if($errorMsg): ?><div class="alert alert-error"><?=h($errorMsg);?></div><?php endif; ?>

    <!-- Filters -->
    <div class="card">
      <div class="card-title">Filters & Search</div>
      <form method="GET" action="" class="filter-form">
        <div class="filter-grid">
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" onchange="this.form.submit()">
              <option value="all"      <?=$roleFilter==='all'?'selected':'';?>>All Roles (<?=$totalUsers;?>)</option>
              <option value="councillor" <?=$roleFilter==='councillor'?'selected':'';?>>Councillors (<?=$councillorCount;?>)</option>
              <option value="owner"    <?=$roleFilter==='owner'?'selected':'';?>>Truck Owners (<?=$ownerCount;?>)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
              <option value="all"     <?=$statusFilter==='all'?'selected':'';?>>All Status</option>
              <option value="active"  <?=$statusFilter==='active'?'selected':'';?>>Active (<?=$activeCount;?>)</option>
              <option value="inactive"<?=$statusFilter==='inactive'?'selected':'';?>>Inactive (<?=$inactiveCount;?>)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Search</label>
            <div class="search-container">
              <input type="text" name="search" class="form-input" placeholder="Search by name or email..." value="<?=h($searchQuery);?>">
              <button type="submit" class="btn btn-primary">Search</button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">&nbsp;</label>
            <a href="admin-users.php" class="btn btn-outline">Clear Filters</a>
          </div>
        </div>
      </form>
    </div>

    <!-- Truck Owners Section -->
    <div class="card full-width">
      <div class="card-title">Truck Owners</div>
      <div class="scroll-container">
        <?php if(empty($owners)): ?>
          <div class="empty">No truck owners found.</div>
        <?php else: foreach($owners as $user): 
          $statusClass = $user['status']==='active' ? 'badge-approved' : 'badge-pending';
          $initials='U';
          if ($user['name']) {
            $parts = explode(' ', $user['name']); 
            $initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):''));
          }
        ?>
        <div class="user-item">
          <div class="user-header">
            <div class="user-info">
              <div class="user-avatar role-owner"><?= $initials; ?></div>
              <div class="user-details">
                <h4><?=h($user['name']);?></h4>
                <div class="user-email"><?=h($user['email']);?></div>
                <div class="user-meta">
                  <span class="user-role role-owner">Truck Owner</span>
                  <?php if($user['phone']): ?><span class="user-phone">📞 <?=h($user['phone']);?></span><?php endif; ?>
                  <span class="user-stats">🚛 <?= (int)$user['vehicle_count']; ?> vehicles</span>
                  <span class="user-stats">📋 <?= (int)$user['request_count']; ?> requests</span>
                </div>
              </div>
            </div>
            <div class="user-actions">
              <span class="badge <?=$statusClass;?>"><?=h(ucfirst($user['status']));?></span>
              <div class="action-buttons">
                <!-- Activate/Deactivate -->
                <form method="POST" action="">
                  <input type="hidden" name="csrf" value="<?=$CSRF;?>">
                  <input type="hidden" name="user_id" value="<?= (int)$user['id']; ?>">
                  <input type="hidden" name="new_status" value="<?= $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                  <button type="submit" name="toggle_status" class="btn btn-warning">
                    <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                  </button>
                </form>
                <!-- View Vehicles (button style for consistency) -->
                <a class="btn btn-outline" href="admin-vehicles.php?owner_id=<?= (int)$user['id']; ?>">
                  View Vehicles
                </a>
                <!-- Delete -->
                <button type="button" class="btn btn-danger" 
                        onclick="confirmDelete(<?= (int)$user['id']; ?>, '<?= h($user['name']); ?>')">
                  Delete
                </button>
              </div>
            </div>
          </div>
          <div class="user-footer">
            <div class="user-tags">
              <div class="tag date">Joined: <?= h(date('M j, Y', strtotime($user['created_at']))); ?></div>
              <div class="tag id">ID: #<?= (int)$user['id']; ?></div>
              <?php if((int)$user['vehicle_count'] > 0): ?>
                <div class="tag warning">Has <?= (int)$user['vehicle_count']; ?> vehicle(s)</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Councillors Section -->
    <div class="card full-width">
      <div class="card-title">Councillors</div>
      <div class="scroll-container">
        <?php if(empty($councillors)): ?>
          <div class="empty">No councillors found.</div>
        <?php else: foreach($councillors as $user): 
          $statusClass = $user['status']==='active' ? 'badge-approved' : 'badge-pending';
          $initials='U';
          if ($user['name']) {
            $parts = explode(' ', $user['name']); 
            $initials = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):''));
          }
        ?>
        <div class="user-item">
          <div class="user-header">
            <div class="user-info">
              <div class="user-avatar role-councillor"><?= $initials; ?></div>
              <div class="user-details">
                <h4><?=h($user['name']);?></h4>
                <div class="user-email"><?=h($user['email']);?></div>
                <div class="user-meta">
                  <span class="user-role role-councillor">Councillor</span>
                  <?php if($user['phone']): ?><span class="user-phone">📞 <?=h($user['phone']);?></span><?php endif; ?>
                  <span class="user-stats">📋 <?= (int)$user['request_count']; ?> requests</span>
                </div>
              </div>
            </div>
            <div class="user-actions">
              <span class="badge <?=$statusClass;?>"><?=h(ucfirst($user['status']));?></span>
              <div class="action-buttons">
                <!-- Activate/Deactivate -->
                <form method="POST" action="">
                  <input type="hidden" name="csrf" value="<?=$CSRF;?>">
                  <input type="hidden" name="user_id" value="<?= (int)$user['id']; ?>">
                  <input type="hidden" name="new_status" value="<?= $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                  <button type="submit" name="toggle_status" class="btn btn-warning">
                    <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                  </button>
                </form>
                <!-- Delete -->
                <button type="button" class="btn btn-danger" 
                        onclick="confirmDelete(<?= (int)$user['id']; ?>, '<?= h($user['name']); ?>')">
                  Delete
                </button>
              </div>
            </div>
          </div>
          <div class="user-footer">
            <div class="user-tags">
              <div class="tag date">Joined: <?= h(date('M j, Y', strtotime($user['created_at']))); ?></div>
              <div class="tag id">ID: #<?= (int)$user['id']; ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <div class="modal-header" style="background:#e53935;">
        <h2 class="modal-title">Confirm Delete</h2>
        <span class="close" onclick="closeModal('deleteModal')">&times;</span>
      </div>
      <div class="modal-body">
        <p id="deleteMessage">Are you sure you want to delete this user?</p>
        <form method="POST" action="" id="deleteForm">
          <input type="hidden" name="csrf" value="<?=$CSRF;?>">
          <input type="hidden" name="user_id" id="deleteUserId">
          <input type="hidden" name="delete_user" value="1">
          <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete User</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function openModal(id){ document.getElementById(id).style.display='block'; document.body.style.overflow='hidden'; }
    function closeModal(id){ document.getElementById(id).style.display='none'; document.body.style.overflow='auto'; }

    function confirmDelete(userId, userName) {
      document.getElementById('deleteUserId').value = userId;
      document.getElementById('deleteMessage').textContent = 'Are you sure you want to delete "'+ userName +'"? This cannot be undone.';
      openModal('deleteModal');
    }

    // Auto-fade alerts
    setTimeout(()=>{
      document.querySelectorAll('.alert').forEach(a=>{
        a.style.transition='opacity .5s'; a.style.opacity='0';
        setTimeout(()=> a.style.display='none', 500);
      });
    }, 4000);
  </script>
</body>
</html>
