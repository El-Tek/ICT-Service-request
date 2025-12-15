<?php
// KsTU ICT Request System - Configuration

// Database credentials (adjust for your XAMPP/MySQL)
define('DB_HOST', 'localhost');
define('DB_NAME', 'ict_requests');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL (adjust if using a subfolder or virtual host)
define('BASE_URL', 'http://localhost/ict-request-system');

// Enable/Disable email (PHP mail() or PHPMailer)
define('MAIL_ENABLED', false);
define('MAIL_FROM', 'ict-support@kstu.edu.gh');

// PHPMailer / SMTP fallback settings (if you install PHPMailer + configure SMTP)
// To use SMTP, install PHPMailer via Composer in the project root:
// composer require phpmailer/phpmailer
define('SMTP_ENABLED', false);
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'smtp-user@example.com');
define('SMTP_PASS', 'smtp-password');
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// Security: allowed upload settings
$ALLOWED_EXTS = ['pdf','doc','docx','jpg','jpeg','png'];
$ALLOWED_MIMES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'image/jpeg',
  'image/png',
];
$MAX_FILE_BYTES = 5 * 1024 * 1024; // 5MB

// Start session for entire app (admin area needs it)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// PDO connection helper
function db() {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
  }
  return $pdo;
}

// Helper: sanitize string for output (XSS protect minimal)
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// send_mail helper: will attempt to use PHPMailer (if installed via Composer) and SMTP settings.
// If PHPMailer is not present or SMTP_ENABLED is false, fallback to PHP mail().
function send_mail($to, $subject, $htmlBody, $plainBody = '') {
  if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) return false;

  // try PHPMailer if available and SMTP_ENABLED configured
  if (defined('SMTP_ENABLED') && SMTP_ENABLED && file_exists(__DIR__ . '/vendor/autoload.php')) {
    try {
      require __DIR__ . '/vendor/autoload.php';
      $mail = new PHPMailer\PHPMailer\PHPMailer(true);
      // Server settings
      $mail->isSMTP();
      $mail->Host = SMTP_HOST;
      $mail->SMTPAuth = true;
      $mail->Username = SMTP_USER;
      $mail->Password = SMTP_PASS;
      $mail->SMTPSecure = SMTP_SECURE;
      $mail->Port = SMTP_PORT;

      // Recipients & content
      $mail->setFrom(MAIL_FROM, 'KsTU ICT Support');
      $mail->addAddress($to);
      $mail->Subject = $subject;
      $mail->isHTML(true);
      $mail->Body = $htmlBody;
      if ($plainBody) $mail->AltBody = $plainBody;

      $mail->send();
      return true;
    } catch (Exception $e) {
      // fall through to mail()
    }
  }

  // fallback: simple mail()
  $headers = "From: " . MAIL_FROM . "\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
  return @mail($to, $subject, $htmlBody, $headers);
}
?>
