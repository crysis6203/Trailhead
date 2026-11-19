<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Trailhead</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1c1917">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icons/favicon-16.png">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Trailhead">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="app.css?v=<?php echo filemtime(__DIR__ . '/app.css'); ?>">
</head>
<body>
    <div id="app"></div>
    <script src="app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
</body>
</html>
