-- Trạng thái phòng: active | maintenance | inactive
ALTER TABLE rooms ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active';

-- Mở rộng trạng thái đơn: pending_payment | confirmed | completed | cancelled | expired
-- (đổi sang VARCHAR để chứa thêm 'completed', 'cancelled'; giữ nguyên dữ liệu cũ)
ALTER TABLE bookings MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending_payment';