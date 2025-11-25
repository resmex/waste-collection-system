<?php
// api/notifications.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $userID = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($authUser['userID'] ?? 0);
    if (!$userID) respond(['ok'=>false,'message'=>'user_id required'],422);

    $rows = [];
    $res = $mysqli->query("SELECT notificationID, title, message, type, related_id, is_read, created_at
                           FROM notifications
                           WHERE userID={$userID}
                           ORDER BY notificationID DESC
                           LIMIT 50");
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    respond(['ok'=>true,'data'=>$rows]);
}

if ($method === 'POST') {
    $in = json_input();
    required($in, ['notification_id','is_read']);
    $id = (int)$in['notification_id'];
    $is = (int)$in['is_read'];

    $upd = $mysqli->prepare("UPDATE notifications SET is_read=? WHERE notificationID=?");
    $upd->bind_param('ii',$is,$id);
    if (!$upd->execute()) respond(['ok'=>false,'message'=>'Update failed','error'=>$upd->error],500);
    respond(['ok'=>true,'message'=>'Notification updated']);
}

respond(['ok'=>false,'message'=>'Method not allowed'],405);
