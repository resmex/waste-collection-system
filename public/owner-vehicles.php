<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
require_login(['owner']);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$ownerId = (int)($_SESSION['user_id'] ?? 0);

// Define vehicle types with their capacity ranges
$vehicleTypes = [
    'motorcycle_tuk' => [
        'name' => 'Motorcycles & Tuk-tuks (Bajaji)',
        'capacity_min' => 50,
        'capacity_max' => 100
    ],
    'pickup_truck' => [
        'name' => 'Pickup Trucks',
        'capacity_min' => 500,
        'capacity_max' => 1000
    ],
    'light_lorry' => [
        'name' => 'Light-Duty Lorries',
        'capacity_min' => 1000,
        'capacity_max' => 3000
    ],
    'tricycle_pushcart' => [
        'name' => 'Tricycles & Pushcarts',
        'capacity_min' => 20,
        'capacity_max' => 50
    ],
    'waste_van' => [
        'name' => 'Specialized Waste Vans',
        'capacity_min' => 800,
        'capacity_max' => 1500
    ]
];

$rows = [];
$res = $conn->query("SELECT id, plate_no, vehicle_type, capacity_kg, status, doc_url, vehicle_pic, created_at, rejection_reason FROM vehicles WHERE owner_id={$ownerId} ORDER BY created_at DESC");
while($res && $row = $res->fetch_assoc()) $rows[] = $row;
if($res) $res->close();

// Get statistics
$stats = [
    'total' => count($rows),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];
