<?php
require_once __DIR__ . '/admin.php';
$pageTitle = $pageTitle ?? 'Quản trị';
$flash = takeFlash();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Quản trị</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin">
<header class="site-header admin-header">
    <div class="container">
        <a class="brand" href="index.php">Quản trị khách sạn</a>
        <?php if (isAdminLoggedIn()): ?>
            <nav class="admin-nav">
                <a href="rooms.php" id="nav-rooms">Quản lý phòng</a>
                <a href="bookings.php" id="nav-bookings">Quản lý đơn đặt phòng</a>
                <a href="../index.php" id="nav-site">Trang khách hàng</a>
                <form method="post" action="logout.php" class="inline-form">
                    <?= csrfField() ?>
                    <button type="submit" id="btn-logout">Đăng xuất</button>
                </form>
            </nav>
        <?php endif; ?>
    </div>
</header>
<main class="container">
<?php if ($flash !== null): ?>
    <div class="<?= $flash['type'] === 'success' ? 'success-box' : 'error-box' ?>" id="flash-message"><?= e($flash['msg']) ?></div>
<?php endif; ?>