

<!-- ===========================================
     VEHICLE UPLOAD PAGE (vehicle-upload.php)
     =========================================== -->
<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
require_login(['owner']);

$owner_id = (int)($_SESSION['user_id'] ?? 0);
$plate_no = trim($_POST['plate_no'] ?? '');
$capacity = (int)($_POST['capacity_kg'] ?? 0);

$error = '';
$success = '';

if(!$owner_id || $plate_no===''){
    $error = 'Plate number is required.';
} elseif(!isset($_FILES['doc']) || $_FILES['doc']['error']!==UPLOAD_ERR_OK){
    $error = 'Please attach a valid document.';
} else {
    $allowed = ['pdf','jpg','jpeg','png'];
    $ext = strtolower(pathinfo($_FILES['doc']['name'], PATHINFO_EXTENSION));
    
    if(!in_array($ext,$allowed,true)){
        $error = 'Invalid file type. Only PDF, JPG, JPEG, and PNG are allowed.';
    } elseif($_FILES['doc']['size'] > 10*1024*1024){
        $error = 'File size must not exceed 10MB.';
    } else {
        $base = realpath(__DIR__ . '/..');
        $docDir = $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'doc';
        if(!is_dir($docDir)) { @mkdir($docDir, 0777, true); }

        $newName = 'veh_'.$owner_id.'_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
        $dest = $docDir . DIRECTORY_SEPARATOR . $newName;

        if(!move_uploaded_file($_FILES['doc']['tmp_name'], $dest)){
            $error = 'Failed to save document. Please try again.';
        } else {
            $relPath = 'uploads/doc/'.$newName;
            $stmt=$conn->prepare("INSERT INTO vehicles (owner_id, plate_no, capacity_kg, doc_url, status) VALUES (?,?,?,?, 'pending')");
            $stmt->bind_param('isis',$owner_id,$plate_no,$capacity,$relPath);
            
            if($stmt->execute()){
                $success = 'Vehicle uploaded successfully and pending approval!';
            } else {
                $error = 'Database error: ' . htmlspecialchars($conn->error);
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Upload - E-Waste System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <h1>Vehicle Upload</h1>
            <p class="subtitle">Submit your vehicle for approval</p>
        </div>

        <div class="content-wrapper">
            <div class="form-container">
                <?php if($error): ?>
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <?php if($success): ?>
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <div class="card-body">
                        <p style="margin-bottom: 12px;"><strong>What happens next?</strong></p>
                        <ul style="margin-left: 20px; line-height: 1.8; color: #4a5568;">
                            <li>Your vehicle will be reviewed by the municipal admin</li>
                            <li>You'll be notified once the review is complete</li>
                            <li>Approved vehicles can be assigned to waste collection requests</li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <div class="btn-group" style="margin-top: 24px;">
                    <a href="../public/owner-vehicles.php" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Back to My Vehicles
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php if($success): ?>
    <script>
        setTimeout(function() {
            window.location.href = '../public/owner-vehicles.php';
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>