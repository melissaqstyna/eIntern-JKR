<?php
// Entry point: login first, then dashboard. No separate homepage.
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
$loggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
header('Location: ' . ($loggedIn ? 'dashboard.php' : 'login.php'));
exit();
