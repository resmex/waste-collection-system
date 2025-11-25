<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
require_login(['owner']); // admin bypass

$owner_id = (int)($_SESSION['user_id'] ?? 0);
$driver_id = (int)($_POST['driver_id'] ?? 0);
$action = $_POST['action'] ?? '';

if(!$owner_id || !$driver_id || !in_array($action, ['suspend','activate'], true)){
  echo "<script>alert('Invalid input.');history.back();</script>"; exit;
}

// verify driver belongs to owner
$chk = $conn->query("SELECT id FROM users WHERE id={$driver_id} AND role='driver' AND owner_id={$owner_id} LIMIT 1");
if(!$chk || $chk->num_rows===0){ echo "<script>alert('Driver not found in your fleet.');history.back();</script>"; exit; }

$newStatus = ($action==='suspend') ? 'suspended' : 'active';
$conn->query("UPDATE users SET status='{$newStatus}' WHERE id={$driver_id}");
echo "<script>alert('Driver status updated.');location.href='../public/owner-drivers.php';</script>";
