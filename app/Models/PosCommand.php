<?php

namespace App\Models;

use App\Enums\PosCommandStatus;
use App\Enums\PosCommandType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nhật ký idempotency và kết quả của một thao tác ghi từ thiết bị POS. */
class PosCommand extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => PosCommandType::class,
            'status' => PosCommandStatus::class,
            'result' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
