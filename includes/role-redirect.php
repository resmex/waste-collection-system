<?php
function role_redirect(string $role): void {
  switch ($role) {
    case 'administrator':     header('Location: /public/system-admin.php'); break;
    case 'municipal_admin':   header('Location: /public/admin-dashboard.php'); break;
    case 'councillor':        header('Location: /public/councillor-dashboard.php'); break;
    case 'truck_owner':       header('Location: /public/owner-dashboard.php'); break;
    case 'driver':            header('Location: /public/driver-dashboard.php'); break;
    default:                  header('Location: /public/resident-dashboard.php'); break;
  }
  exit;
}
