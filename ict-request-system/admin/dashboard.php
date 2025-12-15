<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['admin'])) {
  header('Location: login.php');
  exit;
}
$csrf = $_SESSION['admin']['csrf'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
    http_response_code(403);
    die('Invalid CSRF token');
  }
  $ticket = trim($_POST['ticket_id'] ?? '');
  $status = trim($_POST['status'] ?? '');
  if ($ticket && in_array($status, ['Pending','In Progress','Completed'])) {
    $stmt = db()->prepare("UPDATE requests SET status=? WHERE ticket_id=?");
    $stmt->execute([$status, $ticket]);
    header('Location: dashboard.php?' . http_build_query($_GET));
    exit;
  }
}

// Build query with filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

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

// Count totals and per-status counts
$totalSql = "SELECT COUNT(*) as c FROM requests" . ($where ? " WHERE " . implode(" AND ", $where) : "");
$stmt = db()->prepare($totalSql);
$stmt->execute($params);
$total = (int)$stmt->fetch()['c'];

// status counters (overall)
$counts = [];
foreach (['Pending','In Progress','Completed'] as $s) {
  $cstmt = db()->prepare("SELECT COUNT(*) c FROM requests WHERE status = ?");
  $cstmt->execute([$s]);
  $counts[$s] = (int)$cstmt->fetch()['c'];
}

// Fetch page of results
$sql = "SELECT id, ticket_id, fullname, office, request_type, priority, status, created_at, attachment FROM requests";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

$stmt = db()->prepare($sql);
foreach ($params as $i => $p) {
  $stmt->bindValue($i+1, $p);
}
$stmt->bindValue(count($params)+1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params)+2, $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$totalPages = max(1, ceil($total / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard | ICT Requests</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    table{ width:100%; border-collapse: collapse; }
    th, td{ padding: .6rem; border-bottom: 1px solid #e5e7eb; text-align:left; }
    th{ background: #f8fafc; }
    .badge{ padding:.25rem .5rem; border-radius:999px; font-size:.8rem; border:1px solid #d1d5db; }
    .badge.Pending{ background:#fff; }
    .badge.In\\ Progress{ background:#fff7ed; }
    .badge.Completed{ background:#ecfdf5; }
    .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .toolbar input, .toolbar select{ padding:.5rem .75rem; border-radius:10px; border:1px solid #d1d5db; }
    .nowrap{ white-space:nowrap; }
    .pager{ display:flex; gap:.25rem; align-items:center; }
    .statbar{ display:flex; gap:1rem; margin: .75rem 0; }
    .stat{ background:#fff; padding:.5rem .75rem; border-radius:10px; border:1px solid #e5e7eb; }
  </style>
</head>
<body>
  <nav class="topbar">
    <a href="../index.html" class="brand"><img src="../assets/logo.jpg" class="logo small"> KsTU ICT</a>
    <div style="margin-left:auto">
      <a class="btn" href="export.php?<?= http_build_query($_GET) ?>">Export CSV</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </nav>

  <main class="container card">
    <h2>Requests</h2>

    <div class="statbar">
      <div class="stat"><strong>Total:</strong> <?= $total ?></div>
      <div class="stat"><strong>Pending:</strong> <?= $counts['Pending'] ?></div>
      <div class="stat"><strong>In Progress:</strong> <?= $counts['In Progress'] ?></div>
      <div class="stat"><strong>Completed:</strong> <?= $counts['Completed'] ?></div>
    </div>

    <form class="toolbar" method="get">
      <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by Ticket/Name/Office">
      <select name="status">
        <option value="">All Statuses</option>
        <?php foreach (['Pending','In Progress','Completed'] as $s): ?>
          <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn primary" type="submit">Apply</button>
      <a class="btn" href="dashboard.php">Reset</a>
    </form>

    <div style="overflow:auto; margin-top:1rem;">
      <table>
        <thead>
          <tr>
            <th>Ticket ID</th>
            <th>Name</th>
            <th>Office</th>
            <th>Type</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Date</th>
            <th class="nowrap">Attachment</th>
            <th class="nowrap">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="9" class="muted" style="text-align:center">No records found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td class="nowrap"><?= h($r['ticket_id']) ?></td>
              <td><?= h($r['fullname']) ?></td>
              <td><?= h($r['office']) ?></td>
              <td><?= h($r['request_type']) ?></td>
              <td><?= h($r['priority']) ?></td>
              <td><span class="badge <?= str_replace(' ','\\ ', h($r['status'])) ?>"><?= h($r['status']) ?></span></td>
              <td class="nowrap"><?= h($r['created_at']) ?></td>
              <td class="nowrap">
                <?php if ($r['attachment']): ?>
                  <?php $p = '../uploads/' . $r['attachment']; if (file_exists(__DIR__ . '/../uploads/' . $r['attachment'])): ?>
                    <a class="btn" href="<?= $p ?>" target="_blank">View</a>
                    <a class="btn" href="<?= $p ?>" download>Download</a>
                  <?php else: ?>
                    <span class="muted">missing</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" style="display:flex; gap:.5rem; align-items:center">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="ticket_id" value="<?= h($r['ticket_id']) ?>">
                  <select name="status">
                    <?php foreach (['Pending','In Progress','Completed'] as $s): ?>
                      <option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn primary" type="submit" name="update_status" value="1">Update</button>
                </form>
                <div style="margin-top:.5rem"><a class="btn" href="../ticket_lookup.php?ticket=<?= urlencode($r['ticket_id']) ?>" target="_blank">Open</a></div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:1rem; display:flex; justify-content:space-between; align-items:center;">
      <div class="muted">Page <?= $page ?> of <?= $totalPages ?></div>
      <div class="pager">
        <?php if ($page > 1): ?>
          <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">&laquo; Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">Next &raquo;</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
