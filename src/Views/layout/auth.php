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
<svg class="auth-skyline" viewBox="0 0 1440 200" preserveAspectRatio="none" aria-hidden="true">
  <g fill="#22D3EE">
    <rect x="0" y="150" width="46" height="50" opacity="0.10"/>
    <rect x="54" y="120" width="30" height="80" opacity="0.07"/>
    <rect x="92" y="95" width="40" height="105" opacity="0.08"/>
    <rect x="140" y="140" width="24" height="60" opacity="0.06"/>
    <rect x="172" y="70" width="52" height="130" opacity="0.09"/>
    <rect x="232" y="125" width="34" height="75" opacity="0.07"/>
    <rect x="274" y="88" width="26" height="112" opacity="0.05"/>
    <rect x="308" y="145" width="60" height="55" opacity="0.10"/>
    <rect x="376" y="105" width="38" height="95" opacity="0.07"/>
    <rect x="422" y="60" width="46" height="140" opacity="0.08"/>
    <rect x="476" y="130" width="28" height="70" opacity="0.06"/>
    <rect x="512" y="92" width="40" height="108" opacity="0.09"/>
    <rect x="560" y="150" width="70" height="50" opacity="0.05"/>
    <rect x="638" y="78" width="30" height="122" opacity="0.07"/>
    <rect x="676" y="138" width="44" height="62" opacity="0.10"/>
    <rect x="728" y="55" width="52" height="145" opacity="0.08"/>
    <rect x="788" y="118" width="26" height="82" opacity="0.06"/>
    <rect x="822" y="96" width="38" height="104" opacity="0.09"/>
    <rect x="868" y="142" width="58" height="58" opacity="0.05"/>
    <rect x="934" y="84" width="30" height="116" opacity="0.07"/>
    <rect x="972" y="128" width="44" height="72" opacity="0.10"/>
    <rect x="1024" y="66" width="46" height="134" opacity="0.08"/>
    <rect x="1078" y="122" width="28" height="78" opacity="0.06"/>
    <rect x="1114" y="150" width="64" height="50" opacity="0.05"/>
    <rect x="1186" y="90" width="36" height="110" opacity="0.07"/>
    <rect x="1230" y="140" width="30" height="60" opacity="0.10"/>
    <rect x="1268" y="72" width="48" height="128" opacity="0.08"/>
    <rect x="1324" y="126" width="26" height="74" opacity="0.06"/>
    <rect x="1358" y="102" width="38" height="98" opacity="0.09"/>
    <rect x="1404" y="148" width="36" height="52" opacity="0.05"/>
  </g>
  <g fill="none" stroke="#EC4899" stroke-width="1" opacity="0.28">
    <path d="M0 200h1440"/>
  </g>
</svg>
<?= $content ?>
</body>
</html>
