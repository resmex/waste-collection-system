<?php

session_start();
require_once __DIR__ . '/../includes/db.php';     
require_once __DIR__ . '/../includes/auth-check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$password   = $_POST['password'] ?? '';

if ($identifier === '' || $password === '') {
    echo "<script>alert('Enter email/phone and password.'); window.history.back();</script>";
    exit;
}


$isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
if ($isEmail) {
    $stmt = $conn->prepare("
        SELECT id, name, email, phone, password_hash, role, status, ward_id
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT id, name, email, phone, password_hash, role, status, ward_id
        FROM users
        WHERE phone = ?
        LIMIT 1
    ");
}

if (!$stmt) {
    // SQL prepare failure
    echo "<script>alert('Server error. Try again.'); window.history.back();</script>";
    exit;
}

$stmt->bind_param('s', $identifier);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $user = $res->fetch_assoc()) {

    // account status check
    if (strtolower($user['status']) !== 'active') {
        echo "<script>alert('Your account is deactivated. Contact admin.'); window.history.back();</script>";
        exit;
    }

    if (password_verify($password, $user['password_hash'])) {
        // success: set session; keep names compatible with your older code
        session_regenerate_id(true);

        $_SESSION['user_id']   = (int)$user['id'];        // preferred key
        $_SESSION['name']      = $user['name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['phone']     = $user['phone'];
        $_SESSION['ward_id']   = $user['ward_id'];

        
        $_SESSION['userID']    = (int)$user['id'];
        $_SESSION['full_name'] = $user['name'];


        $roleRedirects = [
            'resident'        => '../public/resident-dashboard.php',
            'driver'          => '../public/driver-dashboard.php',
            'owner'           => '../public/owner-dashboard.php',  
            'councillor'      => '../public/councillor-dashboard.php',
            'municipal_admin' => '../public/admin-dashboard.php',
            'administrator'   => '../public/system-admin.php',        // system admin: full access
        ];

        $role = $user['role'];
        $redirectURL = $roleRedirects[$role] ?? '../users/default.html';
        header("Location: $redirectURL");
        exit;

    } else {
        echo "<script>alert('Incorrect password. Please try again.'); window.history.back();</script>";
        exit;
    }
} else {
    echo "<script>alert('No account found with that email or phone number.'); window.history.back();</script>";
    exit;
}

$stmt->close();
$conn->close();
