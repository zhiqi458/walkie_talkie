<?php
use App\Core\Helpers;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#08131f">
    <meta name="csrf-token" content="<?= Helpers::escape(Helpers::csrfToken()) ?>">
    <title><?= Helpers::escape($title ?? Helpers::config('name', 'Walkie Talkie')) ?></title>
    <link rel="manifest" href="<?= Helpers::escape(Helpers::url('/public/manifest.webmanifest')) ?>">
    <link rel="icon" href="<?= Helpers::escape(Helpers::url('/public/assets/icons/icon.svg')) ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= Helpers::escape(Helpers::url('/public/assets/css/app.css?v=20261008-2')) ?>">
</head>
<body>
<div class="app-shell">
    <?= $content ?>
</div>
<script>
    window.__WALKIE__ = {
        csrfToken: <?= json_encode(Helpers::csrfToken(), JSON_UNESCAPED_SLASHES) ?>,
        baseUrl: <?= json_encode(Helpers::url(''), JSON_UNESCAPED_SLASHES) ?>,
        signalUrl: <?= json_encode(Helpers::url('/index.php?route=signal'), JSON_UNESCAPED_SLASHES) ?>,
        publicUrl: <?= json_encode(
            rtrim(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . Helpers::url('/'),
            JSON_UNESCAPED_SLASHES
        ) ?>,
        signalTimeout: <?= json_encode($signalTimeout ?? 30000, JSON_UNESCAPED_SLASHES) ?>,
        session: <?= json_encode($session ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        state: <?= json_encode($state ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        stunServers: <?= json_encode($stunServers ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script type="module" src="<?= Helpers::escape(Helpers::url('/public/assets/js/app.js?v=20261008-2')) ?>"></script>
</body>
</html>
