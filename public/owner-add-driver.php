<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['truck_owner']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name'] ?? '');
  $email     = trim($_POST['email'] ?? '');
  $phone     = trim($_POST['phone'] ?? '');
  $password  = $_POST['password'] ?? '';

  if (!$full_name || !$email || !$phone || !$password) {
    $_SESSION['flash_error'] = 'All fields required.';
    header('Location: owner-add-driver.php'); exit;
  }

  $password_hash = password_hash($password, PASSWORD_DEFAULT);
  $owner_id = $_SESSION['user_id'];
  $created_by = $owner_id;
  $created_by_role = 'truck_owner';

 $stmt = $conn->prepare("
  INSERT INTO users (full_name,email,phone,password_hash,role,is_active,owner_id,created_by,created_by_role)
  VALUES (?,?,?,?,'driver',1,?,?,?)
");
$owner_id = $_SESSION['user_id'];
$created_by = $owner_id;
$created_by_role = 'truck_owner';
$stmt->bind_param('ssssiss', $full_name, $email, $phone, $password_hash, $owner_id, $created_by, $created_by_role);


  if ($stmt->execute()) {
    $_SESSION['flash_success'] = 'Driver created.';
  } else {
    $_SESSION['flash_error'] = 'Could not create driver.';
  }
  header('Location: owner-add-driver.php'); exit;
}
?>
<!doctype html><html><body>
<h3>Add Driver</h3>
<form method="post">
  <input name="full_name" placeholder="Full name" required>
  <input type="email" name="email" placeholder="Email" required>
  <input name="phone" placeholder="Phone" required>
  <input type="password" name="password" placeholder="Temp password" required>
  <button type="submit">Create Driver</button>
</form>
<p><a href="/public/owner-dashboard.php">← Back to Owner Dashboard</a></p>
</body></html>
