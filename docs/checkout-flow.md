# Checkout và in hóa đơn trên Android

Filament chỉ dùng để quản trị và giám sát. Nút **Tính tiền**, trạng thái
`checkout_pending`, thao tác in và in lại thuộc ứng dụng Tauri Android.

## Ranh giới backend

`CheckoutAndQueueReceipt` điều phối hai bước độc lập:

1. `CheckoutTable` commit Payment, đóng Order/TableSession và giải phóng bàn.
2. `CreateReceiptPrintJob` tạo snapshot hóa đơn sau khi Payment đã hoàn tất.

Kết quả action có contract:

```php
array{
    payment: Payment,
    receiptPrintJob: PrintJob|null,
    receiptError: string|null,
}
```

Lỗi ở bước tạo Receipt PrintJob không rollback Payment. Tauri phải hiển thị
thanh toán thành công riêng với trạng thái in, ví dụ: "Thanh toán thành công,
chưa thể tạo lệnh in hóa đơn".

## Trạng thái Android

`checkout_pending` là trạng thái cục bộ của request trên Android, không phải
trạng thái Payment. Android bật trạng thái này trước khi gửi checkout, vô hiệu
hóa nút để tránh chạm lặp, rồi tắt khi nhận response hoặc lỗi mạng. UUID
`client_request_id` phải được giữ nguyên cho mọi lần retry của cùng một lần
checkout.

## In lại

`RetryPrintJob` hỗ trợ hai trường hợp:

- PrintJob `failed` được đưa về `pending`.
- Receipt PrintJob `printed` được đưa về `pending` để in thêm một bản.

Action giữ nguyên Payment, PrintJob và payload snapshot. `attempts` chỉ tăng khi
thiết bị claim lại; `printed_at` được xóa khi job quay về hàng đợi. Kitchen job
đã in không được phép reprint theo luồng này để tránh bếp làm món trùng.

API đã hỗ trợ xác thực nhân viên Android, command checkout, claim và callback
PrintJob. UI và kết nối ESC/POS trong Tauri vẫn chưa được triển khai.
