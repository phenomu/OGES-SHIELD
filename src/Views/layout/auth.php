<?php
/** Layout shell auth — login/register/pending-approval (Bagian 4.10). */
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
</head>
<body class="auth-shell">
<div class="auth-bg-glow" aria-hidden="true"></div>
<?= $content ?>
</body>
</html>
