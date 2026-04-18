<?php
require_once '../config/db.php';
if (isLoggedIn()) {
    logActivity($pdo, 'User logged out', 'auth');
}
session_destroy();
header('Location: /pharmacy/auth/login.php');
exit;
