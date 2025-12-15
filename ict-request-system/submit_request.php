<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: home.html');
  exit;
}

// Basic validation
$fullname = trim($_POST['fullname'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$office = trim($_POST['office'] ?? '');
$request_type = trim($_POST['request_type'] ?? '');
$priority = trim($_POST['priority'] ?? 'Normal');
$service_time = trim($_POST['service_time'] ?? '');
$request_text = trim($_POST['request_text'] ?? '');

$errors = [];

if ($fullname === '' || strlen($fullname) > 100) $errors[] = 'Full name is required and must be ≤ 100 chars.';
if ($phone === '' || strlen($phone) > 20) $errors[] = 'Phone is required and must be ≤ 20 chars.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $errors[] = 'Valid email is required and must be ≤ 100 chars.';
if ($office === '' || strlen($office) > 100) $errors[] = 'Office is required and must be ≤ 100 chars.';
if ($request_type === '') $errors[] = 'Request type is required.';
if (!in_array($priority, ['Normal','High','Urgent'])) $errors[] = 'Invalid priority.';
if ($request_text === '') $errors[] = 'Description is required.';

// Handle file upload (optional)
$attachment_name = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
  $f = $_FILES['attachment'];
  if ($f['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Error uploading file.';
  } else {
    global $ALLOWED_EXTS, $ALLOWED_MIMES, $MAX_FILE_BYTES;
    $size = (int)$f['size'];
    if ($size <= 0 || $size > $MAX_FILE_BYTES) {
      $errors[] = 'File too large (max 5MB).';
    } else {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($f['tmp_name']);
      if (!in_array($ext, $ALLOWED_EXTS) || !in_array($mime, $ALLOWED_MIMES)) {
        $errors[] = 'Invalid file type.';
      } else {
        // Generate safe filename
        $base = bin2hex(random_bytes(8));
        $attachment_name = $base . '.' . $ext;
        $dest = __DIR__ . '/uploads/' . $attachment_name;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
          $errors[] = 'Failed to save uploaded file.';
        }
      }
    }
  }
}

if (!empty($errors)) {
  // Simple error display
  echo '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="assets/style.css"><title>Submission Error</title></head><body>';
  echo '<main class="container card">';
  echo '<h2>Submission Error</h2><ul class="error">';
  foreach ($errors as $e) echo '<li>'.h($e).'</li>';
  echo '</ul><a class="btn" href="home.html">Go Back</a></main></body></html>';
  exit;
}

// Generate unique ticket id KSTU-YYYY-####
function generate_ticket_id(PDO $pdo) {
  $year = date('Y');
  for ($i=0; $i<5; $i++) { // attempt few times
    $num = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $ticket = "KSTU-$year-$num";
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM requests WHERE ticket_id = ?");
    $stmt->execute([$ticket]);
    if ($stmt->fetch()['c'] == 0) return $ticket;
  }
  // fallback with time-based suffix
  return "KSTU-$year-" . substr((string)time(), -4);
}

try {
  $pdo = db();
  $pdo->beginTransaction();
  $ticket_id = generate_ticket_id($pdo);

  $stmt = $pdo->prepare("
    INSERT INTO requests (ticket_id, fullname, phone, email, office, request_type, request_text, attachment, service_time, priority)
    VALUES (:ticket_id, :fullname, :phone, :email, :office, :request_type, :request_text, :attachment, :service_time, :priority)
  ");
  $stmt->execute([
    ':ticket_id' => $ticket_id,
    ':fullname' => $fullname,
    ':phone' => $phone,
    ':email' => $email,
    ':office' => $office,
    ':request_type' => $request_type,
    ':request_text' => $request_text,
    ':attachment' => $attachment_name,
    ':service_time' => $service_time,
    ':priority' => $priority,
  ]);
  $pdo->commit();
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // On failure, delete file if saved
  if ($attachment_name) @unlink(__DIR__ . '/uploads/' . $attachment_name);
  http_response_code(500);
  echo '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="assets/style.css"><title>Error</title></head><body>';
  echo '<main class="container card"><h2>Server Error</h2><p>We could not process your request right now.</p>';
  echo '<pre class="muted">'.h($e->getMessage()).'</pre>';
  echo '<a class="btn" href="home.html">Go Back</a></main></body></html>';
  exit;
}

// Send email if enabled (HTML template with lookup link)
if (MAIL_ENABLED) {
  $lookup = rtrim(BASE_URL, '/') . '/ticket_lookup.php?ticket=' . urlencode($ticket_id);
  $subject = "KsTU ICT Ticket $ticket_id received";
  $html = '<p>Dear ' . h($fullname) . ',</p>';
  $html .= '<p>Your request has been received and assigned <strong>Ticket ID: ' . h($ticket_id) . '</strong>.</p>';
  $html .= '<p>You can check the status and details at: <a href="' . h($lookup) . '">' . h($lookup) . '</a></p>';
  $html .= '<p>Summary:</p>';
  $html .= '<ul>';
  $html .= '<li><strong>Type:</strong> ' . h($request_type) . '</li>';
  $html .= '<li><strong>Priority:</strong> ' . h($priority) . '</li>';
  $html .= '<li><strong>Office:</strong> ' . h($office) . '</li>';
  $html .= '</ul>';
  $html .= '<p>Regards,<br>KsTU ICT Directorate</p>';
  send_mail($email, $subject, $html, strip_tags($html));
}

header('Location: thankyou.html?ticket=' . urlencode($ticket_id));
exit;
