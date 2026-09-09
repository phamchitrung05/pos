<?php

namespace App\Models\Concerns;

use App\Models\Store;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Tự gán Store hiện tại cho model mới được tạo trong Filament tenant panel.
 *
 * Trait chỉ điền khi `store_id` đang trống và tenant đã được Filament xác
 * định là Store. Dữ liệu chạy ngoài panel vẫn phải truyền Store rõ ràng hoặc
 * được model nghiệp vụ suy ra từ quan hệ cha; không có tenant ngầm toàn cục.
 */
trait AssignsCurrentStore
{
    /** Đăng ký sự kiện creating trước khi câu lệnh INSERT được thực hiện. */
    protected static function bootAssignsCurrentStore(): void
    {
        static::creating(function (Model $record): void {
            $tenant = Filament::getTenant();

            if ($record->getAttribute('store_id') === null && $tenant instanceof Store) {
                $record->setAttribute('store_id', $tenant->getKey());
            }
        });
    }
}
