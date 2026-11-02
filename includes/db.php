<?php
/* ============================================================
 * includes/db.php - Tầng truy cập dữ liệu (PDO + MySQL)
 * Các trang khác dùng: require_once __DIR__ . '/includes/db.php';
 *
 * Thời gian dùng định dạng chuỗi 'Y-m-d H:i:s'; ngày dùng 'Y-m-d'.
 * Tham số $now (tùy chọn) cho phép "giả lập" thời điểm hiện tại khi kiểm thử.
 * ============================================================ */

require_once __DIR__ . '/../config/database.php';

/* ---------- Kết nối ---------- */
function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function nowString(?string $now = null): string
{
    return $now ?? date('Y-m-d H:i:s');
}

/* ---------- Bảng PHÒNG ---------- */
function decodeRoom(?array $row): ?array
{
    if ($row === null) {
        return null;
    }
    $row['amenities'] = json_decode($row['amenities'], true) ?: [];
    return $row;
}

function getAllRooms(): array
{
    $rows = getPDO()->query('SELECT * FROM rooms ORDER BY id')->fetchAll();
    return array_map('decodeRoom', $rows);
}

function getRoomById($id): ?array
{
    if (!is_string($id) || trim($id) === '') {
        return null;
    }
    $stmt = getPDO()->prepare('SELECT * FROM rooms WHERE id = :id');
    $stmt->execute([':id' => trim($id)]);
    $row = $stmt->fetch();
    return $row ? decodeRoom($row) : null;
}

function getAllDestinations(): array
{
    return getPDO()
        ->query('SELECT DISTINCT destination FROM rooms ORDER BY destination')
        ->fetchAll(PDO::FETCH_COLUMN);
}

/* ---------- Bảng ĐẶT PHÒNG ---------- */
function getBookingByCode(string $code): ?array
{
    $stmt = getPDO()->prepare('SELECT * FROM bookings WHERE code = :code');
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* Sinh mã đặt phòng duy nhất, ví dụ: BK260905-K9Z6X */
function generateBookingCode(?string $now = null): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $datePart = date('ymd', strtotime(nowString($now)));
    do {
        $suffix = '';
        for ($i = 0; $i < 5; $i++) {
            $suffix .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $code = 'BK' . $datePart . '-' . $suffix;
    } while (getBookingByCode($code) !== null);
    return $code;
}

/*
 * Thêm đơn đặt phòng. Trả về mã đơn.
 * Bắt buộc có: room_id, check_in, check_out, guests, quantity,
 *              full_name, phone, country, email, total_amount
 * Tùy chọn   : special_request, arrival_time, code, status, created_at, hold_expires_at
 * Nếu không truyền thì: status = pending_payment, created_at = $now,
 *                       hold_expires_at = $now + HOLD_MINUTES phút.
 */
function addBooking(array $data, ?string $now = null): string
{
    $createdAt = $data['created_at'] ?? nowString($now);
    $holdUntil = $data['hold_expires_at']
        ?? date('Y-m-d H:i:s', strtotime($createdAt) + HOLD_MINUTES * 60);
    $code = $data['code'] ?? generateBookingCode($createdAt);

    $sql = 'INSERT INTO bookings
              (code, room_id, check_in, check_out, guests, quantity,
               full_name, phone, country, email, special_request, arrival_time,
               total_amount, status, created_at, hold_expires_at)
            VALUES
              (:code, :room_id, :check_in, :check_out, :guests, :quantity,
               :full_name, :phone, :country, :email, :special_request, :arrival_time,
               :total_amount, :status, :created_at, :hold_expires_at)';
    getPDO()->prepare($sql)->execute([
        ':code'            => $code,
        ':room_id'         => $data['room_id'],
        ':check_in'        => $data['check_in'],
        ':check_out'       => $data['check_out'],
        ':guests'          => $data['guests'],
        ':quantity'        => $data['quantity'],
        ':full_name'       => $data['full_name'],
        ':phone'           => $data['phone'],
        ':country'         => $data['country'],
        ':email'           => $data['email'],
        ':special_request' => $data['special_request'] ?? null,
        ':arrival_time'    => $data['arrival_time'] ?? null,
        ':total_amount'    => $data['total_amount'],
        ':status'          => $data['status'] ?? 'pending_payment',
        ':created_at'      => $createdAt,
        ':hold_expires_at' => $holdUntil,
    ]);
    return $code;
}

/* Cập nhật một số cột của đơn theo mã. Trả về đơn sau cập nhật, hoặc null nếu không có đơn. */
function updateBooking(string $code, array $changes): ?array
{
    $allowed = ['status', 'hold_expires_at', 'special_request', 'arrival_time'];
    $sets = [];
    $params = [':code' => $code];
    foreach ($changes as $column => $value) {
        if (in_array($column, $allowed, true)) {
            $sets[] = "$column = :$column";
            $params[":$column"] = $value;
        }
    }
    if ($sets) {
        $sql = 'UPDATE bookings SET ' . implode(', ', $sets) . ' WHERE code = :code';
        getPDO()->prepare($sql)->execute($params);
    }
    return getBookingByCode($code);
}

/* Xóa toàn bộ đơn đặt phòng - dùng để đưa dữ liệu về ban đầu khi kiểm thử */
function resetBookings(): void
{
    getPDO()->exec('DELETE FROM bookings');
}

/* ---------- Tình trạng còn phòng ---------- */

/* Đơn giữ chỗ quá hạn mà chưa thanh toán -> chuyển "expired". Trả về số đơn bị hủy. */
function expireHolds(?string $now = null): int
{
    $stmt = getPDO()->prepare(
        "UPDATE bookings SET status = 'expired'
         WHERE status = 'pending_payment' AND hold_expires_at <= :now"
    );
    $stmt->execute([':now' => nowString($now)]);
    return $stmt->rowCount();
}

/*
 * Số phòng loại $roomId đã bị chiếm trong khoảng [$checkIn, $checkOut).
 * Đơn chiếm phòng = đã xác nhận, hoặc đang giữ chỗ còn hạn.
 * Ngày trả phòng của đơn này được phép trùng ngày nhận phòng của đơn khác.
 */
function countBookedRooms(string $roomId, string $checkIn, string $checkOut, ?string $now = null): int
{
    $sql = "SELECT COALESCE(SUM(quantity), 0) FROM bookings
            WHERE room_id = :room_id
              AND check_in < :check_out
              AND check_out > :check_in
              AND (status IN ('confirmed', 'completed')
                   OR (status = 'pending_payment' AND hold_expires_at > :now))";
    $stmt = getPDO()->prepare($sql);
    $stmt->execute([
        ':room_id'   => $roomId,
        ':check_in'  => $checkIn,
        ':check_out' => $checkOut,
        ':now'       => nowString($now),
    ]);
    return (int) $stmt->fetchColumn();
}

/* Số phòng còn trống của loại phòng trong khoảng ngày. Trả về 0 nếu phòng không tồn tại. */
function getAvailableQuantity(string $roomId, string $checkIn, string $checkOut, ?string $now = null): int
{
    $room = getRoomById($roomId);
    if ($room === null) {
        return 0;
    }
     if (($room['status'] ?? 'active') !== 'active') {   // <-- THÊM 3 DÒNG NÀY
        return 0;
    }
    $left = (int) $room['total_rooms'] - countBookedRooms($roomId, $checkIn, $checkOut, $now);
    return max(0, $left);
}