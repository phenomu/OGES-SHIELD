<?php
/**
 * Layout app shell: topbar fixed + sidebar fixed + area konten scroll.
 * Dipakai via View::page() — $content sudah ter-captured.
 * Topbar & sidebar ambil data asli sendiri (Phase 6 — MockData tak dipakai lagi di shell).
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(View::$pageTitle) ?></title>
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="stylesheet" href="/assets/css/tokens.css">
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/layout.css">
<link rel="stylesheet" href="/assets/css/components.css">
<script defer src="/assets/js/chart.umd.min.js"></script>
<script defer src="/assets/js/app.js"></script>
</head>
<body class="app-shell">
<?php require BASE_PATH . '/src/Views/partials/topbar.php'; ?>
<?php require BASE_PATH . '/src/Views/partials/sidebar.php'; ?>
<main class="app-main" id="main-content">
  <?= $content ?>
</main>
</body>
</html>
