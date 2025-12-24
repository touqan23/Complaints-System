<?php

namespace App\Jobs;

use App\Models\Complaint;
use App\Models\ComplaintFiles;
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

    public function handle(): void
    {
        $file = ComplaintFiles::find($this->fileId);

        if (!$file) {
            Log::channel('system')->error("File not found: {$this->fileId}");
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

            Log::channel('system')->error(
                "Upload failed for file {$this->fileId}, attempt {$this->attempts()}: {$e->getMessage()}"
            );

            // إذا كانت آخر محاولة
            if ($this->attempts() >= $this->tries) {
                $file->update(['status' => 'failed']);

                // 🔥 أول فشل → تحديث الشكوى مباشرة
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

            // 🔥 ضمان التحديث حتى لو فشل فجأة
            $this->markComplaintNeedMoreInfo($file->complaint_id);
        }

        Log::channel('system')->error(
            "Upload job permanently failed for file {$this->fileId}: {$exception->getMessage()}"
        );
    }

    /**
     * أي فشل = need_more_info
     */
    protected function markComplaintNeedMoreInfo(int $complaintId): void
    {
        Complaint::where('id', $complaintId)
            ->where('status', '!=', 'need_more_info')
            ->update(['status' => 'need_more_info']);

        Log::channel('system')->warning(
            "Complaint {$complaintId} marked as need_more_info"
        );
    }
}
