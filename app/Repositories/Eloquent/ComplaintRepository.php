<?php

namespace App\Repositories\Eloquent;


use App\Models\Citizen;
use App\Models\Complaint;
use App\Models\ComplaintFiles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use Illuminate\Support\Str;

class ComplaintRepository  extends BaseRepository implements ComplaintRepositoryInterface
{

    public function __construct(Complaint $model)
    {
        parent::__construct($model);
    }

    public function generateReferenceNumber(): string
    {
        // Format: COMP-2025-XXXXX-XXXXX
        $timestamp = now()->format('Y');
        $random = Str::upper(Str::random(5));
        $unique = substr(uniqid(), -5);

        return "COMP-{$timestamp}-{$random}-{$unique}";
    }

    public function attachFiles($complaintId, array $files)
    {
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
                if (!$file || !$file->isValid()) {
                    Log::warning("Invalid file for complaint {$complaintId}");
                    continue;
                }

                $path = $file->storePublicly('complaints', 's3');

                if (!$path) {
                    Log::error("Failed to upload file for complaint {$complaintId}");
                    continue;
                }
                $s3Url = "https://touqa200.s3.eu-north-1.amazonaws.com/$path";
                ComplaintFiles::create([
                    'complaint_id' => $complaintId,
                    'url' => $s3Url,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'type' => $file->getMimeType(),
                ]);

                Log::info("File uploaded successfully for complaint {$complaintId}: {$path}");

        }
    }

    public function getByReferenceNumber(string $referenceNumber): ?Complaint
    {
        return Complaint::where('reference_number', $referenceNumber)->first();
    }

    public function getByCitizen(int $citizenId)
    {
        return Complaint::where('citizen_id', $citizenId)
            ->with('files', 'governmentEntity')
            ->latest()
            ->get();
    }

    public function getByStatus(string $status)
    {
        return Complaint::where('status', $status)
            ->with('citizen', 'governmentEntity', 'files')
            ->latest()
            ->get();
    }

    public function getByGovernmentEntity(int $entityId)
    {
        return Complaint::where('government_entity_id', $entityId)
            ->with('citizen', 'files')
            ->latest()
            ->get();
    }

    public function updateStatus($complaint, string $status): Complaint
    {
        $complaint->update(['status' => $status]);
        return $complaint;    }

    public function create(array $data): Complaint
    {
        $data['reference_number'] = $this->generateReferenceNumber();
        return parent::create($data);
    }
}

