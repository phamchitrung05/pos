<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/** Bàn ăn thuộc một chi nhánh và có thể nằm trong một khu vực. */
class DiningTable extends Model
{
    use AssignsCurrentStore, HasFactory;

    protected $table = 'dining_table';

    protected $fillable = ['store_id', 'zone_id', 'name'];

    /**
     * Bảo đảm khu vực và bàn luôn cùng Store, kể cả khi dữ liệu được ghi ngoài Filament.
     */
    protected static function booted(): void
    {
        static::saving(function (DiningTable $diningTable): void {
            if ($diningTable->zone_id === null) {
                return;
            }

            $zoneStoreId = TableZone::query()->whereKey($diningTable->zone_id)->value('store_id');

            if ($zoneStoreId === null || ($diningTable->store_id !== null && (int) $diningTable->store_id !== (int) $zoneStoreId)) {
                throw ValidationException::withMessages([
                    'zone_id' => 'Khu vực được chọn không thuộc cửa hàng đang sở hữu bàn.',
                ]);
            }

            $diningTable->store_id = $zoneStoreId;
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TableZone::class, 'zone_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'table_id');
    }
}
