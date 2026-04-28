<?php
// Run — redirects to Meeting Mode (run flow is now managed there)
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }
header('Location: /meeting');
exit;
