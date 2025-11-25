<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: register.html'); exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$password  = $_POST['password'] ?? '';
$confirm   = $_POST['confirm_password'] ?? '';
$agree     = isset($_POST['agree']);

if (!$agree) { $_SESSION['flash_error'] = 'Please accept terms.'; header('Location: register.html'); exit; }
if ($password !== $confirm) { $_SESSION['flash_error'] = 'Passwords do not match.'; header('Location: register.html'); exit; }
if (!$full_name || !$email || !$phone) { $_SESSION['flash_error'] = 'All fields are required.'; header('Location: register.html'); exit; }

$allowed_role = 'resident'; 
$role = strtolower(trim($_POST['role'] ?? 'resident'));
if ($role !== $allowed_role) {

  $role = 'resident';
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Uniqueness checks
$stmt = $conn->prepare("SELECT id FROM users WHERE email=? OR phone=? LIMIT 1");
$stmt->bind_param('ss', $email, $phone);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
  $_SESSION['flash_error'] = 'Email or phone already in use.';
  header('Location: login.html'); exit;
}
$stmt->close();

// Insert
$stmt = $conn->prepare("
    INSERT INTO users (name, email, phone, password_hash, role) 
    VALUES (?, ?, ?, ?, 'resident')
");
if (!$stmt) {
    
}
$stmt->bind_param('ssss', $full_name, $email, $phone, $password_hash);
if (!$stmt->execute()) {
    $_SESSION['flash_error'] = 'Registration failed. Try again.';
    header('Location: register.html'); 
    exit;
}
$user_id = $stmt->insert_id;
$stmt->close();

// Auto-login after registration
$_SESSION['user_id'] = $user_id;
$_SESSION['name']    = $full_name;   // match how auth-check expects it
$_SESSION['role']    = 'resident';

require_once __DIR__ . '/../includes/role-redirect.php';
role_redirect('resident');


