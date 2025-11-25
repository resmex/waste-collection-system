<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
require_once __DIR__ . '/../includes/rgeocode-client.php';


require_role(['municipal_admin']);   
require_login(['municipal_admin']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$adminName = $currentUserName ?: 'Admin';

// Flash-style messages
$successMsg = '';
$errorMsg   = '';
$activeRole = null;

// If redirected after success, show success message (GET)
if (!empty($_GET['registered']) && in_array($_GET['registered'], ['councillor','owner'], true)) {
    $roleLabel  = ucfirst($_GET['registered']);
    $successMsg = "$roleLabel registered successfully!";
}

// Handle user registration (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user'])) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role'] ?? '');
    $activeRole = $role; // remember which modal to reopen if error

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $errorMsg = "All required fields must be filled.";
    } elseif (!in_array($role, ['councillor', 'owner'], true)) {
        $errorMsg = "Invalid role selected.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Invalid email format.";
    } 
    // Strong password: min 8, with upper, lower, and digit
    elseif (strlen($password) < 8 
        || !preg_match('/[A-Z]/', $password) 
        || !preg_match('/[a-z]/', $password) 
        || !preg_match('/\d/',   $password)
    ) {
        $errorMsg = "Password must be at least 8 characters and include uppercase, lowercase letters and a number.";
    } else {
        // Check if email already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $errorMsg = "Email already exists in the system.";
        } else {
            // Hash password and insert user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $conn->prepare("
                INSERT INTO users (name, email, phone, password_hash, role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->bind_param("sssss", $name, $email, $phone, $hashedPassword, $role);
            
            if ($insertStmt->execute()) {
                // ✅ SUCCESS: redirect so that refresh does NOT resubmit the form
                $redirectRole = $role; // councillor/owner
                $insertStmt->close();
                $checkStmt->close();
                header("Location: " . $_SERVER['PHP_SELF'] . "?registered=" . urlencode($redirectRole));
                exit;
            } else {
                $errorMsg = "Error registering user. Please try again.";
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
}

// Statistics
$statTotalTrips = (int)($conn->query("SELECT COUNT(*) c FROM requests")->fetch_assoc()['c'] ?? 0);
$statNew        = (int)($conn->query("SELECT COUNT(*) c FROM requests WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$statPendingVeh = (int)($conn->query("SELECT COUNT(*) c FROM vehicles WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$statCompleted  = (int)($conn->query("SELECT COUNT(*) c FROM requests WHERE status='completed'")->fetch_assoc()['c'] ?? 0);

// Get counts for councillors and owners
$statCouncillors = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='councillor'")->fetch_assoc()['c'] ?? 0);
$statOwners      = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='owner'")->fetch_assoc()['c'] ?? 0);

// Get owners with pending vehicles (grouped)
$ownersWithPending = [];
$ownerQuery = "SELECT u.id, u.name, u.phone, u.email,
               COUNT(v.id) as pending_count,
               MAX(v.created_at) as latest_submission
               FROM users u
               INNER JOIN vehicles v ON v.owner_id = u.id
               WHERE u.role='owner' AND v.status='pending'
               GROUP BY u.id, u.name, u.phone, u.email
               ORDER BY latest_submission DESC
               LIMIT 5";
$res = $conn->query($ownerQuery);
while($res && $row = $res->fetch_assoc()) $ownersWithPending[] = $row;
if($res) $res->close();

$wards = [];
$wres = $conn->query("SELECT id, ward_name, municipal FROM wards ORDER BY municipal, ward_name");
while ($wres && $row = $wres->fetch_assoc()) $wards[] = $row;
if ($wres) $wres->close();


// Recent pending requests
$requests=[]; 
$r=$conn->query("SELECT r.id, r.address, r.status, r.created_at, 
                        u.name as resident_name, u.email as resident_email
                 FROM requests r 
                 LEFT JOIN users u ON u.id = r.resident_id
                 WHERE r.status = 'pending'
                 ORDER BY r.created_at DESC LIMIT 3");
while($r && $row=$r->fetch_assoc()) $requests[]=$row; 
if($r) $r->close();

// Recent feedback
$feedback=[];
$f=$conn->query("SELECT f.rating, f.comments, u.name AS resident_name, f.created_at
                 FROM feedback f LEFT JOIN users u ON u.id=f.resident_id
                 ORDER BY f.created_at DESC LIMIT 8");
while($f && $row=$f->fetch_assoc()) $feedback[]=$row; 
if($f) $f->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Admin Dashboard - Waste Collection system</title>

    <link rel="stylesheet" href="../assets/css/municipal.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
</head>
<body>
    <!-- Side Menu -->
    <div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>
    <div class="side-menu" id="sideMenu">
       <div class="menu-header">
    <h3>Hi, <?php echo h($adminName); ?></h3>
    <p>Municipal admin dashboard</p>
</div>

        
        <div class="menu-section">
            <div class="menu-section-title">User Management</div>
            <div class="menu-items">
                <div class="menu-item" onclick="openModal('councillorModal')">
                    <div class="menu-item-icon">👤</div>
                    <div class="menu-item-text">Register Councillor</div>
                </div>
                <div class="menu-item" onclick="openModal('ownerModal')">
                    <div class="menu-item-icon">🚛</div>
                    <div class="menu-item-text">Register Truck Owner</div>
                </div>
                <div class="menu-item" onclick="window.location.href='admin-users.php'">
                    <div class="menu-item-icon">👥</div>
                    <div class="menu-item-text">Manage All Users</div>
                </div>
            </div>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">System Management</div>
            <div class="menu-items">
                <div class="menu-item" onclick="window.location.href='admin-requests.php'">
                    <div class="menu-item-icon">📋</div>
                    <div class="menu-item-text">Manage Requests</div>
                </div>
                <div class="menu-item" onclick="window.location.href='admin-vehicles.php'">
                    <div class="menu-item-icon">🚗</div>
                    <div class="menu-item-text">Manage Vehicles</div>
                </div>
                <!-- <div class="menu-item" onclick="window.location.href='admin-reports.php'">
                    <div class="menu-item-icon">📊</div>
                    <div class="menu-item-text">View Reports</div>
                </div> -->
            </div>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Account</div>
            <div class="menu-items">
                <div class="menu-item" onclick="window.location.href='admin-profile.php'">
                    <div class="menu-item-icon">⚙️</div>
                    <div class="menu-item-text">Profile Settings</div>
                </div>
                <div class="menu-item" onclick="window.location.href='../auth/logout.php'">
                    <div class="menu-item-icon">🚪</div>
                    <div class="menu-item-text">Logout</div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="menu-icon" onclick="toggleMenu()">
                    <span></span><span></span><span></span>
                </div>
                <div class="welcome-text">Welcome, <strong><?php echo h($adminName); ?></strong></div>
            </div>
            <div class="header-right">
                <div class="notification-badge">
                    <div class="notification-count">3</div>
                </div>
                <div class="profile-pic"></div>
            </div>
        </div>

        <!-- Dashboard Welcome Section -->
        <!-- <div class="dashboard-welcome">
            <h1>E-Waste Management Dashboard</h1>
            <p>Monitor and manage the e-waste collection system efficiently</p>
            <div class="welcome-stats">
                <div class="welcome-stat">
                    <div class="welcome-stat-value"><?php echo $statTotalTrips; ?></div>
                    <div class="welcome-stat-label">Total Requests</div>
                </div>
                <div class="welcome-stat">
                    <div class="welcome-stat-value"><?php echo $statNew; ?></div>
                    <div class="welcome-stat-label">Pending Requests</div>
                </div>
                <div class="welcome-stat">
                    <div class="welcome-stat-value"><?php echo $statPendingVeh; ?></div>
                    <div class="welcome-stat-label">Vehicles to Approve</div>
                </div>
                <div class="welcome-stat">
                    <div class="welcome-stat-value"><?php echo $statCompleted; ?></div>
                    <div class="welcome-stat-label">Completed</div>
                </div>
            </div>
        </div> -->

        <!-- Statistics Cards - 3 per row -->
        <div class="stats-grid-extended">
            <div class="stat-row">
                <div class="stat-card">
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-value cyan"><?php echo $statTotalTrips; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">New Requests</div>
                    <div class="stat-value orange"><?php echo $statNew; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Vehicles</div>
                    <div class="stat-value yellow"><?php echo $statPendingVeh; ?></div>
                </div>
            </div>
            <div class="stat-row">
                <div class="stat-card">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value green"><?php echo $statCompleted; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Councillors</div>
                    <div class="stat-value purple"><?php echo $statCouncillors; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Truck Owners</div>
                    <div class="stat-value indigo"><?php echo $statOwners; ?></div>
                </div>
            </div>
        </div>

        <!-- Main Content - Clean Layout -->
        <div class="main-content">
            <!-- Left Column: Vehicle Approvals -->
            <div class="card">
                <div class="card-title">Vehicles waiting for approval</div>

                <div class="scroll-container">
                    <?php if(!$ownersWithPending): ?>
                        <div class="empty">All vehicles approved! 🎉</div>
                    <?php else: ?>
                        <?php foreach($ownersWithPending as $owner): ?>
                        <div class="user-item">
                            <div class="user-header">
                                <div class="user-info">
                                    <div class="user-avatar"></div>
                                    <div class="user-details">
                                        <h4><?php echo h($owner['name']); ?></h4>
                                        <div class="user-email"><?php echo h($owner['email']); ?></div>
                                        <div class="user-role">Owner</div>
                                    </div>
                                </div>
                                <div class="status-badge status-active">
                                    <?php echo (int)$owner['pending_count']; ?> pending
                                </div>
                            </div>
                            <div class="user-tags">
                                <div class="tag activity"><?php echo h(date('M j, Y', strtotime($owner['latest_submission']))); ?></div>
                                <?php if($owner['phone']): ?>
                                <div class="tag resident"><?php echo h($owner['phone']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Recent Pending Requests -->
            <div class="card">
                <div class="card-title">Latest pickup requests</div>

                <div class="scroll-container">
                    <?php if(!$requests): ?>
                        <div class="empty">No pending requests 📭</div>
                    <?php else: ?>
                        <?php foreach($requests as $r): 
                            // Get initials for avatar
                            $initials = '';
                            if ($r['resident_name']) {
                                $nameParts = explode(' ', $r['resident_name']);
                                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                            } else {
                                $initials = 'U';
                            }
                        ?>
                        <div class="request-item">
                            <div class="request-header">
                                <div class="request-info">
                                    <div class="request-avatar">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div class="request-details">
                                        <h4><?php echo h($r['resident_name'] ?: 'Resident'); ?></h4>
                                        <div class="request-email"><?php echo h($r['resident_email'] ?: 'No email provided'); ?></div>
                                        <div class="request-address">
                                            📍 <?php echo h($r['address'] ?: 'No address provided'); ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="badge badge-pending">
                                    Pending
                                </span>
                            </div>
                            <div class="request-meta">
                                <div class="meta-tag id">#<?php echo (int)$r['id']; ?></div>
                                <div class="meta-tag date"><?php echo h(date('M j, Y', strtotime($r['created_at']))); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Full Width: Customer Feedback -->
        <div class="card full-width">
            <div class="card-title">Recent Customer Feedback</div>
            <div class="scroll-container">
                <?php if(!$feedback): ?>
                    <div class="empty">No feedback yet 💬</div>
                <?php else: ?>
                    <?php foreach($feedback as $fb): ?>
                    <div class="feedback-item">
                        <div>
                            <div><strong><?php echo h($fb['resident_name'] ?: 'Resident'); ?></strong> • 
                                <span style="color: #ff9800;">
                                    <?php for($i = 0; $i < (int)$fb['rating']; $i++): ?>★<?php endfor; ?>
                                    <?php for($i = (int)$fb['rating']; $i < 5; $i++): ?>☆<?php endfor; ?>
                                </span>
                            </div>
                            <div class="feedback-text"><?php echo nl2br(h($fb['comments'] ?: 'No comment provided')); ?></div>
                            <div style="font-size: 12px; color: #999; margin-top: 8px;">
                                <?php echo h(date('M j, Y', strtotime($fb['created_at']))); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Councillor Registration Modal -->
    <div id="councillorModal" class="modal">
        <div class="modal-content">
            <!-- <div class="modal-header">
                <h2 class="modal-title">Register Councillor</h2>
                <span class="close" onclick="closeModal('councillorModal')">&times;</span>
            </div> -->
            <div class="modal-body">
                <?php if($successMsg && isset($_POST['role']) && $_POST['role'] === 'councillor'): ?>
                    <div class="alert alert-success"><?php echo h($successMsg); ?></div>
                <?php endif; ?>
                <?php if($errorMsg && isset($_POST['role']) && $_POST['role'] === 'councillor'): ?>
                    <div class="alert alert-error"><?php echo h($errorMsg); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="register_user" value="1">
                    <input type="hidden" name="role" value="councillor">
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-input" required placeholder="Enter full name" value="<?php echo isset($_POST['name']) && $_POST['role'] === 'councillor' ? h($_POST['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input" required placeholder="Enter email address" value="<?php echo isset($_POST['email']) && $_POST['role'] === 'councillor' ? h($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" placeholder="Enter phone number (optional)" value="<?php echo isset($_POST['phone']) && $_POST['role'] === 'councillor' ? h($_POST['phone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-input" 
       required placeholder="Minimum 8 chars, mix letters & numbers"
       minlength="8"
       pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}"
       title="Password must be at least 8 characters and include uppercase, lowercase letters and a number">

                    </div>
                    <div class="form-group">
    <label class="form-label">Ward <span class="required">*</span></label>
    <select name="ward_id" class="form-input" required>
        <option value="">Select ward</option>
        <?php foreach ($wards as $w): ?>
            <option value="<?= (int)$w['id'] ?>">
                <?= h($w['municipal'] . ' - ' . $w['ward_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                    
                    <button type="submit" class="submit-btn">Register Councillor</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Owner Registration Modal -->
    <div id="ownerModal" class="modal">
        <div class="modal-content">
            <!-- <div class="modal-header owner">
                <h2 class="modal-title">Register Truck Owner</h2>
                <span class="close" onclick="closeModal('ownerModal')">&times;</span>
            </div> -->
            <div class="modal-body">
                <?php if($successMsg && isset($_POST['role']) && $_POST['role'] === 'owner'): ?>
                    <div class="alert alert-success"><?php echo h($successMsg); ?></div>
                <?php endif; ?>
                <?php if($errorMsg && isset($_POST['role']) && $_POST['role'] === 'owner'): ?>
                    <div class="alert alert-error"><?php echo h($errorMsg); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="register_user" value="1">
                    <input type="hidden" name="role" value="owner">
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-input" required placeholder="Enter full name" value="<?php echo isset($_POST['name']) && $_POST['role'] === 'owner' ? h($_POST['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input" required placeholder="Enter email address" value="<?php echo isset($_POST['email']) && $_POST['role'] === 'owner' ? h($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" placeholder="Enter phone number (optional)" value="<?php echo isset($_POST['phone']) && $_POST['role'] === 'owner' ? h($_POST['phone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-input" 
       required placeholder="Minimum 8 chars, mix letters & numbers"
       minlength="8"
       pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}"
       title="Password must be at least 8 characters and include uppercase, lowercase letters and a number">

                    </div>
                    
                    <button type="submit" class="submit-btn">Register Truck Owner</button>
                </form>
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

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
            closeMenu(); // Close sidebar when opening modal
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const councillorModal = document.getElementById('councillorModal');
            const ownerModal = document.getElementById('ownerModal');
            
            if (event.target == councillorModal) {
                closeModal('councillorModal');
            }
            if (event.target == ownerModal) {
                closeModal('ownerModal');
            }
        }

        // Auto-open modal if there's an error or success message for that role
        // Auto-open modal again ONLY if there is an error (so user can fix form)
<?php if($errorMsg && isset($_POST['role'])): ?>
    <?php if($_POST['role'] === 'councillor'): ?>
        openModal('councillorModal');
    <?php elseif($_POST['role'] === 'owner'): ?>
        openModal('ownerModal');
    <?php endif; ?>
<?php endif; ?>


        // Close alert messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>