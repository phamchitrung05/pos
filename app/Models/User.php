<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\AssignsCurrentStore;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/** Tài khoản đăng nhập, đồng thời là chủ thể nhận role, permission và quyền truy cập tenant. */
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use AssignsCurrentStore, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'name',
        'email',
        'password',
    ];

    /** Lấy chi nhánh mà người dùng đang trực thuộc. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Command đã được thực hiện dưới danh tính tài khoản này. */
    public function posCommands(): HasMany
    {
        return $this->hasMany(PosCommand::class);
    }

    /**
     * Chỉ cho tài khoản hợp lệ vào panel quản trị.
     *
     * Owner luôn được vào để quản trị toàn bộ hệ thống. Staff chỉ được vào khi
     * đã được gán vào một cửa hàng đang hoạt động, tránh tài khoản mồ côi truy
     * cập các trang không có ranh giới tenant rõ ràng.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isOwner() || (bool) $this->store?->is_active;
    }

    /**
     * Trả về danh sách cửa hàng xuất hiện trong bộ chuyển tenant của Filament.
     *
     * Owner xem được mọi chi nhánh. Staff chỉ nhận đúng quan hệ store của mình,
     * nên không thể chọn một chi nhánh khác từ giao diện.
     *
     * @return Collection<int, Store>
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->isOwner()) {
            return Store::query()->orderBy('name')->get();
        }

        return $this->store()->where('is_active', true)->get();
    }

    /** Chặn truy cập tenant bằng cách sửa ID trên URL, kể cả khi menu không hiển thị Store đó. */
    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Store && $this->canAccessStore((int) $tenant->getKey());
    }

    /**
     * Chọn tenant mặc định sau khi đăng nhập.
     *
     * Staff dùng cửa hàng được gán. Owner ưu tiên store_id nếu có, nếu không
     * dùng cửa hàng đầu tiên để vẫn vào được panel quản trị đa chi nhánh.
     */
    public function getDefaultTenant(Panel $panel): ?Model
    {
        if (! $this->isOwner()) {
            return $this->store;
        }

        return $this->store ?? Store::query()->oldest('id')->first();
    }

    /** Kiểm tra role owner tại một nơi duy nhất để tenancy và policy dùng cùng quy tắc. */
    public function isOwner(): bool
    {
        return $this->hasRole(UserRole::Owner->value);
    }

    /**
     * Kiểm tra ranh giới dữ liệu của một Store trong Model Policy.
     *
     * Owner được phép đi qua mọi Store. Staff bắt buộc có store_id và ID phải
     * trùng tuyệt đối; giá trị null không bao giờ được coi là tenant hợp lệ.
     */
    public function canAccessStore(?int $storeId): bool
    {
        return $this->isOwner()
            || ($storeId !== null && $this->store_id !== null && (int) $this->store_id === $storeId);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
