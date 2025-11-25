<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } // Utility function

$fullName = $_SESSION['full_name'] ?? 'Resident';
$email = $_SESSION['email'] ?? 'user@example.com'; 

/** * Logic to determine the Profile Image URL
 * Base Path: C:\xampp\htdocs\e-waste\uploads\profiles 
 * Web Path: /e-waste/uploads/profiles/
 **/
$profile_dir_web = '/e-waste/uploads/profiles/';
$default_img_web = '/e-waste/assets/images/profile.png'; 
// Assuming $_SESSION['profile_image'] holds the filename (e.g., 'user_123.jpg')
$profile_image_filename = $_SESSION['profile_image'] ?? ''; 
$profile_image_url = empty($profile_image_filename) 
    ? $default_img_web 
    : $profile_dir_web . h($profile_image_filename);

?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/e-waste/assets/css/header.css">
<link rel="stylesheet" href="/e-waste/assets/css/resident.css">

<div class="header">
    <div class="header-left">
        <div class="menu-icon">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <div class="welcome-text">Hi, <strong><?= h($fullName) ?></strong></div>
    </div>
    
    <div class="header-right">
        <div class="notification-badge" title="Notifications">
            <i class="fa-solid fa-bell"></i>
            <div class="notification-count">6</div>
        </div>

        <div class="profile-dropdown-container">
            <div class="profile-pic-trigger">
                <img src="<?= $profile_image_url ?>" alt="<?= h($fullName) ?>'s Profile" class="profile-pic">
            </div>
            
            <div class="profile-menu">
                <div class="menu-header">
                    <img src="<?= $profile_image_url ?>" alt="Profile" class="menu-profile-pic">
                    <p class="menu-name"><?= h($fullName) ?></p>
                    <p class="menu-email"><?= h($email) ?></p>
                </div>
                <ul>
                    <li><a href="/e-waste/resident/profile.php"><i class="fa-solid fa-user-gear"></i> Edit Profile</a></li>
                    
                    <li><a href="/e-waste/resident/profile.php?tab=photo"><i class="fa-solid fa-image"></i> Upload Photo</a></li>
                    
                    <li><a href="/e-waste/resident/settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
                    
                    <li class="separator"></li>
                    
                    <li><a href="/e-waste/auth/logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
                </ul>
            </div>
        </div>
        </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const trigger = document.querySelector('.profile-pic-trigger');
        const menu = document.querySelector('.profile-menu');
        
        // Toggle the menu visibility on click
        trigger.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent document click from closing it immediately
            menu.classList.toggle('show');
        });
        
        // Close the menu if the user clicks outside of it
        document.addEventListener('click', function(event) {
            const container = document.querySelector('.profile-dropdown-container');
            if (container && !container.contains(event.target)) {
                menu.classList.remove('show');
            }
        });
    });
</script>