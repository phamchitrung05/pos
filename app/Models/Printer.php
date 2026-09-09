<?php

namespace App\Models;

use App\Enums\PrinterType;
use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cấu hình máy in mạng được sử dụng bởi một chi nhánh. */
class Printer extends Model
{
    use AssignsCurrentStore, HasFactory;

    protected $fillable = ['store_id', 'name', 'printer_type', 'ip_address', 'port', 'is_active'];

    protected function casts(): array
    {
        return [
            'printer_type' => PrinterType::class,
            'port' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
