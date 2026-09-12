<?php

namespace App\Filament\Resources\Printers\Tables;

use App\Models\Printer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrintersTable
{
    /** Hiển thị máy in theo chi nhánh, loại và trạng thái kết nối. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên máy in')->searchable()->sortable(),
                TextColumn::make('store.name')->label('Chi nhánh')->sortable(),
                TextColumn::make('printer_type')->label('Loại')->badge(),
                TextColumn::make('ip_address')->label('Địa chỉ IP'),
                TextColumn::make('port')->label('Cổng'),
                TextColumn::make('paper_width_mm')->label('Khổ giấy')->suffix(' mm'),
                IconColumn::make('is_active')->label('Hoạt động')->boolean(),
                TextColumn::make('api_token_hint')->label('Token agent')->formatStateUsing(
                    fn (?string $state): string => $state ? '••••'.$state : 'Chưa cấp',
                ),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('issueApiToken')
                    ->label('Cấp token agent')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->requiresConfirmation()
                    // Credential thiết bị là cấu hình hạ tầng, chỉ owner được phép xoay token.
                    ->visible(fn (): bool => Filament::auth()->user() instanceof User && Filament::auth()->user()->isOwner())
                    ->action(function (Printer $record): void {
                        $token = $record->issueApiToken();

                        Notification::make()
                            ->title('Token agent vừa được cấp')
                            ->body("Lưu token ngay, hệ thống sẽ không hiển thị lại:\n{$token}")
                            ->warning()
                            ->persistent()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
