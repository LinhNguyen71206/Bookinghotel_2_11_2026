<?php
require_once __DIR__ . '/../includes/admin.php';
requireAdmin();

$id = inputString($_GET, 'id');
$isEdit = ($id !== '');
$pageTitle = $isEdit ? 'Sửa phòng' : 'Thêm phòng';

$room = null;
$loadError = null;
$dbError = null;
$errors = [];

try {
    if ($isEdit) {
        if (!preg_match(ROOM_ID_PATTERN, $id)) {
            $loadError = 'Mã phòng không hợp lệ';
        } else {
            $room = getRoomById($id);
            if ($room === null) {
                $loadError = 'Không tìm thấy phòng có mã ' . $id;
            }
        }
    }
} catch (PDOException $ex) {
    $dbError = 'Lỗi cơ sở dữ liệu. Hãy chắc chắn đã chạy database/migration_admin.sql.';
}

/* Giá trị ban đầu của form */
$form = array_fill_keys(ROOM_COLUMNS, '');
$form['status'] = 'active';
$form['image_color'] = DEFAULT_IMAGE_COLOR;
if ($room !== null) {
    foreach (ROOM_COLUMNS as $c) {
        $form[$c] = ($c === 'amenities') ? implode("\n", $room['amenities']) : (string) ($room[$c] ?? '');
    }
    if ($form['status'] === '') {
        $form['status'] = 'active';
    }
}

/* Gửi form */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loadError === null && $dbError === null) {
    if (!csrfValid($_POST)) {
        $errors['_form'] = 'Phiên làm việc không hợp lệ, vui lòng thử lại';
    } else {
        foreach (ROOM_COLUMNS as $c) {
            $form[$c] = inputString($_POST, $c);
        }
        $errors = validateRoomForm($form);
        if (!$errors) {
            try {
                $data = roomFormData($form);
                if ($isEdit) {
                    adminUpdateRoom($id, $data);
                    $savedId = $id;
                    flash('success', "Đã cập nhật phòng $id");
                } else {
                    $savedId = adminCreateRoom($data);
                    flash('success', "Đã thêm phòng $savedId");
                }
                header('Location: room_view.php?id=' . urlencode($savedId));
                exit;
            } catch (PDOException $ex) {
                $dbError = 'Lỗi khi lưu vào cơ sở dữ liệu.';
            } catch (RuntimeException $ex) {
                $dbError = $ex->getMessage();
            }
        }
    }
}

$textFields = [
    'hotel_name'          => ['Tên khách sạn', ''],
    'room_name'           => ['Tên loại phòng', ''],
    'destination'         => ['Điểm đến', ''],
    'address'             => ['Địa chỉ', ''],
    'rating'              => ['Điểm đánh giá (0 - ' . LIMIT_RATING_MAX . ')', 'ví dụ 8.5'],
    'max_guests'          => ['Số khách tối đa / phòng', ''],
    'price'               => ['Giá / đêm (VND)', 'số nguyên'],
    'total_rooms'         => ['Tổng số phòng', ''],
    'cancellation_policy' => ['Chính sách hủy', ''],
    'meal_policy'         => ['Chính sách bữa ăn', ''],
    'image_color'         => ['Màu ảnh', '#RRGGBB, để trống = mặc định'],
];
$action = 'room_form.php' . ($isEdit ? '?id=' . urlencode($id) : '');

require __DIR__ . '/../includes/admin_header.php';
?>
<p><a href="rooms.php" id="link-back">&laquo; Quay lại danh sách phòng</a></p>
<h2><?= e($pageTitle) ?></h2>

<?php if ($loadError !== null): ?>
    <div class="error-box" id="room-error"><?= e($loadError) ?></div>
<?php else: ?>
    <?php if ($dbError !== null): ?>
        <div class="error-box" id="db-error"><?= e($dbError) ?></div>
    <?php endif; ?>
    <?php if (isset($errors['_form'])): ?>
        <div class="error-box" id="form-error"><?= e($errors['_form']) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($action) ?>" id="room-form" novalidate>
            <?= csrfField() ?>
            <div class="form-row">
                <label>Mã phòng</label>
                <span id="room-id"><?= $isEdit ? e($id) : 'Tự động tạo khi lưu' ?></span>
            </div>
            <?php foreach ($textFields as $name => [$label, $hint]): ?>
                <div class="form-row">
                    <label for="<?= e($name) ?>"><?= e($label) ?></label>
                    <input type="text" id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($form[$name]) ?>">
                    <?php if ($hint !== ''): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>
                    <?php fieldError($errors, $name); ?>
                </div>
            <?php endforeach; ?>
            <div class="form-row">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" rows="3"><?= e($form['description']) ?></textarea>
                <?php fieldError($errors, 'description'); ?>
            </div>
            <div class="form-row">
                <label for="amenities">Tiện nghi</label>
                <textarea id="amenities" name="amenities" rows="4"><?= e($form['amenities']) ?></textarea>
                <span class="hint">mỗi dòng một tiện nghi</span>
                <?php fieldError($errors, 'amenities'); ?>
            </div>
            <div class="form-row">
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="">-- Chọn trạng thái --</option>
                    <?php foreach (roomStatusLabels() as $k => $label): ?>
                        <option value="<?= e($k) ?>"<?= $form['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php fieldError($errors, 'status'); ?>
            </div>
            <div class="form-row">
                <button type="submit" id="btn-save"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm phòng' ?></button>
            </div>
        </form>
        <?php if ($errors && !isset($errors['_form'])): ?>
            <div class="error-box" id="validation-summary">Vui lòng kiểm tra lại các trường bị lỗi ở trên.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>