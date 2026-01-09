<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use App\Models\BackupLog;
class RunBackup extends Command
{
    protected $signature = 'system:backup';
    protected $description = 'Take a full system backup and upload to S3';

    public function handle()
    {
        try {
            $this->info("Starting backup...");

            // تشغيل الـ backup باستخدام Artisan
            Artisan::call('backup:run', [
                '--only-db' => false,
            ]);

            // الحصول على آخر ملف backup
            $backupDisk = config('backup.backup.destination.disks')[0] ?? 's3';
            $backupName = config('backup.backup.name');

            $files = Storage::disk($backupDisk)->allFiles($backupName);

            // ترتيب الملفات حسب التاريخ والحصول على الأحدث
            $latest = collect($files)
                ->sortByDesc(function ($file) use ($backupDisk) {
                    return Storage::disk($backupDisk)->lastModified($file);
                })
                ->first();

            BackupLog::create([
                'file_path' => $latest,
                'disk' => $backupDisk,
                'success' => true,
                'message' => 'Backup completed successfully.',
            ]);

            $this->info("✅ Backup completed and logged: {$latest}");

        } catch (\Exception $e) {
            BackupLog::create([
                'file_path' => null,
                'disk' => 's3',
                'success' => false,
                'message' => $e->getMessage(),
            ]);

            $this->error("❌ Backup failed: " . $e->getMessage());
        }
    }
}
