<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/admin_auth.php';

$auth = new AdminAuth();
$auth->logout();

header('Location: /admin/login.php');
exit;
?>
