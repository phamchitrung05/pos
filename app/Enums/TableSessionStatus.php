<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Vòng đời của một phiên sử dụng bàn.
 *
 * Enum là nguồn định nghĩa duy nhất cho cả Eloquent và Filament, tránh việc
 * các form hoặc action tự viết chuỗi trạng thái khác nhau làm sai lệch dữ liệu.
 */
enum TableSessionStatus: string implements HasLabel
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** Trả về nhãn tiếng Việt để Filament hiển thị thống nhất trên form và bảng. */
    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Đang mở',
            self::Closed => 'Đã đóng',
            self::Cancelled => 'Đã hủy',
        };
    }
}
