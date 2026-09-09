<?php

namespace App\Filament\Resources\PrintJobs\Schemas;

use App\Enums\PrintJobStatus;
use App\Enums\PrintType;
use App\Models\Order;
use App\Models\Printer;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PrintJobForm
{
    /** Khai báo form theo dõi lệnh in và snapshot nội dung cần in. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Máy in và đơn hàng đều phải nằm trong Store hiện tại để
                // không thể gửi lệnh in hoặc payload sang thiết bị tenant khác.
                Select::make('printer_id')
                    ->label('Máy in')
                    ->options(fn () => Printer::query()->where('store_id', Filament::getTenant()?->getKey())->pluck('name', 'id'))
                    ->scopedExists(
                        Printer::class,
                        'id',
                        fn (Builder $query): Builder => $query->where('store_id', Filament::getTenant()?->getKey()),
                    )
                    ->required()
                    ->searchable(),
                Select::make('order_id')
                    ->label('Đơn hàng')
                    ->options(fn () => Order::query()->where('store_id', Filament::getTenant()?->getKey())->pluck('id', 'id'))
                    ->scopedExists(
                        Order::class,
                        'id',
                        fn (Builder $query): Builder => $query->where('store_id', Filament::getTenant()?->getKey()),
                    )
                    ->searchable(),
                Select::make('print_type')->label('Loại nội dung')->options(PrintType::class)->required(),
                Select::make('status')->label('Trạng thái')->options(PrintJobStatus::class)->required()->default(PrintJobStatus::Pending->value),
                TextInput::make('attempts')->label('Số lần thử')->numeric()->integer()->minValue(0)->default(0)->required(),
                Textarea::make('error_message')->label('Thông báo lỗi')->columnSpanFull(),
                KeyValue::make('payload')->label('Dữ liệu snapshot')->columnSpanFull(),
                DateTimePicker::make('printed_at')->label('Thời điểm in thành công'),
            ]);
    }
}
