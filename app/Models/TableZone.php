<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Khu vực dùng để phân loại và hiển thị các bàn trong cửa hàng. */
class TableZone extends Model
{
    use AssignsCurrentStore, HasFactory;

    protected $table = 'table_zones';

    protected $fillable = ['store_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class, 'zone_id');
    }
}
