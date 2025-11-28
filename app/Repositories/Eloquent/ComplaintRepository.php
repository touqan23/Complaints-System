<?php

namespace App\Repositories\Eloquent;


use App\Models\Citizen;
use App\Models\Complaint;
use App\Models\ComplaintFiles;
use App\Models\Department;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use Illuminate\Support\Str;
use App\Models\Notes;

class ComplaintRepository  extends BaseRepository implements ComplaintRepositoryInterface
{

    protected $expirationMinutes = 20;
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
        $departmentIds = Department::where('government_entity_id', $entityId)
            ->pluck('id');

        // return complaints belonging to those departments
        return Complaint::whereIn('department_id', $departmentIds)
            ->with('citizen', 'files')
            ->latest()
            ->get();
    }

    public function getByDepartmentId(int $departmentId)
    {
        return Complaint::where('department_id', $departmentId)
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

/////////////////////////////notes
    public function addNote(int $complaintId, int $employeeId, string $note, bool $requestedToCitizen = false): Notes
    {
        return Notes::create([
            'complaint_id' => $complaintId,
            'employee_id' => $employeeId,
            'note' => $note,
            'requested_to_citizen' => $requestedToCitizen,
        ]);
    }

    public function getNotesByComplaint(int $complaintId)
    {
        return Notes::where('complaint_id', $complaintId)
            ->with('employee')
            ->latest()
            ->get();
    }

    public function getCitizenNotes(int $complaintId)
    {
        return Notes::where('complaint_id', $complaintId)
            ->with('employee')
            ->latest()
            ->get();
    }

    public function getInternalNotes(int $complaintId)
    {
        return Notes::where('complaint_id', $complaintId)
            ->where('requested_to_citizen', false)
            ->with('employee')
            ->latest()
            ->get();
    }

    public function getComplaintByNote(int $noteId)
    {
        $note = Notes::find($noteId);
        return Complaint::where('id', $note->complaint_id)->first();
    }

    public function deleteNote(int $noteId): bool
    {
        return Notes::where('id', $noteId)->delete();
    }

    public function updateNote(int $noteId, array $data): ?Notes
    {
        $note = Notes::find($noteId);
        if (!$note) return null;

        $note->update($data);
        return $note;
    }

    // resource competition
    public function acquireLock(Complaint $complaint, int $userId)
    {
         //اذا غير مقفولة فيحصل الموظف على قفل او اذا القفل منتهي فيحصل الموظف الجديد على القفل
        if (!$complaint->locked_by ||
            $complaint->locked_at->diffInMinutes(now()) >= $this->expirationMinutes) {
            return $complaint->update([
                'locked_by' => $userId,
                'locked_at' => now()
            ]);
        }
        // اذا مقفولة بنفس الموظف يمكنه المتابعة
        if ($complaint->locked_by == $userId) {
            return true;
        }
        // مقفولة من موظف آخر
        return false;
    }

    public function refreshLock(Complaint $complaint)
    {
        return $complaint->update(['locked_at' => now()]);
    }
}

