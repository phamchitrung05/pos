# POS commands và idempotency

Mọi thao tác ghi từ Tauri dùng một UUID command do thiết bị sinh và giữ lại cho
đến khi nhận được kết quả terminal từ Laravel. `ProcessPosCommand` hỗ trợ:

- `open_table`
- `add_order_items`
- `update_order_item`
- `create_kitchen_ticket`
- `checkout`

## Envelope

Một request command gồm:

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "device_id": "6f20670c-a966-4a3d-9675-4f005d4d9aa4",
  "type": "add_order_items",
  "payload": {
    "order_id": 25,
    "items": [
      {"product_id": 8, "quantity": 2, "notes": "Ít đá"}
    ]
  }
}
```

`store_id` và `user_id` không được tin từ JSON. API lấy hai giá trị này từ
user/device đã xác thực rồi truyền model vào `ProcessPosCommand`.

## Quy tắc idempotency

Laravel chuẩn hóa thứ tự key JSON và lưu SHA-256 vào `payload_hash`.

- UUID mới: command được khóa và thực thi.
- UUID cũ cùng store/device/user/type/payload: trả nguyên `result` hoặc `error` đã lưu.
- UUID cũ nhưng envelope hoặc payload khác: validation error, không thực thi.
- Hai request đồng thời: cùng khóa một dòng; request sau chờ rồi đọc kết quả request trước.
- Command `failed` là terminal. Client phải sửa dữ liệu và dùng UUID mới, không đổi payload của UUID cũ.

Riêng `checkout`, UUID command cũng được dùng làm `payments.client_request_id`,
tạo thêm unique constraint bảo vệ Payment ở tầng database.

## Trạng thái

```text
pending -> processing -> completed
                      -> failed
```

`attempts` tăng khi command bắt đầu thực thi, không tăng khi thiết bị chỉ đọc lại
kết quả terminal. `processed_at` được ghi cho cả `completed` và `failed`.
