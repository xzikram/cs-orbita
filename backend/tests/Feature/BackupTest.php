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
        // Write a test photo to public storage
        $publicPath = storage_path('app/public');
        if (!file_exists($publicPath)) {
            mkdir($publicPath, 0777, true);
        }
        file_put_contents($publicPath . '/test_image.jpg', 'fake image content');

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
        $this->assertStringEndsWith('.zip', $filename);
        
        // Assert it exists on faked storage
        Storage::disk('local')->assertExists('backups/' . $filename);

        // Copy from faked disk to verify ZIP contents
        $tempZipPath = tempnam(sys_get_temp_dir(), 'zip');
        $stream = Storage::disk('local')->readStream('backups/' . $filename);
        file_put_contents($tempZipPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Open zip and verify content
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempZipPath));
        $this->assertNotFalse($zip->locateName('database.sql'));
        $this->assertNotFalse($zip->locateName('public/test_image.jpg'));
        $zip->close();

        // Clean up
        @unlink($publicPath . '/test_image.jpg');
        @unlink($tempZipPath);
    }

    public function test_admin_can_list_backups(): void
    {
        // Put a fake zip file in disk
        Storage::disk('local')->put('backups/backup_test_123.zip', 'fake zip');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/backups');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'backup_test_123.zip');
    }

    public function test_admin_can_download_backup(): void
    {
        Storage::disk('local')->put('backups/backup_test_123.zip', 'fake zip');

        $response = $this->actingAs($this->admin)
            ->get('/api/v1/admin/backups/backup_test_123.zip/download');

        $response->assertStatus(200)
            ->assertHeader('content-disposition', 'attachment; filename=backup_test_123.zip');
            
        // Delete locally downloaded cache if any
        $localPath = storage_path('app/backups/backup_test_123.zip');
        if (file_exists($localPath)) {
            @unlink($localPath);
        }
    }

    public function test_admin_can_delete_backup(): void
    {
        Storage::disk('local')->put('backups/backup_test_123.zip', 'fake zip');

        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/v1/admin/backups/backup_test_123.zip');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Backup berhasil dihapus.');

        Storage::disk('local')->assertMissing('backups/backup_test_123.zip');
    }

    public function test_admin_can_restore_database_from_file(): void
    {
        // Create a fake zip containing database.sql and a fake public file in temp path
        $tempZipPath = tempnam(sys_get_temp_dir(), 'zip');
        $sqlContent = "DROP TABLE IF EXISTS `test_restore_table`; CREATE TABLE `test_restore_table` (`id` int, `name` varchar(255)); INSERT INTO `test_restore_table` VALUES (1, 'Hello Zip');";
        
        $zip = new \ZipArchive();
        if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFromString('database.sql', $sqlContent);
            $zip->addFromString('public/restored_image.jpg', 'restored image content');
            $zip->close();
        }

        // Put the zip into the faked disk
        Storage::disk('local')->put('backups/backup_restore_test.zip', file_get_contents($tempZipPath));
        @unlink($tempZipPath);

        // Delete restored_image.jpg if it exists before restore
        $restoredImagePath = storage_path('app/public/restored_image.jpg');
        @unlink($restoredImagePath);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/backups/backup_restore_test.zip/restore');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Database dan file berhasil direstore dari file backup_restore_test.zip');

        // Check if the table was created and has data
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('test_restore_table'));
        $data = DB::table('test_restore_table')->first();
        $this->assertEquals('Hello Zip', $data->name);

        // Check if file was restored
        $this->assertFileExists($restoredImagePath);
        $this->assertEquals('restored image content', file_get_contents($restoredImagePath));

        // Clean up
        \Illuminate\Support\Facades\Schema::dropIfExists('test_restore_table');
        @unlink($restoredImagePath);
    }

    public function test_admin_can_restore_database_from_uploaded_file(): void
    {
        // Create a temporary zip file
        $tempZipPath = tempnam(sys_get_temp_dir(), 'zip');
        $sqlContent = "DROP TABLE IF EXISTS `test_upload_table`; CREATE TABLE `test_upload_table` (`id` int, `name` varchar(255)); INSERT INTO `test_upload_table` VALUES (2, 'Uploaded Zip');";
        
        $zip = new \ZipArchive();
        if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFromString('database.sql', $sqlContent);
            $zip->addFromString('public/uploaded_restored_image.jpg', 'uploaded restored image content');
            $zip->close();
        }

        $restoredImagePath = storage_path('app/public/uploaded_restored_image.jpg');
        @unlink($restoredImagePath);

        // Create UploadedFile instance
        $file = new UploadedFile(
            $tempZipPath,
            'backup.zip',
            'application/zip',
            null,
            true // test mode
        );

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/backups/restore', [
                'backup_file' => $file
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Database dan file berhasil direstore dari file yang diupload.');

        // Check if table was created
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('test_upload_table'));
        $data = DB::table('test_upload_table')->first();
        $this->assertEquals('Uploaded Zip', $data->name);

        // Check if file was restored
        $this->assertFileExists($restoredImagePath);
        $this->assertEquals('uploaded restored image content', file_get_contents($restoredImagePath));

        // Clean up
        \Illuminate\Support\Facades\Schema::dropIfExists('test_upload_table');
        @unlink($restoredImagePath);
        @unlink($tempZipPath);
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
            ->deleteJson('/api/v1/admin/backups/backup_test.zip');
        $response->assertStatus(403);
    }
}
