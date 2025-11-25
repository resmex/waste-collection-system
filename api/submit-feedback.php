<?php
require_once __DIR__ . '/../includes/db.php';
@require_once __DIR__ . '/../includes/auth-check.php';
header('Content-Type: text/html; charset=utf-8');

$request_id=(int)($_POST['request_id'] ?? 0);
$driver_id =(int)($_POST['driver_id'] ?? 0);
$rating    =(int)($_POST['rating'] ?? 0);
$comments  =trim($_POST['comments'] ?? '');
$resident_id = isset($currentUserId)? (int)$currentUserId : 0;

if(!$resident_id){ die('Not logged in'); }
if(!$request_id || $rating<1 || $rating>5){ die('invalid'); }

$stmt=$conn->prepare("INSERT INTO feedback (request_id, resident_id, driver_id, rating, comments) VALUES (?,?,?,?,?)");
$stmt->bind_param('iiiis',$request_id,$resident_id,$driver_id,$rating,$comments);
$stmt->execute(); $stmt->close();

echo "Thanks for your feedback. <a href=\"../public/resident-dashboard.php\">Back</a>";
