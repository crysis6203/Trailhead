<?php
// Queue — redirects to Meeting Mode (queue is now managed there)
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }
header('Location: /meeting');
exit;
