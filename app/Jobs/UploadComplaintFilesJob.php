<?php

namespace App\Jobs;

use App\Enums\NotificationPlatform;
use App\Models\Citizen;
use App\Models\Complaint;
use App\Models\ComplaintFiles;
use App\Services\FirebaseNotificationService;
use App\Services\LoggingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadComplaintFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    protected int $fileId;

    public function __construct(int $fileId)
    {
        $this->fileId = $fileId;
    }

    public function handle(LoggingService $logs): void
    {
        $file = ComplaintFiles::find($this->fileId);

        if (!$file) {
            $logs->systemError(null, 'Upload job failed - file not found',
                new \Exception("File ID {$this->fileId} not found")
            );
            return;
        }

        if ($file->status === 'none') {
            $file->update(['status' => 'pending']);
        }

        try {
            if (!$file->local_path || !Storage::disk('local')->exists($file->local_path)) {
                throw new \Exception("Local file not found: {$file->local_path}");
            }

            $content = Storage::disk('local')->get($file->local_path);
            $s3Path = 'complaints/' . basename($file->local_path);

            Storage::disk('s3')->put($s3Path, $content, 'public');

            $url = Storage::disk('s3')->url($s3Path);

            $file->update([
                'url'    => $url,
                'status' => 'uploaded',
            ]);

            Storage::disk('local')->delete($file->local_path);

        } catch (\Throwable $e) {

            $logs->systemError(null, 'Complaint file upload failed', $e, [
                    'file_id' => $this->fileId,
                    'attempt' => $this->attempts(),
                ]);

            // إذا كانت آخر محاولة
            if ($this->attempts() >= $this->tries) {
                $file->update(['status' => 'failed']);

                $this->markComplaintNeedMoreInfo($file->complaint_id);
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $file = ComplaintFiles::find($this->fileId);

        if ($file) {
            $file->update(['status' => 'failed']);
            $this->markComplaintNeedMoreInfo($file->complaint_id);
        }
        app(LoggingService::class)->systemError(null, 'Upload job permanently failed',
            $exception, ['file_id' => $this->fileId]);
    }

    /**
     * أي فشل = need_more_info
     */
    protected function markComplaintNeedMoreInfo(int $complaintId, FirebaseNotificationService $service): void
    {
        $complaint =  Complaint::where('id', $complaintId)
            ->where('status', '!=', 'need_more_info')
            ->update(['status' => 'need_more_info']);

        $citizen = Citizen::where('id', $complaint->citizen_id)->first();

        $service->notifyUser(
            $citizen->user->id,
            NotificationPlatform::MOBILE,
            "Upload files permanently failed",
            "Upload files permanently failed in you complaint {$complaint->reference_number}.Please try again later by update the complaint.",
            [
                'type' => 'Upload files',
                'action' => 'Upload_files_failed',
            ]
        );

        Log::channel('system')->warning(
            "Complaint {$complaintId} marked as need_more_info"
        );
    }
}
