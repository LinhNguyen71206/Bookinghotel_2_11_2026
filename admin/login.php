<?php
require_once __DIR__ . '/../includes/admin.php';
if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}
$pageTitle = 'Đăng nhập';
$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = inputString($_POST, 'username');
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu';
    } elseif (!adminLogin($username, $password)) {
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng';
    } else {
        header('Location: index.php');
        exit;
    }
}
require __DIR__ . '/../includes/admin_header.php';
?>
<h2>Đăng nhập quản trị</h2>
<?php if ($error !== null): ?>
    <div class="error-box" id="login-error"><?= e($error) ?></div>
<?php endif; ?>
<form method="post" action="login.php" id="login-form" novalidate>
    <div class="form-row">
        <label for="username">Tên đăng nhập</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>">
    </div>
    <div class="form-row">
        <label for="password">Mật khẩu</label>
        <input type="password" id="password" name="password" value="">
    </div>
    <div class="form-row">
        <button type="submit" id="btn-login">Đăng nhập</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>