<?php
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['municipal_admin','administrator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name'] ?? '');
  $email     = trim($_POST['email'] ?? '');
  $phone     = trim($_POST['phone'] ?? '');
  $role      = trim($_POST['role'] ?? '');
  $ward_id   = $_POST['ward_id'] ?? null;
  $password  = $_POST['password'] ?? '';

  if (!in_array($role, ['truck_owner','councillor'], true)) {
    $_SESSION['flash_error'] = 'Role must be Truck Owner or Councillor.';
    header('Location: municipal-add-user.php'); exit;
  }
  if (!$full_name || !$email || !$phone || !$password) {
    $_SESSION['flash_error'] = 'All fields are required.';
    header('Location: municipal-add-user.php'); exit;
  }

  $password_hash = password_hash($password, PASSWORD_DEFAULT);

  $stmt = $conn->prepare("INSERT INTO users(full_name,email,phone,password_hash,role,is_active,created_by,created_by_role,ward_id) VALUES(?,?,?,?,?,1,?,? ,?)");
  $created_by = $_SESSION['user_id'];
  $created_by_role = $_SESSION['role'];
  $stmt->bind_param('sssssiis', $full_name,$email,$phone,$password_hash,$role,$created_by,$created_by_role,$ward_id);
  if ($stmt->execute()) {
    $_SESSION['flash_success'] = ucfirst(str_replace('_',' ',$role)).' created.';
  } else {
    $_SESSION['flash_error'] = 'Could not create user.';
  }
  header('Location: municipal-add-user.php'); exit;
}
?>
<!-- Simple form -->
<!doctype html><html><body>
<h3>Create Truck Owner / Councillor</h3>
<form method="post">
  <input name="full_name" placeholder="Full name" required>
  <input type="email" name="email" placeholder="Email" required>
  <input name="phone" placeholder="Phone" required>
  <select name="role" required>
    <option value="">-- Select role --</option>
    <option value="truck_owner">Truck Owner</option>
    <option value="councillor">Councillor</option>
  </select>
  <input name="ward_id" placeholder="Ward ID (for councillor)">
  <input type="password" name="password" placeholder="Temp password" required>
  <button type="submit">Create</button>
</form>
<p><a href="/public/municipal-dashboard.php">← Back to Municipal Dashboard</a></p>
</body></html>