foreach($rows as $row) {
    if(isset($stats[$row['status']])) {
        $stats[$row['status']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vehicles - E-Waste System</title>
    <link rel="stylesheet" href="../assets/css/owner.css">
    <style>
   
    </style>
</head>
<body>
    <!-- Your existing PHP/HTML content remains the same -->
    <!-- Only the CSS has been updated to match owner.css styling -->
    <div class="page-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>My Vehicles</h1>
            <p class="subtitle">Manage your waste collection fleet</p>
        </div>

        <!-- Breadcrumb -->
        <!-- <div class="breadcrumb">
            <a href="owner-dashboard.php">Dashboard</a>
            <span>›</span>
            <span>My Vehicles</span>
        </div> -->

        <!-- Main Content -->
        <div class="content-wrapper">
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Vehicles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #d97706;"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #48bb78;"><?php echo $stats['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <!-- <div class="stat-card">
                    <div class="stat-value" style="color: #e53e3e;"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div> -->
            </div>

            <!-- Upload Form -->
            <div class="upload-section">
                <h3 style="margin-bottom: 16px; color: #2d3748; font-size: 16px; font-weight: 600;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"></path>
                        <circle cx="6.5" cy="16.5" r="2.5"></circle>
                        <circle cx="16.5" cy="16.5" r="2.5"></circle>
                    </svg>
                    Register New Vehicle
                </h3>
                <form method="post" action="../api/vehicle-upload.php" enctype="multipart/form-data" id="vehicleForm">
                    <div class="form-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="plate_no" style="font-size: 13px;">Vehicle Plate Number <span style="color: #e53e3e;">*</span></label>
                            <input type="text" id="plate_no" name="plate_no" class="form-control" placeholder="e.g., T123ABC" required>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="vehicle_type" style="font-size: 13px;">Vehicle Type <span style="color: #e53e3e;">*</span></label>
                            <select id="vehicle_type" name="vehicle_type" class="form-control" required>
                                <option value="">-- Select Vehicle Type --</option>
                                <?php foreach($vehicleTypes as $key => $type): ?>
                                <option value="<?php echo h($key); ?>" 
                                        data-capacity="<?php echo $type['capacity_min']; ?>-<?php echo $type['capacity_max']; ?>">
                                    <?php echo h($type['name']); ?> (<?php echo $type['capacity_min']; ?>-<?php echo $type['capacity_max']; ?> kg)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="file-upload-area">
                        <label style="font-size: 13px; margin-bottom: 8px; display: block;">
                            Vehicle Documents <span style="color: #e53e3e;">*</span>
                            <span style="color: #718096; font-weight: 400;">(Registration, Insurance, etc.)</span>
                        </label>
                        <input type="file" id="doc" name="doc" class="file-input-hidden" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="file-upload-box" onclick="document.getElementById('doc').click()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="12" y1="18" x2="12" y2="12"></line>
                                <line x1="9" y1="15" x2="15" y2="15"></line>
                            </svg>
                            <div style="margin-top: 12px; font-weight: 600; color: #667eea;">
                                Click to upload documents
                            </div>
                            <div style="margin-top: 4px; font-size: 13px; color: #718096;">
                                PDF, JPG, JPEG or PNG (Max 10MB)
                            </div>
                        </div>
                        <div id="docFiles" class="selected-files"></div>
                    </div>

                    <div class="file-upload-area" style="margin-top: 16px;">
                        <label style="font-size: 13px; margin-bottom: 8px; display: block;">
                            Vehicle Pictures <span style="color: #e53e3e;">*</span>
                            <span style="color: #718096; font-weight: 400;">(Multiple images allowed - all angles)</span>
                        </label>
                        <input type="file" id="vehicle_pics" name="vehicle_pics[]" class="file-input-hidden" accept="image/*" multiple required>
                        <div class="file-upload-box" onclick="document.getElementById('vehicle_pics').click()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                            <div style="margin-top: 12px; font-weight: 600; color: #667eea;">
                                Click to upload vehicle photos
                            </div>
                            <div style="margin-top: 4px; font-size: 13px; color: #718096;">
                                JPG, JPEG, PNG, GIF (Any size/dimension - Multiple files allowed)
                            </div>
                        </div>
                        <div id="picFiles" class="selected-files"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 16px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        Submit for Approval
                    </button>
                </form>
            </div>

            <!-- Vehicles List -->
            <h3 style="margin-bottom: 16px; color: #2d3748; font-size: 18px; font-weight: 600;">
                <?php echo $stats['total']; ?> Vehicle<?php echo $stats['total'] !== 1 ? 's' : ''; ?> in Your Fleet
            </h3>

            <?php if(!$rows): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <h3>No Vehicles Yet</h3>
                    <p>Register your first vehicle using the form above</p>
                </div>
            <?php else: ?>
                <?php foreach($rows as $v): 
                    $badgeClass = 'badge-pending';
                    if($v['status'] === 'approved') $badgeClass = 'badge-approved';
                    elseif($v['status'] === 'rejected') $badgeClass = 'badge-rejected';
                    
                    $vehicleTypeName = 'Unknown Type';
                    $capacityRange = '';
                    if($v['vehicle_type'] && isset($vehicleTypes[$v['vehicle_type']])) {
                        $vehicleTypeName = $vehicleTypes[$v['vehicle_type']]['name'];
                        $capacityRange = $vehicleTypes[$v['vehicle_type']]['capacity_min'] . '-' . $vehicleTypes[$v['vehicle_type']]['capacity_max'] . ' kg';
                    }
                    
                    // Parse vehicle pictures (stored as JSON array)
                    $vehiclePics = [];
                    if($v['vehicle_pic']) {
                        $vehiclePics = json_decode($v['vehicle_pic'], true) ?: [];
                    }
                ?>
                <div class="vehicle-card">
                    <div class="vehicle-header">
                        <div style="flex: 1;">
                            <div class="vehicle-plate">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                                    <path d="M14 16H9m10 0h3v-3.15a1 1 0 0 0-.84-.99L16 11l-2.7-3.6a1 1 0 0 0-.8-.4H5.24a2 2 0 0 0-1.8 1.1l-.8 1.63A6 6 0 0 0 2 12.42V16h2"></path>
                                    <circle cx="6.5" cy="16.5" r="2.5"></circle>
                                    <circle cx="16.5" cy="16.5" r="2.5"></circle>
                                </svg>
                                <?php echo h($v['plate_no']); ?>
                            </div>
                            <div class="vehicle-type-badge">
                                <?php echo h($vehicleTypeName); ?>
                            </div>
                            <div class="capacity-info">
                                Capacity Range: <strong><?php echo h($capacityRange); ?></strong>
                            </div>
                            <div style="color: #718096; font-size: 13px; margin-top: 8px;">
                                Registered: <?php echo h(date('F j, Y', strtotime($v['created_at']))); ?>
                            </div>
                        </div>
                        <span class="badge <?php echo $badgeClass; ?>">
                            <?php echo h(ucfirst($v['status'])); ?>
                        </span>
                    </div>

                    <?php if($v['status'] === 'rejected' && $v['rejection_reason']): ?>
                    <div class="rejection-reason">
                        <strong>Rejection Reason:</strong> <?php echo h($v['rejection_reason']); ?>
                    </div>
                    <?php endif; ?>

                    <?php if($vehiclePics): ?>
                    <div style="margin-top: 16px;">
                        <div style="font-size: 13px; color: #718096; font-weight: 600; margin-bottom: 8px;">
                            VEHICLE PHOTOS
                        </div>
                        <div class="vehicle-images">
                            <?php foreach($vehiclePics as $pic): ?>
                            <div class="vehicle-image" onclick="window.open('../<?php echo h($pic); ?>', '_blank')">
                                <img src="../<?php echo h($pic); ?>" alt="Vehicle Photo">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($v['doc_url']): ?>
                    <a href="../<?php echo h($v['doc_url']); ?>" target="_blank" class="document-link">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        View Registration Document
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Handle document file selection
        document.getElementById('doc').addEventListener('change', function(e) {
            const files = e.target.files;
            const container = document.getElementById('docFiles');
            const uploadBox = this.previousElementSibling;
            
            if(files.length > 0) {
                uploadBox.classList.add('has-files');
                container.innerHTML = '<div class="file-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#48bb78" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg><strong>' + files[0].name + '</strong></div>';
            }
        });

        // Handle vehicle pictures selection
        document.getElementById('vehicle_pics').addEventListener('change', function(e) {
            const files = e.target.files;
            const container = document.getElementById('picFiles');
            const uploadBox = this.previousElementSibling;
            
            if(files.length > 0) {
                uploadBox.classList.add('has-files');
                let html = '<div style="font-weight: 600; margin-bottom: 8px;">' + files.length + ' image(s) selected:</div>';
                for(let i = 0; i < files.length; i++) {
                    html += '<div class="file-item"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#48bb78" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>' + files[i].name + '</div>';
                }
                container.innerHTML = html;
            }
        });

        // Form submission
        document.getElementById('vehicleForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span> Uploading...';
        });
    </script>
</body>
</html>