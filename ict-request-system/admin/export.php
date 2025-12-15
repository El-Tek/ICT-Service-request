<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$params = [];
$where = [];
if ($search !== '') {
  $where[] = "(ticket_id LIKE ? OR fullname LIKE ? OR office LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
}
if ($status !== '' && in_array($status, ['Pending','In Progress','Completed'])) {
  $where[] = "status = ?";
  $params[] = $status;
}
$sql = "SELECT ticket_id, fullname, phone, email, office, request_type, priority, status, created_at FROM requests";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY created_at DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="kstu-ict-requests.csv"');

$out = fopen('php://output', 'w');
fputcsv(out: $out, fields: ['Ticket ID','Full Name','Phone','Email','Office','Type','Priority','Status','Created At']);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, $row);
}
fclose($out);
exit;
