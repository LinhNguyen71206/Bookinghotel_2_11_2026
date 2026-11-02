<?php
/* ============================================================
 * includes/header.php - Phần đầu chung của mọi trang
 * Cách dùng ở đầu mỗi trang:
 *     $pageTitle = 'Tìm kiếm phòng';
 *     require_once __DIR__ . '/includes/header.php';
 * ============================================================ */
require_once __DIR__ . '/helpers.php';
$pageTitle = $pageTitle ?? 'Đặt phòng khách sạn';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Đặt phòng khách sạn</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="brand" href="index.php">Đặt phòng khách sạn</a>
    </div>
</header>
<main class="container"></main>