<?php // api/get-requests.php
require_once __DIR__.'/../includes/db.php';
header('Content-Type: application/json');

$ward_id = isset($_GET['ward_id']) ? intval($_GET['ward_id']) : null;
$status  = $_GET['status'] ?? null;  
$from    = $_GET['from'] ?? null;     
$to      = $_GET['to'] ?? null;       

$q = "SELECT r.*, a.owner_id, a.driver_id
      FROM requests r
      LEFT JOIN assignments a ON a.request_id=r.id
      WHERE 1=1";
if ($ward_id) $q .= " AND r.ward_id=$ward_id";
if ($status)  $q .= " AND r.status='".$conn->real_escape_string($status)."'";
if ($from)    $q .= " AND DATE(r.created_at) >= '".$conn->real_escape_string($from)."'";
if ($to)      $q .= " AND DATE(r.created_at) <= '".$conn->real_escape_string($to)."'";
$q .= " ORDER BY r.created_at DESC";

$res = $conn->query($q);
$out = [];
while ($row = $res->fetch_assoc()) $out[] = $row;
echo json_encode(['data'=>$out]);
