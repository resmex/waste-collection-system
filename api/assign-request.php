<?php
require_once __DIR__ . '/../includes/db.php';
@require_once __DIR__ . '/../includes/auth-check.php';

$request_id  = (int)($_POST['request_id'] ?? 0);
$owner_id    = isset($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
$driver_id   = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : null;
$assigned_by = isset($currentUserId) ? (int)$currentUserId : null;

$error   = '';
$success = '';

if (!$request_id) {
    $error = 'Request ID is required.';
} else {

   
    if ($driver_id) {

        // (A) Check if this driver already has an in-progress trip
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS c
            FROM requests r
            JOIN assignments a ON a.request_id = r.id
            WHERE a.driver_id = ?
              AND r.status = 'in_progress'
        ");
        $stmt->bind_param('i', $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        if (!empty($row['c']) && (int)$row['c'] > 0) {
            $error = 'This driver already has an active trip in progress. Complete it before assigning a new one.';
        }

        // (B) Check if this request is already assigned to a driver
        if (!$error) {
            $stmt = $conn->prepare("
                SELECT 1 
                FROM assignments 
                WHERE request_id = ? 
                  AND driver_id IS NOT NULL
                LIMIT 1
            ");
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $alreadyAssigned = (bool)$result->fetch_row();
            $stmt->close();

            if ($alreadyAssigned) {
                $error = 'This request is already assigned to a driver.';
            }
        }
    }

   
    if (!$error) {
        $stmt = $conn->prepare("
            INSERT INTO assignments (request_id, owner_id, driver_id, assigned_by) 
            VALUES (?,?,?,?)
        ");
        $stmt->bind_param('iiii', $request_id, $owner_id, $driver_id, $assigned_by);

        if ($stmt->execute()) {
            // If driver set, request becomes pending for that driver
            if ($driver_id) {
                $reqId = (int)$request_id;
                $conn->query("UPDATE requests SET status = 'pending' WHERE id = {$reqId}");
            }
            $success = 'Assignment completed successfully!';
        } else {
            $error = 'Failed to create assignment. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Request - E-Waste System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <h1>Request Assignment</h1>
            <p class="subtitle">Assign waste collection requests</p>
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
                <?php endif; ?>

                <div class="btn-group" style="margin-top: 24px;">
                    <a href="../public/admin-dashboard.php" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if($success): ?>
    <script>
        setTimeout(function() {
            window.location.href = '../public/admin-dashboard.php';
        }, 2000);
    </script>
    <?php endif; ?>
</body>
</html>
