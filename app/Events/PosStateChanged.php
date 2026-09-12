<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Thông báo projection POS của một bàn đã thay đổi sau khi transaction commit. */
final class PosStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** Chỉ đưa event vào queue sau commit để không phát trạng thái có thể rollback. */
    public bool $afterCommit = true;

    public function __construct(
        public readonly int $storeId,
        public readonly int $tableId,
        public readonly string $change,
    ) {}

    /** Private channel theo Store ngăn tài khoản tenant khác nghe dữ liệu vận hành. */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("stores.{$this->storeId}.pos");
    }

    /** Tên ổn định giúp Livewire và Tauri không phụ thuộc namespace PHP. */
    public function broadcastAs(): string
    {
        return 'pos.state.changed';
    }

    /** Chỉ phát metadata cần để client quyết định component nào phải refresh. */
    public function broadcastWith(): array
    {
        return [
            'storeId' => $this->storeId,
            'tableId' => $this->tableId,
            'change' => $this->change,
            'occurredAt' => now()->toIso8601String(),
        ];
    }
}
