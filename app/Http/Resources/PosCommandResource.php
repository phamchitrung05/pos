<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Response ổn định để Tauri lưu trạng thái terminal của command vào SQLite. */
final class PosCommandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'result' => $this->result,
            'error' => $this->error,
            'attempts' => $this->attempts,
            'processed_at' => $this->processed_at?->toIso8601String(),
        ];
    }
}
