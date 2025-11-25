<?php
session_start();

// Destroy all session data
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to login page
header("Location: /e-waste/auth/login.php");
exit;
