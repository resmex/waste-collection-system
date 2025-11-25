<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_login(['owner']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ownerId = (int)($_SESSION['user_id'] ?? 0);

$successMsg = '';
$errorMsg   = '';

// If redirected after successful creation
if (!empty($_GET['created']) && $_GET['created'] === 'driver') {
    $successMsg = "Driver registered successfully!";
}

/**
 * Handle driver registration (same style as admin add councillor/owner)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_driver'])) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($ownerId <= 0) {
        $errorMsg = "You are not allowed to register drivers.";
    } elseif ($name === '' || $email === '' || $phone === '' || $password === '') {
        $errorMsg = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Invalid email format.";
    }
    // strong password: min 8, upper + lower + number
    elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/\d/',   $password)
    ) {
        $errorMsg = "Password must be at least 8 characters and include uppercase, lowercase letters and a number.";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errorMsg = "Email already exists in the system.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $role   = 'driver';
            $status = 'active';

            $ins = $conn->prepare("
                INSERT INTO users (name, email, phone, password_hash, role, owner_id, status)
                VALUES (?,?,?,?,?,?,?)
            ");
            $ins->bind_param('sssssis', $name, $email, $phone, $hashed, $role, $ownerId, $status);

            if ($ins->execute()) {
                $ins->close();
                $check->close();
                // ✅ redirect to avoid resubmission on refresh
                header('Location: ' . $_SERVER['PHP_SELF'] . '?created=driver');
                exit;
            } else {
                $errorMsg = "Error creating driver. Please try again.";
            }
            $ins->close();
        }
        $check->close();
    }
}

// Fetch drivers + stats (unchanged)
$drivers = [];
$sql = "
  SELECT u.id, u.name, u.email, u.phone, u.status,
         -- all-time totals
         (SELECT COUNT(*) FROM assignments a JOIN requests r ON r.id=a.request_id WHERE a.driver_id=u.id) AS total_trips,
         (SELECT COUNT(*) FROM assignments a JOIN requests r ON r.id=a.request_id WHERE a.driver_id=u.id AND r.status='completed') AS total_completed,
         (SELECT ROUND(AVG(rating),1) FROM feedback f WHERE f.driver_id=u.id) AS avg_rating,
         -- last 30 days window
         (SELECT COUNT(*) FROM assignments a JOIN requests r ON r.id=a.request_id
           WHERE a.driver_id=u.id AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS m_trips,
         (SELECT COUNT(*) FROM assignments a JOIN requests r ON r.id=a.request_id
           WHERE a.driver_id=u.id AND r.status='completed' AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS m_completed
  FROM users u
  WHERE u.role='driver' AND u.owner_id={$ownerId}
  ORDER BY u.name ASC";
$res = $conn->query($sql);
while ($res && $row = $res->fetch_assoc()) {
  $row['progress_percent'] = 0;
  if ((int)$row['m_trips'] > 0) {
    $row['progress_percent'] = (int) round(((int)$row['m_completed'] * 100) / (int)$row['m_trips']);
  } elseif ((int)$row['total_trips'] > 0) {
    $row['progress_percent'] = (int) round(((int)$row['total_completed'] * 100) / (int)$row['total_trips']);
  }
  $drivers[] = $row;
}
if ($res) $res->close();

// Summary stats
$totalDrivers   = count($drivers);
$activeDrivers  = count(array_filter($drivers, fn($d) => $d['status'] === 'active'));
$totalTrips     = array_sum(array_column($drivers, 'total_trips'));
$completedTrips = array_sum(array_column($drivers, 'total_completed'));
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Drivers - E-Waste System</title>
    <link rel="stylesheet" href="../assets/css/owner.css">
</head>
<body>
    <div class="container">
        <!-- Success/Error Messages -->
        <?php if($successMsg): ?>
            <div class="alert alert-success">
                <?= h($successMsg) ?>
            </div>
        <?php endif; ?>

        <?php if($errorMsg && !$successMsg): ?>
            <div class="alert alert-error">
                <?= h($errorMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="menu-icon">
                    <span></span><span></span><span></span>
                </div>
                <div class="welcome-text">Driver management</div>
            </div>
            <div class="header-right">
                <div class="notification-badge">
                    <div class="notification-count"><?php echo $totalDrivers; ?></div>
                </div>
                <div class="profile-pic"></div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total drivers</div>
                <div class="stat-value cyan"><?php echo $totalDrivers; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active drivers</div>
                <div class="stat-value green"><?php echo $activeDrivers; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total trips</div>
                <div class="stat-value orange"><?php echo $totalTrips; ?></div>
            </div>
            <!-- <div class="stat-card">
                <div class="stat-label">Completed Trips</div>
                <div class="stat-value cyan"><?php echo $completedTrips; ?></div>
            </div> -->
        </div>

        <!-- Add Driver Button -->
        <button class="add-driver-btn" onclick="openModal('driverModal')">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <line x1="19" y1="8" x2="19" y2="14"></line>
                <line x1="22" y1="11" x2="16" y2="11"></line>
            </svg>
            Register new driver
        </button>

        <!-- Drivers List -->
        <div class="card-title">
            <?php echo $totalDrivers; ?> Driver<?php echo $totalDrivers !== 1 ? 's' : ''; ?> in your fleet
        </div>

        <?php if (!$drivers): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3>No drivers yet</h3>
                <p>Register your first driver using the button above</p>
            </div>
        <?php else: ?>
            <?php foreach ($drivers as $d): 
                $pct = max(0, min(100, (int)$d['progress_percent']));
                $statusClass = $d['status'] === 'active' ? 'status-badge status-active' : 'status-badge status-suspended';
                $cardClass = $d['status'] === 'active' ? '' : 'inactive';
                
                // Get initials for avatar
                $initials = '';
                if ($d['name']) {
                    $nameParts = explode(' ', $d['name']);
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                } else {
                    $initials = 'D';
                }
            ?>
            <div class="driver-item <?php echo $cardClass; ?>">
                <!-- Header -->
                <div class="driver-header">
                    <div class="driver-info">
                        <div class="driver-avatar">
                            <?php echo $initials; ?>
                        </div>
                        <div class="driver-details">
                            <h4><?php echo h($d['name']); ?></h4>
                            <div class="driver-email"><?php echo h($d['email']); ?></div>
                            <div class="driver-contact">
                                <span class="contact-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    <?php echo h($d['phone']); ?>
                                </span>
                            </div>
                            <div class="driver-meta">
                                <div class="meta-tag id">#<?php echo (int)$d['id']; ?></div>
                                <?php if($d['avg_rating']): ?>
                                <div class="meta-tag rating">
                                    ★ <?php echo h($d['avg_rating']); ?> Rating
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="<?php echo $statusClass; ?>">
                        <?php echo h(ucfirst($d['status'])); ?>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-item-label">30-Day Trips</span>
                        <span class="stat-item-value" style="color: #667eea;">
                            <?php echo (int)$d['m_completed']; ?> / <?php echo (int)$d['m_trips']; ?>
                        </span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-item-label">All-Time Trips</span>
                        <span class="stat-item-value" style="color: #4caf50;">
                            <?php echo (int)$d['total_completed']; ?> / <?php echo (int)$d['total_trips']; ?>
                        </span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-item-label">Average Rating</span>
                        <span class="stat-item-value">
                            <?php if($d['avg_rating']): ?>
                            <span class="rating-display">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                </svg>
                                <?php echo h($d['avg_rating']); ?>
                            </span>
                            <?php else: ?>
                            <span style="color: #999; font-style: italic;">No ratings yet</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-section">
                    <div class="progress-label">
                        <span>Completion Rate </span>
                        <span style="font-weight: 700; color: #333;"><?php echo $pct; ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $pct; ?>%"></div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if ($d['status']==='active'): ?>
                    <form method="post" action="../api/driver-status.php" style="flex: 1;">
                        <input type="hidden" name="driver_id" value="<?php echo (int)$d['id']; ?>">
                        <input type="hidden" name="action" value="suspend">
                        <button type="submit" class="btn-warning"
                                onclick="return confirm('Are you sure you want to deactivate this driver?')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                            Deactivate Driver
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="post" action="../api/driver-status.php" style="flex: 1;">
                        <input type="hidden" name="driver_id" value="<?php echo (int)$d['id']; ?>">
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn-success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Activate Driver
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <!-- Additional action button for more options -->
                    <button type="button" class="btn" style="background: #2196F3; color: white; flex: 0.5;" 
                            onclick="alert('Driver details and history coming soon!')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        Details
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Driver Registration Modal -->
    <div id="driverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header driver">
                <h2 class="modal-title">Register Driver</h2>
                <span class="close" onclick="closeModal('driverModal')">&times;</span>
            </div>
            <div class="modal-body">
                <?php if($successMsg && isset($_POST['create_driver'])): ?>
                    <div class="alert alert-success"><?php echo h($successMsg); ?></div>
                <?php endif; ?>
                <?php if($errorMsg && isset($_POST['create_driver'])): ?>
                    <div class="alert alert-error"><?php echo h($errorMsg); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" autocomplete="off">
                    <input type="hidden" name="create_driver" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-input" required 
                               placeholder="Enter driver's full name" 
                               value="<?php echo isset($_POST['name']) ? h($_POST['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-input" required 
                               placeholder="driver@example.com" 
                               value="<?php echo isset($_POST['email']) ? h($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" class="form-input" required 
                               placeholder="+255 XXX XXX XXX" 
                               value="<?php echo isset($_POST['phone']) ? h($_POST['phone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Temporary Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-input" 
                               required placeholder="Minimum 8 chars, mix letters & numbers"
                               minlength="8"
                               pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}"
                               title="Password must be at least 8 characters and include uppercase, lowercase letters and a number">
                    </div>
                    
                    <button type="submit" class="submit-btn">Register Driver</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions - consistent with municipal admin
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const driverModal = document.getElementById('driverModal');
            if (event.target == driverModal) {
                closeModal('driverModal');
            }
        }

        // Auto-open modal if there's an error
        <?php if($errorMsg && isset($_POST['create_driver'])): ?>
            openModal('driverModal');
        <?php endif; ?>

        // Form submission loading states
        document.querySelector('#driverModal form')?.addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            if (!btn) return;
            btn.disabled = true;
            btn.innerHTML = 'Registering Driver...';
        });

        // Status toggle confirmation
        document.querySelectorAll('form[action*="driver-status"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                const action = this.querySelector('input[name="action"]').value;
                btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.219-8.56"></path></svg> ' + (action === 'suspend' ? 'Deactivating...' : 'Activating...');
            });
        });

        // Animate progress bars on load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.progress-bar-fill').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });

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