<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AuditLink;
use App\Models\AuditSession;
use App\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $nonAdmin;
    protected AuditLink $link;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'role' => RoleEnum::ADMINISTRATOR,
            'is_active' => true,
        ]);

        // Create non-admin user
        $this->nonAdmin = User::factory()->create([
            'role' => RoleEnum::CLEANING_SERVICE,
            'is_active' => true,
        ]);

        // Create an audit link
        $this->link = AuditLink::create([
            'uuid' => (string) Str::uuid(),
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_get_active_sessions(): void
    {
        // Create an active (approved & unexpired) session
        $activeSession = AuditSession::create([
            'uuid' => (string) Str::uuid(),
            'audit_link_id' => $this->link->id,
            'name' => 'John Auditor',
            'unit' => 'SPI',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        // Create a pending session (should not show in active)
        $pendingSession = AuditSession::create([
            'uuid' => (string) Str::uuid(),
            'audit_link_id' => $this->link->id,
            'name' => 'Pending Auditor',
            'unit' => 'Management',
            'status' => 'pending',
        ]);

        // Create an expired session (should not show in active)
        $expiredSession = AuditSession::create([
            'uuid' => (string) Str::uuid(),
            'audit_link_id' => $this->link->id,
            'name' => 'Expired Auditor',
            'unit' => 'Management',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now()->subDay(),
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/audit-sessions/active');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $activeSession->uuid)
            ->assertJsonPath('data.0.name', 'John Auditor');
    }

    public function test_admin_can_revoke_session(): void
    {
        $session = AuditSession::create([
            'uuid' => (string) Str::uuid(),
            'audit_link_id' => $this->link->id,
            'name' => 'John Auditor',
            'unit' => 'SPI',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/audit-sessions/{$session->id}/revoke");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Sesi berhasil diputuskan / diputus.');

        // Assert database values changed
        $this->assertDatabaseHas('audit_sessions', [
            'id' => $session->id,
            'status' => 'rejected',
        ]);

        $updatedSession = AuditSession::find($session->id);
        $this->assertTrue(now()->diffInSeconds($updatedSession->expires_at) < 5);
    }

    public function test_non_admin_cannot_access_active_or_revoke_sessions(): void
    {
        $session = AuditSession::create([
            'uuid' => (string) Str::uuid(),
            'audit_link_id' => $this->link->id,
            'name' => 'John Auditor',
            'unit' => 'SPI',
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        // Non-admin get active sessions
        $response = $this->actingAs($this->nonAdmin)
            ->getJson('/api/v1/admin/audit-sessions/active');
        $response->assertStatus(403);

        // Non-admin revoke session
        $response = $this->actingAs($this->nonAdmin)
            ->putJson("/api/v1/admin/audit-sessions/{$session->id}/revoke");
        $response->assertStatus(403);

        // Guest get active sessions
        $response = $this->getJson('/api/v1/admin/audit-sessions/active');
        $response->assertStatus(403);
    }
}
