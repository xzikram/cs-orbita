<?php

namespace App\Events;

use App\Models\AuditSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditSessionRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $auditSession;

    public function __construct(AuditSession $auditSession)
    {
        $this->auditSession = $auditSession;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin-approvals'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->auditSession->id,
            'uuid' => $this->auditSession->uuid,
            'name' => $this->auditSession->name,
            'unit' => $this->auditSession->unit,
            'status' => $this->auditSession->status,
            'created_at' => $this->auditSession->created_at->toIso8601String(),
        ];
    }
}
