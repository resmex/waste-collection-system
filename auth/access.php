<?php
// C:\xampp\htdocs\e-waste\auth\access.php
// Redirect users to their area (placeholder since login is excluded).

require_once __DIR__ . '/../includes/auth-check.php';

$role = $_SESSION['role'] ?? 'guest';

$map = [
  'resident'    => '/e-waste/public/resident-dashboard.php',
  'driver'      => '/e-waste/public/driver-dashboard.php',
  'municipal'   => '/e-waste/public/admin-dashboard.php',
  'admin'       => '/e-waste/public/system-admin.html',
  'councillor'  => '/e-waste/public/councillor-dashboard.php',
  'owner' => '/e-waste/public/owner-dashboard.php',
];

$dest = $map[$role] ?? '/e-waste/public/resident-dashboard.php';
header("Location: $dest");
exit;
