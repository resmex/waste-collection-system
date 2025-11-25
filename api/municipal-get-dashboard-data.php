<?php // api/municipal-get-dashboard-data.php
require_once __DIR__.'/../includes/db.php';
header('Content-Type: application/json');

$stats = [
  'totalTrips' => 0,
  'newRequests'=> 0,
  'pendingVeh' => 0,
];

$r1 = $conn->query("SELECT COUNT(*) c FROM requests");
$stats['totalTrips'] = ($r1 && ($row=$r1->fetch_assoc())) ? (int)$row['c'] : 0;

$r2 = $conn->query("SELECT COUNT(*) c FROM requests WHERE status='pending'");
$stats['newRequests'] = ($r2 && ($row=$r2->fetch_assoc())) ? (int)$row['c'] : 0;

$r3 = $conn->query("SELECT COUNT(*) c FROM vehicles WHERE status='pending'");
$stats['pendingVeh'] = ($r3 && ($row=$r3->fetch_assoc())) ? (int)$row['c'] : 0;

$reqs = [];
$q = $conn->query("SELECT id,resident_id,address,ward_id,status,created_at FROM requests ORDER BY created_at DESC LIMIT 50");
while($row=$q->fetch_assoc()) $reqs[]=$row;

$veh = [];
$v = $conn->query("SELECT id,owner_id,plate_no,capacity_kg,status FROM vehicles ORDER BY created_at DESC LIMIT 50");
while($row=$v->fetch_assoc()) $veh[]=$row;

$fdb = [];
$f = $conn->query("SELECT f.*, u.name AS resident_name
                   FROM feedback f
                   LEFT JOIN users u ON u.id=f.resident_id
                   ORDER BY f.created_at DESC LIMIT 50");
while($row=$f->fetch_assoc()) $fdb[]=$row;

echo json_encode([
  'stats'=>$stats,
  'requests'=>$reqs,
  'vehicles'=>$veh,
  'feedback'=>$fdb
]);
