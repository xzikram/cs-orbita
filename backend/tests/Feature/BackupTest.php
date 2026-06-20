<?php

namespace Tests\Feature;

use App\Models\User;
use App\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $nonAdmin;

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

        // Fake the local filesystem
        Storage::fake('local');
    }

    public function test_admin_can_generate_database_backup(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/backups');

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Backup berhasil dibuat.')
            ->assertJsonStructure([
                'message',
                'backup' => [
                    'filename',
                    'size',
                    'created_at'
                ]
            ]);

        $filename = $response->json('backup.filename');
        Storage::disk('local')->assertExists('backups/' . $filename);
    }

    public function test_admin_can_list_backups(): void
    {
        // Put a fake sql file in disk
        Storage::disk('local')->put('backups/backup_test_123.sql', 'SELECT 1;');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/backups');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'backup_test_123.sql');
    }

    public function test_admin_can_download_backup(): void
    {
        Storage::disk('local')->put('backups/backup_test_123.sql', 'SELECT 1;');
        
        // Laravel storage_path mapping
        $path = storage_path('app/backups/backup_test_123.sql');
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, 'SELECT 1;');

        $response = $this->actingAs($this->admin)
            ->get('/api/v1/admin/backups/backup_test_123.sql/download');

        $response->assertStatus(200)
            ->assertHeader('content-disposition', 'attachment; filename=backup_test_123.sql');
            
        @unlink($path);
    }

    public function test_admin_can_delete_backup(): void
    {
        Storage::disk('local')->put('backups/backup_test_123.sql', 'SELECT 1;');

        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/v1/admin/backups/backup_test_123.sql');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Backup berhasil dihapus.');

        Storage::disk('local')->assertMissing('backups/backup_test_123.sql');
    }

    public function test_admin_can_restore_database_from_file(): void
    {
        // We will put a fake SQL statement to create a temporary table, then assert it runs
        Storage::disk('local')->put(
            'backups/backup_restore_test.sql',
            "DROP TABLE IF EXISTS `test_restore_table`; CREATE TABLE `test_restore_table` (`id` int, `name` varchar(255)); INSERT INTO `test_restore_table` VALUES (1, 'Hello');"
        );

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/backups/backup_restore_test.sql/restore');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Database berhasil direstore dari file backup_restore_test.sql');

        // Check if the table was created and has data
        $result = \Illuminate\Support\Facades\Schema::hasTable('test_restore_table');
        $this->assertTrue($result);

        $data = DB::table('test_restore_table')->first();
        $this->assertEquals('Hello', $data->name);

        // Clean up
        \Illuminate\Support\Facades\Schema::dropIfExists('test_restore_table');
    }

    public function test_admin_can_restore_database_from_uploaded_file(): void
    {
        $sqlContent = "DROP TABLE IF EXISTS `test_upload_table`; CREATE TABLE `test_upload_table` (`id` int, `name` varchar(255)); INSERT INTO `test_upload_table` VALUES (2, 'Uploaded');";
        
        $file = UploadedFile::fake()->createWithContent('backup.sql', $sqlContent);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/backups/restore', [
                'backup_file' => $file
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Database berhasil direstore dari file yang diupload.');

        // Check if the table was created
        $result = \Illuminate\Support\Facades\Schema::hasTable('test_upload_table');
        $this->assertTrue($result);

        $data = DB::table('test_upload_table')->first();
        $this->assertEquals('Uploaded', $data->name);

        // Clean up
        \Illuminate\Support\Facades\Schema::dropIfExists('test_upload_table');
    }

    public function test_non_admin_cannot_access_backup_endpoints(): void
    {
        // Non-admin list backups
        $response = $this->actingAs($this->nonAdmin)
            ->getJson('/api/v1/admin/backups');
        $response->assertStatus(403);

        // Non-admin trigger backup
        $response = $this->actingAs($this->nonAdmin)
            ->postJson('/api/v1/admin/backups');
        $response->assertStatus(403);

        // Non-admin delete backup
        $response = $this->actingAs($this->nonAdmin)
            ->deleteJson('/api/v1/admin/backups/backup_test.sql');
        $response->assertStatus(403);
    }
}
