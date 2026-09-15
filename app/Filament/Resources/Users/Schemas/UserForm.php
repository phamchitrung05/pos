<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    /** Khai báo form tài khoản nhân viên và chi nhánh phụ trách. */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Không nhận store_id từ form. Filament tự gán Store đang chọn
                // trên URL, nhờ đó owner không thể vô tình tạo user sai tenant.
                TextInput::make('name')->label('Họ và tên')->required()->maxLength(255),
                TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('password')->label('Mật khẩu')->password()->revealable()->required(fn (string $operation): bool => $operation === 'create')->dehydrated(fn (?string $state): bool => filled($state)),
                // UserResource chỉ dành cho owner và chỉ cho phép cấp role
                // staff, tránh nâng quyền một tài khoản thành owner từ UI.
                Select::make('roles')
                    ->label('Vai trò')
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('name', UserRole::Staff->value),
                    )
                    ->getOptionLabelFromRecordUsing(fn (): string => 'Nhân viên')
                    ->multiple()
                    ->maxItems(1)
                    ->preload()
                    ->required(),
            ]);
    }
}
