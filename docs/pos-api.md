# API dành cho Tauri Android

## Xác thực thiết bị

Nhân viên đăng nhập bằng `POST /api/login`:

```json
{
  "email": "staff.tranphu@example.com",
  "password": "password",
  "device_id": "6f20670c-a966-4a3d-9675-4f005d4d9aa4",
  "device_name": "POS quầy 1"
}
```

Laravel trả plaintext Sanctum token đúng trong response này. Những request sau
phải gửi đồng thời:

```http
Authorization: Bearer <access-token>
X-Device-ID: 6f20670c-a966-4a3d-9675-4f005d4d9aa4
Accept: application/json
```

Token có ability `pos:use` và `device:{uuid}`, mặc định hết hạn sau 43.200 phút
(30 ngày). Login lại cùng user/device sẽ thu hồi token cũ. `POST /api/pos/logout`
thu hồi token hiện tại.

Owner chưa gắn một Store không được đăng nhập POS Android. Luồng hiện tại dành
cho staff thuộc một Store đang hoạt động.

## Read API

`GET /api/pos/bootstrap` trả user, Store, zone, catalog và cấu hình các Printer
đang hoạt động.

`GET /api/pos/tables?zone=all&status=all&search=` trả projection sơ đồ bàn cùng
active session/order. Các trạng thái hỗ trợ là `all`, `empty`, `occupied`.

`GET /api/pos/orders/{order}` trả chi tiết order, items, payments và trạng thái
PrintJob. Order ngoài Store đã xác thực trả `404`.

## Command API

`POST /api/pos/commands` nhận một command:

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "device_id": "6f20670c-a966-4a3d-9675-4f005d4d9aa4",
  "type": "open_table",
  "payload": {"table_id": 7}
}
```

`device_id` phải trùng `X-Device-ID`. Type hợp lệ:

- `open_table`
- `add_order_items`
- `update_order_item`
- `create_kitchen_ticket`
- `checkout`

Response thành công có HTTP `200`. Command nghiệp vụ thất bại có HTTP `422` và
vẫn trả command terminal:

```json
{
  "data": {
    "id": "...",
    "type": "open_table",
    "status": "failed",
    "result": null,
    "error": "Không tìm thấy dữ liệu thuộc cửa hàng cho command này.",
    "attempts": 1,
    "processed_at": "..."
  }
}
```

`POST /api/pos/sync` nhận tối đa 50 command theo thứ tự và trả kết quả từng
command cùng snapshot bàn mới nhất:

```json
{
  "device_id": "6f20670c-a966-4a3d-9675-4f005d4d9aa4",
  "commands": [
    {"id": "...", "type": "add_order_items", "payload": {}}
  ]
}
```

Sync hiện là batch command + full table snapshot. Cursor incremental sẽ thuộc
giai đoạn local-first tiếp theo.

## PrintJob API cho Android

Android nhận job cũ nhất của một Printer:

```http
POST /api/pos/printers/{printer}/print-jobs/claim
```

Laravel trả payload, endpoint TCP, profile giấy, claim token và lease. Nếu máy
đang có lease hoặc không có job thì trả `204`.

Android gia hạn hoặc báo kết quả:

```http
PATCH /api/pos/print-jobs/{job}/result
```

```json
{"status": "printing", "claim_token": "<64 ký tự>"}
```

```json
{"status": "printed", "claim_token": "<64 ký tự>"}
```

```json
{
  "status": "failed",
  "claim_token": "<64 ký tự>",
  "error_message": "Không kết nối được 192.168.1.50:9100"
}
```

Android yêu cầu in lại job lỗi hoặc receipt đã in:

```http
POST /api/pos/print-jobs/{job}/retry
```

API Android dùng Sanctum user/device token. Các route
`/api/printers/{printer}/print-jobs/*` cũ tiếp tục dùng token riêng của Printer
cho agent headless và không phải luồng chính của ứng dụng Android.
