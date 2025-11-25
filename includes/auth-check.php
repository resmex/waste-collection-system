<?php
// includes/auth-check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Normalize session keys so the rest of the app can rely on them.
 * If you previously stored 'name', keep it, but also mirror to 'full_name'.
 */
if (!isset($_SESSION['full_name']) && isset($_SESSION['name'])) {
    $_SESSION['full_name'] = $_SESSION['name'];
}

// Expose convenient variables (optional)
$currentUserId   = $_SESSION['user_id']   ?? null;
$currentUserName = $_SESSION['full_name'] ?? ($_SESSION['name'] ?? null);
$currentRole     = $_SESSION['role']      ?? null;
$currentWardId   = $_SESSION['ward_id']   ?? null;


/**
 * Primary guard. If $roles is provided, enforce membership.
 * Administrators bypass role checks.
 */
function require_login(array $roles = []) {
    // must re-read from $_SESSION to avoid globals requirement
    $uid  = $_SESSION['user_id']   ?? null;
    $role = $_SESSION['role']      ?? null;

    if (!$uid) {
        // Use a single canonical login path
        header('Location: ../auth/login.html');
        exit;
    }

    // SYSTEM ADMIN BYPASS: full access
    if ($role === 'administrator') {
        return;
    }

    if ($roles && !in_array($role, $roles, true)) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
}

/**
 * Backward/compat shim: some pages call require_role([...])
 * Delegate to require_login([...]).
 */
function require_role(array $roles) {
    require_login($roles);
}
