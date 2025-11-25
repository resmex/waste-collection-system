<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/auth-check.php';
require_login(['owner']); // admin bypass

$owner_id = (int)($_SESSION['user_id'] ?? 0);
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$pass     = $_POST['password'] ?? '';

if(!$owner_id || $name==='' || $email==='' || $phone==='' || $pass===''){
  echo "<script>alert('All fields are required.');history.back();</script>"; exit;
}
if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
  echo "<script>alert('Invalid email.');history.back();</script>"; exit;
}

// unique email
$stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$stmt->bind_param('s', $email); $stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0) { echo "<script>alert('Email already used.');history.back();</script>"; $stmt->close(); exit; }
$stmt->close();

// optional unique phone
$stmt = $conn->prepare("SELECT id FROM users WHERE phone=? LIMIT 1");
$stmt->bind_param('s', $phone); $stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0) { echo "<script>alert('Phone already used.');history.back();</script>"; $stmt->close(); exit; }
$stmt->close();

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (name, email, phone, password_hash, role, owner_id, status) VALUES (?, ?, ?, ?, 'driver', ?, 'active')");
$stmt->bind_param('ssssi', $name, $email, $phone, $hash, $owner_id);
if ($stmt->execute()) {
  echo "<script>alert('Driver added.');location.href='../public/owner-drivers.php';</script>";
} else {
  echo "<script>alert('Database error.');history.back();</script>";
}
$stmt->close();
