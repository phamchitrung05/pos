<?php

namespace App\Models;

use App\Enums\PrinterPaperWidth;
use App\Enums\PrinterType;
use App\Models\Concerns\AssignsCurrentStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Cấu hình máy in mạng được sử dụng bởi một chi nhánh. */
class Printer extends Model
{
    use AssignsCurrentStore, HasFactory;

    protected $fillable = ['store_id', 'name', 'printer_type', 'ip_address', 'port', 'paper_width_mm', 'is_active'];

    /** Hash và gợi ý token không bao giờ được serialize cùng cấu hình máy in. */
    protected $hidden = ['api_token_hash'];

    /** Máy đang bật phải có endpoint TCP hợp lệ để PrintJob không mắc kẹt vĩnh viễn. */
    protected static function booted(): void
    {
        static::saving(function (Printer $printer): void {
            if (! $printer->is_active) {
                return;
            }

            if (blank($printer->ip_address) || filter_var($printer->ip_address, FILTER_VALIDATE_IP) === false) {
                throw ValidationException::withMessages(['ip_address' => 'Máy in đang hoạt động phải có địa chỉ IP hợp lệ.']);
            }

            if (! is_numeric($printer->port) || (int) $printer->port < 1 || (int) $printer->port > 65535) {
                throw ValidationException::withMessages(['port' => 'Cổng máy in phải nằm trong khoảng 1 đến 65535.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'printer_type' => PrinterType::class,
            'port' => 'integer',
            'paper_width_mm' => PrinterPaperWidth::class,
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

    /**
     * Cấp credential mới cho thiết bị và chỉ trả plaintext đúng một lần.
     *
     * Database chỉ giữ SHA-256 để bản sao dữ liệu không đủ đăng nhập API in.
     */
    public function issueApiToken(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'api_token_hash' => hash('sha256', $token),
            'api_token_hint' => substr($token, -8),
        ])->save();

        return $token;
    }
}
