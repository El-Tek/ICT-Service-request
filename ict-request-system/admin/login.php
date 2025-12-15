<?php
require_once __DIR__ . '/../config.php';

// Already logged in?
if (!empty($_SESSION['admin'])) {
  header('Location: dashboard.php');
  exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');

  // Hardcoded per specification
  if ($username === 'admin' && $password === 'kstuict123') {
    $_SESSION['admin'] = [
      'user' => 'admin',
      'login_time' => time(),
      'csrf' => bin2hex(random_bytes(16)),
    ];
    header('Location: dashboard.php');
    exit;
  } else {
    $error = 'Invalid credentials';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login | ICT Requests</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <main class="container card" style="max-width:480px">
    <h2>Admin Login</h2>
    <?php if ($error): ?>
      <p class="error"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" class="grid" style="grid-template-columns:1fr">
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <div class="actions" style="justify-content:space-between">
        <a href="../index.html" class="btn">Back</a>
        <button type="submit" class="btn primary">Login</button>
      </div>
    </form>
  </main>
</body>
</html>
