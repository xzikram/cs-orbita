<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /**
     * Get list of backups
     */
    public function index()
    {
        $files = Storage::disk('local')->files('backups');
        $backups = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                $backups[] = [
                    'filename' => basename($file),
                    'size' => Storage::disk('local')->size($file),
                    'created_at' => date('Y-m-d H:i:s', Storage::disk('local')->lastModified($file)),
                ];
            }
        }

        // Sort by created_at desc
        usort($backups, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return response()->json(['data' => $backups]);
    }

    /**
     * Create a backup
     */
    public function store()
    {
        // Increase time and memory limit for backing up large databases
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        try {
            $sql = $this->generateSqlDump();
            $filename = 'backup_' . date('Y_m_d_His') . '.zip';
            
            // Ensure directory exists
            if (!Storage::disk('local')->exists('backups')) {
                Storage::disk('local')->makeDirectory('backups');
            }

            // Create temporary zip file
            $tempZipPath = tempnam(sys_get_temp_dir(), 'backup_zip');
            
            $zip = new \ZipArchive();
            if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                // Add database.sql
                $zip->addFromString('database.sql', $sql);
                
                // Add public files recursively
                $publicPath = storage_path('app/public');
                if (is_dir($publicPath)) {
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($publicPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    foreach ($files as $name => $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = 'public/' . substr($filePath, strlen($publicPath) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                }
                
                $zip->close();
            } else {
                throw new \Exception('Gagal membuat file ZIP backup.');
            }

            // Put stream to Laravel storage disk
            $stream = fopen($tempZipPath, 'r');
            Storage::disk('local')->put('backups/' . $filename, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($tempZipPath);

            return response()->json([
                'message' => 'Backup berhasil dibuat.',
                'backup' => [
                    'filename' => $filename,
                    'size' => Storage::disk('local')->size('backups/' . $filename),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal membuat backup: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download backup file
     */
    public function download($filename)
    {
        // Prevent path traversal
        $filename = basename($filename);
        $path = storage_path('app/backups/' . $filename);

        // If the storage disk is faked or the file doesn't exist on the real path, copy it from Storage first
        if (!file_exists($path)) {
            $filePath = 'backups/' . $filename;
            if (!Storage::disk('local')->exists($filePath)) {
                abort(404, 'File backup tidak ditemukan.');
            }
            
            // Create directories if needed
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            
            $stream = Storage::disk('local')->readStream($filePath);
            file_put_contents($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return response()->download($path);
    }

    /**
     * Delete backup file
     */
    public function destroy($filename)
    {
        // Prevent path traversal
        $filename = basename($filename);
        $filePath = 'backups/' . $filename;
        if (!Storage::disk('local')->exists($filePath)) {
            return response()->json(['message' => 'File backup tidak ditemukan.'], 404);
        }

        Storage::disk('local')->delete($filePath);

        // Also delete from real local path if it was cached for download
        $localPath = storage_path('app/backups/' . $filename);
        if (file_exists($localPath)) {
            @unlink($localPath);
        }

        return response()->json(['message' => 'Backup berhasil dihapus.']);
    }

    /**
     * Restore from a saved backup file
     */
    public function restoreFromFile($filename)
    {
        // Prevent path traversal
        $filename = basename($filename);
        $filePath = 'backups/' . $filename;
        if (!Storage::disk('local')->exists($filePath)) {
            return response()->json(['message' => 'File backup tidak ditemukan.'], 404);
        }

        try {
            // Copy from Laravel Storage to local temp path for Zip extraction
            $tempZipPath = tempnam(sys_get_temp_dir(), 'restore_zip');
            $stream = Storage::disk('local')->readStream($filePath);
            file_put_contents($tempZipPath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $this->performRestoreFromZip($tempZipPath);
            @unlink($tempZipPath);

            return response()->json(['message' => 'Database dan file berhasil direstore dari file ' . $filename]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal melakukan restore: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Restore from an uploaded backup file
     */
    public function uploadAndRestore(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file',
        ]);

        $file = $request->file('backup_file');
        
        // Check extension
        if ($file->getClientOriginalExtension() !== 'zip') {
            return response()->json(['message' => 'Format file tidak valid. Harus berupa file .zip.'], 400);
        }

        try {
            $zipFilePath = $file->getRealPath();
            $this->performRestoreFromZip($zipFilePath);

            return response()->json(['message' => 'Database dan file berhasil direstore dari file yang diupload.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal melakukan restore: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Perform restore from ZIP archive
     */
    private function performRestoreFromZip($zipFilePath)
    {
        // Increase memory and time limits
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        // Create temp folder
        $tempPath = storage_path('app/temp_restore_' . time() . '_' . uniqid());
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath) === true) {
                $zip->extractTo($tempPath);
                $zip->close();
            } else {
                throw new \Exception('Gagal membuka file ZIP backup.');
            }

            // 1. Restore database from database.sql
            $sqlPath = $tempPath . '/database.sql';
            if (!file_exists($sqlPath)) {
                throw new \Exception('File database.sql tidak ditemukan dalam ZIP backup.');
            }
            $sql = file_get_contents($sqlPath);
            $this->executeSqlDump($sql);

            // 2. Restore public files from public/ directory
            $backupPublicPath = $tempPath . '/public';
            if (is_dir($backupPublicPath)) {
                $destPublicPath = storage_path('app/public');
                $this->recursiveCopy($backupPublicPath, $destPublicPath);
            }

            // Cleanup temp
            $this->recursiveDelete($tempPath);
        } catch (\Exception $e) {
            $this->recursiveDelete($tempPath);
            throw $e;
        }
    }

    /**
     * Recursively copy files and directories
     */
    private function recursiveCopy($src, $dst)
    {
        if (!is_dir($src)) {
            return;
        }
        if (!file_exists($dst)) {
            mkdir($dst, 0777, true);
        }
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Recursively delete directories and files
     */
    private function recursiveDelete($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : @unlink("$dir/$file");
        }
        return @rmdir($dir);
    }

    /**
     * Helper to generate SQL dump
     */
    private function generateSqlDump()
    {
        $isSqlite = DB::getDriverName() === 'sqlite';
        
        if ($isSqlite) {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $key = 'name';
        } else {
            $tables = DB::select('SHOW TABLES');
            $dbName = config('database.connections.mysql.database');
            $key = 'Tables_in_' . $dbName;
        }
        
        $output = "-- CLEANTRACK RS DATABASE BACKUP\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        
        if ($isSqlite) {
            $output .= "PRAGMA foreign_keys = OFF;\n\n";
        } else {
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        }

        foreach ($tables as $tableObj) {
            if ($isSqlite) {
                $tableName = $tableObj->$key;
            } else {
                if (!isset($tableObj->$key)) {
                    $keys = array_keys((array)$tableObj);
                    $key = $keys[0];
                }
                $tableName = $tableObj->$key;
            }
            
            // Get structure
            if ($isSqlite) {
                $structureResult = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$tableName}'");
                $createTableSql = $structureResult[0]->sql;
            } else {
                $structureResult = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $createTableSql = $structureResult[0]->{'Create Table'};
            }
            
            $output .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $output .= $createTableSql . ";\n\n";
            
            // Get data
            $rows = DB::select("SELECT * FROM `{$tableName}`");
            if (count($rows) > 0) {
                $output .= "-- Dumping data for table `{$tableName}`\n";
                foreach ($rows as $row) {
                    $cols = [];
                    $vals = [];
                    foreach ((array)$row as $col => $val) {
                        $cols[] = "`{$col}`";
                        if (is_null($val)) {
                            $vals[] = "NULL";
                        } else {
                            $vals[] = DB::getPdo()->quote($val);
                        }
                    }
                    $output .= "INSERT INTO `{$tableName}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
                }
                $output .= "\n";
            }
        }
        
        if ($isSqlite) {
            $output .= "PRAGMA foreign_keys = ON;\n";
        } else {
            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        }
        return $output;
    }

    /**
     * Helper to execute SQL dump
     */
    private function executeSqlDump($sql)
    {
        // Increase memory and time limits
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $isSqlite = DB::getDriverName() === 'sqlite';

        DB::transaction(function () use ($sql, $isSqlite) {
            // Disable foreign key checks
            if ($isSqlite) {
                DB::statement('PRAGMA foreign_keys = OFF');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            // Execute the SQL queries
            DB::unprepared($sql);

            // Enable foreign key checks
            if ($isSqlite) {
                DB::statement('PRAGMA foreign_keys = ON');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });
    }
}
