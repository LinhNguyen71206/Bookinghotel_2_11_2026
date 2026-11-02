<?php
require_once __DIR__ . '/../includes/admin.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid($_POST)) {
    adminLogout();
    header('Location: login.php');
    exit;
}
header('Location: index.php');
exit;