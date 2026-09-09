<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Trang danh sách Sản phẩm.
 *
 * Nút "Sản phẩm mới" mở POPUP (modal) ngay trên trang danh sách
 * thay vì chuyển sang trang tạo riêng — vì resource không đăng ký
 * trang 'create' nên Filament tự động render CreateAction dạng modal.
 */
class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    /** Các nút ở đầu trang danh sách: chỉ có nút tạo mới dạng modal. */
    protected function getHeaderActions(): array
    {
        return [
            // Mở popup thêm sản phẩm ngay tại trang danh sách; sau khi lưu,
            // bảng tự làm mới và vẫn ở lại trang (không điều hướng).
            CreateAction::make(),
        ];
    }
}
