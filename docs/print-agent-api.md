# Print Agent API

Tài liệu này là contract giữa Laravel và ứng dụng Tauri print agent. Laravel quản lý hàng đợi và snapshot; agent chỉ render rồi giao bytes tới máy in.

## Cấu hình máy in

Mỗi Printer cần:

- `printer_type`: `kitchen`, `receipt` hoặc `label`.
- `ip_address`: địa chỉ IP LAN hợp lệ.
- `port`: cổng TCP từ 1 đến 65535, thường là 9100.
- `paper_width_mm`: `58` hoặc `80`.
- `is_active`: phải bật để nhận claim mới.
- Bearer token: được owner cấp trong Filament và chỉ hiển thị plaintext một lần.

Profile raster chuẩn:

| Khổ giấy | Chiều rộng bitmap |
| --- | ---: |
| 58 mm | 384 dots |
| 80 mm | 576 dots |

## Trạng thái

```text
pending -> printing -> printed
                    -> failed -> pending (retry)
```

- `claim` đổi job từ `pending` sang `printing`, tăng `attempts` và cấp lease hai phút.
- `printing` xác nhận agent đang xử lý và gia hạn lease thêm hai phút.
- `printed` và `failed` yêu cầu đúng claim token của lượt hiện tại.
- Callback `printed` hoặc `failed` lặp lại với cùng token là idempotent.
- Retry dùng lại chính hàng `print_jobs` và payload snapshot cũ; `attempts` chỉ tăng khi claim lại.
- Mỗi Printer chỉ có tối đa một lease đang hoạt động.

## Xác thực

Tất cả request sử dụng:

```http
Authorization: Bearer <printer-api-token>
Accept: application/json
```

Không ghi token vào log. Khi kết nối ngoài localhost, Laravel bắt buộc được phục vụ qua HTTPS.

## Claim job

```http
POST /api/printers/{printer}/print-jobs/claim
```

Kết quả `204 No Content` nghĩa là chưa có job hoặc máy đang có lease hoạt động. Kết quả `200`:

```json
{
  "data": {
    "id": 123,
    "print_type": "receipt",
    "status": "printing",
    "attempt": 1,
    "claim_token": "64-character-token",
    "lease_expires_at": "2026-09-10T10:32:00+07:00",
    "printer": {
      "id": 4,
      "name": "Máy in quầy",
      "ip_address": "192.168.1.50",
      "port": 9100,
      "paper_width_mm": 80,
      "dots_per_line": 576
    },
    "payload": {}
  }
}
```

## Báo đang in

```http
POST /api/printers/{printer}/print-jobs/{printJob}/printing
Content-Type: application/json

{"claim_token":"64-character-token"}
```

Agent gọi endpoint này trước khi gửi tài liệu dài hoặc định kỳ để lease không hết hạn giữa lúc in.

## Báo thành công

```http
POST /api/printers/{printer}/print-jobs/{printJob}/printed
Content-Type: application/json

{"claim_token":"64-character-token"}
```

Chỉ báo `printed` sau khi TCP client đã hoàn tất `write_all` và `flush`.

## Báo thất bại

```http
POST /api/printers/{printer}/print-jobs/{printJob}/failed
Content-Type: application/json

{
  "claim_token":"64-character-token",
  "error_message":"Không kết nối được 192.168.1.50:9100"
}
```

`error_message` tối đa 1000 ký tự. Owner hoặc staff có quyền sẽ retry job trong danh sách PrintJob của Filament.

## Snapshot version 1

Cả kitchen và receipt payload đều có:

```json
{
  "version": 1,
  "type": "kitchen",
  "document": {
    "paper_width_mm": 80,
    "dots_per_line": 576,
    "locale": "vi-VN",
    "render_mode": "raster"
  }
}
```

Receipt lưu `unit_price`, `subtotal`, `total` và `amount` dưới dạng số nguyên VND. Agent phải raster hóa toàn bộ text do người dùng quản lý; không gửi tên món hoặc ghi chú trực tiếp dưới dạng ESC/POS text để tránh lỗi dấu tiếng Việt và ký tự điều khiển.

## Bố cục chứng từ

Phiếu bếp:

```text
PHIẾU BẾP
Tên cửa hàng
Bàn / mã order / thời gian
--------------------------------
Số lượng x tên món
Ghi chú món
--------------------------------
Nhân viên yêu cầu
```

Hóa đơn:

```text
Tên, địa chỉ, điện thoại cửa hàng
HÓA ĐƠN THANH TOÁN
Mã order / bàn / thời gian
--------------------------------
Tên món
Số lượng x đơn giá       thành tiền
--------------------------------
TỔNG CỘNG
Phương thức / thu ngân
Cảm ơn quý khách
```

Thiết kế phải tự wrap theo chiều rộng bitmap, in theo dải raster giới hạn và không thay đổi snapshot khi retry.
