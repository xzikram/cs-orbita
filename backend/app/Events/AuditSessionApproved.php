<?php

namespace App\Events;

use App\Models\AuditSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditSessionApproved implements ShouldBroadcast
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
            new Channel('audit-session.' . $this->auditSession->uuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->auditSession->uuid,
            'status' => $this->auditSession->status,
            'approved_at' => $this->auditSession->approved_at?->toIso8601String(),
            'expires_at' => $this->auditSession->expires_at?->toIso8601String(),
        ];
    }
}
