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
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
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
            $filename = 'backup_' . date('Y_m_d_His') . '.sql';
            
            // Ensure directory exists
            if (!Storage::disk('local')->exists('backups')) {
                Storage::disk('local')->makeDirectory('backups');
            }

            Storage::disk('local')->put('backups/' . $filename, $sql);

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
        if (!file_exists($path)) {
            abort(404, 'File backup tidak ditemukan.');
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
            $sql = Storage::disk('local')->get($filePath);
            $this->executeSqlDump($sql);

            return response()->json(['message' => 'Database berhasil direstore dari file ' . $filename]);
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
        if ($file->getClientOriginalExtension() !== 'sql') {
            return response()->json(['message' => 'Format file tidak valid. Harus berupa file .sql.'], 400);
        }

        try {
            $sql = file_get_contents($file->getRealPath());
            $this->executeSqlDump($sql);

            return response()->json(['message' => 'Database berhasil direstore dari file yang diupload.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal melakukan restore: ' . $e->getMessage()], 500);
        }
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
