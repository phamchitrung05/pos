<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Đại diện cho một cửa hàng hoặc chi nhánh trong hệ thống POS. */
class Store extends Model
{
    use HasFactory;

    /** Tên bảng dùng số ít theo thiết kế cơ sở dữ liệu hiện tại. */
    protected $table = 'store';

    /** Các cột được phép ghi hàng loạt từ form quản trị. */
    protected $fillable = ['name', 'address', 'phone', 'email', 'opening_hours', 'is_active'];

    /** Ép kiểu cờ trạng thái về boolean để form và nghiệp vụ xử lý nhất quán. */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Các khu vực bàn thuộc chi nhánh. */
    public function zones(): HasMany
    {
        return $this->hasMany(TableZone::class);
    }

    /** Các bàn ăn thuộc chi nhánh. */
    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    /** Các nhóm sản phẩm thuộc chi nhánh. */
    public function productGroups(): HasMany
    {
        return $this->hasMany(ProductGroup::class);
    }

    /** Các sản phẩm thuộc trực tiếp chi nhánh, phục vụ truy vấn thực đơn theo tenant an toàn. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Các phiên bàn thuộc chi nhánh, phục vụ giới hạn dữ liệu vận hành theo tenant. */
    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /** Các đơn hàng thuộc chi nhánh, tránh tổng hợp doanh thu chéo tenant. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Các dòng món thuộc chi nhánh, tránh đọc chi tiết đơn hàng chéo tenant. */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Các thanh toán thuộc chi nhánh, phục vụ đối soát đúng tenant. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Các lệnh in thuộc chi nhánh, tránh điều khiển máy in chéo tenant. */
    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    /** Các command idempotent phát sinh tại chi nhánh. */
    public function posCommands(): HasMany
    {
        return $this->hasMany(PosCommand::class);
    }

    /** Các máy in được cấu hình cho chi nhánh. */
    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    /** Các tài khoản nhân viên thuộc chi nhánh. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
