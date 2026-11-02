<?php
/* ============================================================
 * includes/search.php - Logic tìm kiếm phòng
 *
 * Quy tắc tìm kiếm (đây cũng là cơ sở để thiết kế ca kiểm thử):
 *  1. Điểm đến: so khớp chuỗi con với cột "destination" HOẶC tên khách sạn,
 *     không phân biệt hoa thường (theo collation của MySQL).
 *  2. Giá: lọc theo giá/đêm, tính cả hai đầu mút [min, max].
 *  3. Sức chứa: số phòng yêu cầu x số khách tối đa của phòng phải >= số người.
 *  4. Còn trống: số phòng còn trống trong khoảng [nhận, trả) phải >= số phòng yêu cầu.
 *  5. Sắp xếp mặc định: điểm đánh giá giảm dần, rồi giá tăng dần, rồi mã phòng.
 * Dữ liệu đầu vào được giả định đã qua validateSearch().
 * ============================================================ */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* Thoát các ký tự đặc biệt của LIKE (dùng ký tự thoát '!') */
function escapeLike(string $s): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
}

/*
 * Trả về danh sách phòng phù hợp. Mỗi phần tử là bản ghi phòng kèm thêm:
 *   available   : số phòng còn trống
 *   nights      : số đêm
 *   total_price : tổng tiền = giá/đêm x số đêm x số phòng yêu cầu
 */
function searchRooms(
    string $destination,
    string $checkIn,
    string $checkOut,
    int $guests,
    int $rooms,
    ?int $minPrice = null,
    ?int $maxPrice = null,
    ?string $now = null
): array {
    $sql = "SELECT * FROM rooms
            WHERE (destination LIKE :kw_dest ESCAPE '!' OR hotel_name LIKE :kw_hotel ESCAPE '!')";
    $keyword = '%' . escapeLike($destination) . '%';
    $params = [':kw_dest' => $keyword, ':kw_hotel' => $keyword];

    if ($minPrice !== null) {
        $sql .= ' AND price >= :min_price';
        $params[':min_price'] = $minPrice;
    }
    if ($maxPrice !== null) {
        $sql .= ' AND price <= :max_price';
        $params[':max_price'] = $maxPrice;
    }
    $sql .= ' ORDER BY rating DESC, price ASC, id ASC';

    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);

    $nights = calculateNights($checkIn, $checkOut);
    $results = [];
    foreach ($stmt->fetchAll() as $row) {
        $room = decodeRoom($row);

        if ((int) $room['max_guests'] * $rooms < $guests) {
            continue; // không đủ sức chứa
        }
        $available = getAvailableQuantity($room['id'], $checkIn, $checkOut, $now);
        if ($available < $rooms) {
            continue; // không đủ phòng trống
        }

        $room['available']   = $available;
        $room['nights']      = $nights;
        $room['total_price'] = calculateTotal((int) $room['price'], $nights, $rooms);
        $results[] = $room;
    }
    return $results;
}