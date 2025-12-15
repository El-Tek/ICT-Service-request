<?php
require_once __DIR__ . '/config.php';

$ticket = trim($_GET['ticket'] ?? '');
$result = null;
if ($ticket !== '') {
  $stmt = db()->prepare("SELECT ticket_id, fullname, phone, email, office, request_type, request_text, attachment, service_time, priority, status, created_at FROM requests WHERE ticket_id = ? LIMIT 1");
  $stmt->execute([$ticket]);
  $result = $stmt->fetch();
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ticket Lookup | KsTU ICT</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <main class="container card">
    <h2>Check Ticket Status</h2>
    <form method="get" style="display:flex; gap:.5rem; margin-bottom:1rem">
      <input name="ticket" placeholder="Enter Ticket ID (e.g., KSTU-2025-0001)" value="<?= h($ticket) ?>" style="flex:1; padding:.5rem .75rem; border-radius:8px; border:1px solid #d1d5db">
      <button class="btn primary" type="submit">Lookup</button>
    </form>

    <?php if ($ticket === ''): ?>
      <p class="muted">Enter your ticket ID above to see the current status.</p>
    <?php elseif (!$result): ?>
      <p class="error">No ticket found with that ID.</p>
    <?php else: ?>
      <div style="display:grid; grid-template-columns:1fr 300px; gap:1rem; align-items:start">
        <div>
          <h3><?= h($result['ticket_id']) ?> <small class="muted">— <?= h($result['status']) ?></small></h3>
          <p><strong>Name:</strong> <?= h($result['fullname']) ?></p>
          <p><strong>Office:</strong> <?= h($result['office']) ?></p>
          <p><strong>Type:</strong> <?= h($result['request_type']) ?></p>
          <p><strong>Priority:</strong> <?= h($result['priority']) ?></p>
          <p><strong>Submitted:</strong> <?= h($result['created_at']) ?></p>
          <h4>Description</h4>
          <p style="white-space:pre-wrap;"><?= h($result['request_text']) ?></p>
        </div>
        <div class="card" style="padding:1rem">
          <p><strong>Contact:</strong><br><?= h($result['phone']) ?><br><?= h($result['email']) ?></p>
          <p><strong>Preferred Service Time:</strong><br><?= h($result['service_time']) ?></p>
          <?php if ($result['attachment']): ?>
            <p><strong>Attachment</strong></p>
            <?php
              $path = __DIR__ . '/uploads/' . $result['attachment'];
              $ext = strtolower(pathinfo($result['attachment'], PATHINFO_EXTENSION));
              if (file_exists($path) && in_array($ext, ['jpg','jpeg','png'])): ?>
                <p><img src="uploads/<?= h($result['attachment']) ?>" alt="attachment" style="max-width:100%; border-radius:8px;"/></p>
                <p><a class="btn" href="uploads/<?= h($result['attachment']) ?>" download>Download Attachment</a></p>
              <?php elseif (file_exists($path)): ?>
                <p><a class="btn" href="uploads/<?= h($result['attachment']) ?>" download>Download Attachment</a></p>
              <?php else: ?>
                <p class="muted">Attachment missing.</p>
              <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
