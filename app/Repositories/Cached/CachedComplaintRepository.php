<?php

namespace App\Repositories\Cached;

use App\Models\Complaint;
use App\Repositories\Eloquent\ComplaintRepository;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class CachedComplaintRepository extends CachedBaseRepository implements ComplaintRepositoryInterface
{
    protected int $cacheTTL = 10;

    public function __construct(ComplaintRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Invalidate complaint-specific caches
     */
    protected function invalidateSpecificCache($model): void
    {
        if ($model instanceof Complaint) {
            // Clear specific complaint caches
            Cache::forget("complaints:id:{$model->id}");
            Cache::forget("complaints:reference:{$model->reference_number}");
            Cache::forget("complaints:citizen:{$model->citizen_id}");
            Cache::forget("complaints:department:{$model->department_id}");
            Cache::forget("complaints:status:{$model->status}");

            // Clear related entity caches if government_entity_id exists
            if ($model->department) {
                Cache::forget("complaints:entity:{$model->department->government_entity_id}");
            }

            // Clear admin stats caches
            Cache::tags(['admin'])->flush();
        }
    }

    /* ================= READ METHODS (CACHED) ================= */

    public function find(int|string $id)
    {
        return Cache::tags(['complaints'])->remember(
            "complaints:id:{$id}",
            now()->addMinutes($this->cacheTTL),
            fn() => $this->repository->find($id)
        );
    }

    public function getByReferenceNumber(string $referenceNumber): ?Complaint
    {
        return Cache::tags(['complaints'])->remember(
            "complaints:reference:{$referenceNumber}",
            now()->addMinutes($this->cacheTTL),
            fn() => $this->repository->getByReferenceNumber($referenceNumber)
        );
    }

    public function getByCitizen(int $citizenId)
    {
        return Cache::tags(['complaints'])->remember(
            "complaints:citizen:{$citizenId}",
            now()->addMinutes(5),
            fn() => $this->repository->getByCitizen($citizenId)
        );
    }

    public function getByCitizenNationalNumber(string $nationalNumber)
    {
        return Cache::tags(['complaints'])->remember(
            "complaints:citizen:national:{$nationalNumber}",
            now()->addMinutes(5),
            fn() => $this->repository->getByCitizenNationalNumber($nationalNumber)
        );
    }

    public function getByStatus(string $status)
    {
        return Cache::tags(['complaints'])->remember(
            "complaints:status:{$status}",
            now()->addMinutes(3),
            fn() => $this->repository->getByStatus($status)
        );
    }

    public function getByGovernmentEntity(int $entityId)
    {
        return Cache::tags(['complaints'])->remember(
            "complaints:entity:{$entityId}",
            now()->addMinutes(5),
            fn() => $this->repository->getByGovernmentEntity($entityId)
        );
    }

    public function getByDepartmentId(int $departmentId)
    {
        return Cache::tags(['complaints'])->remember(
            "complaints:department:{$departmentId}",
            now()->addMinutes(5),
            fn() => $this->repository->getByDepartmentId($departmentId)
        );
    }

    /* ================= FILE-RELATED METHODS (CACHED) ================= */

    public function getFilesStatusSummary(int $complaintId): array
    {
        return Cache::tags(['complaints', 'files'])->remember(
            "complaints:files:status:{$complaintId}",
            now()->addMinutes(2),
            fn() => $this->repository->getFilesStatusSummary($complaintId)
        );
    }

    public function areAllFilesProcessed(int $complaintId): bool
    {
        return Cache::tags(['complaints', 'files'])->remember(
            "complaints:files:processed:{$complaintId}",
            now()->addMinutes(2),
            fn() => $this->repository->areAllFilesProcessed($complaintId)
        );
    }

    public function getFailedFiles(int $complaintId)
    {
        return Cache::tags(['complaints', 'files'])->remember(
            "complaints:files:failed:{$complaintId}",
            now()->addMinutes(3),
            fn() => $this->repository->getFailedFiles($complaintId)
        );
    }

    /* ================= NOTES METHODS (CACHED) ================= */

    public function getNotesByComplaint(int $complaintId)
    {
        return Cache::tags(['complaints', 'notes'])->remember(
            "complaints:notes:{$complaintId}",
            now()->addMinutes(5),
            fn() => $this->repository->getNotesByComplaint($complaintId)
        );
    }

    public function getCitizenNotes(int $nationalNumber)
    {
        return Cache::tags(['complaints', 'notes'])->remember(
            "complaints:notes:citizen:{$nationalNumber}",
            now()->addMinutes(5),
            fn() => $this->repository->getCitizenNotes($nationalNumber)
        );
    }

    public function getInternalNotes(int $complaintId)
    {
        return Cache::tags(['complaints', 'notes'])->remember(
            "complaints:notes:internal:{$complaintId}",
            now()->addMinutes(5),
            fn() => $this->repository->getInternalNotes($complaintId)
        );
    }

    public function getComplaintByNote(int $noteId)
    {
        return Cache::tags(['complaints', 'notes'])->remember(
            "complaints:by-note:{$noteId}",
            now()->addMinutes(5),
            fn() => $this->repository->getComplaintByNote($noteId)
        );
    }

    /* ================= WRITE METHODS (INVALIDATE CACHE) ================= */

    public function create(array $data): Complaint
    {
        $complaint = $this->repository->create($data);

        // Clear all complaint-related caches
        $this->flushCache();
        $this->invalidateSpecificCache($complaint);

        return $complaint;
    }

    public function updateStatus($complaint, string $status): Complaint
    {
        $oldStatus = $complaint->status;

        $updated = $this->repository->updateStatus($complaint, $status);

        // Clear caches
        $this->flushCache();
        $this->invalidateSpecificCache($updated);

        // Clear old status cache
        Cache::forget("complaints:status:{$oldStatus}");

        return $updated;
    }

    public function createPendingFileRecords(int $complaintId, array $files): array
    {
        $fileIds = $this->repository->createPendingFileRecords($complaintId, $files);

        // Clear file-related caches
        Cache::tags(['files'])->flush();
        Cache::forget("complaints:files:status:{$complaintId}");
        Cache::forget("complaints:files:processed:{$complaintId}");

        return $fileIds;
    }

    public function retryFailedUploads(int $complaintId): int
    {
        $count = $this->repository->retryFailedUploads($complaintId);

        // Clear file-related caches
        Cache::tags(['files'])->flush();
        Cache::forget("complaints:files:failed:{$complaintId}");
        Cache::forget("complaints:files:status:{$complaintId}");

        return $count;
    }

    /* ================= NOTES WRITE METHODS (INVALIDATE CACHE) ================= */

    public function addNote(int $complaintId, int $employeeId, string $note, bool $requestedToCitizen = false)
    {
        $noteRecord = $this->repository->addNote($complaintId, $employeeId, $note, $requestedToCitizen);

        // Clear notes caches
        Cache::tags(['notes'])->flush();
        Cache::forget("complaints:notes:{$complaintId}");
        Cache::forget("complaints:notes:internal:{$complaintId}");

        return $noteRecord;
    }

    public function updateNote(int $noteId, array $data)
    {
        $note = $this->repository->updateNote($noteId, $data);

        if ($note) {
            // Clear notes caches
            Cache::tags(['notes'])->flush();
            Cache::forget("complaints:notes:{$note->complaint_id}");
            Cache::forget("complaints:notes:internal:{$note->complaint_id}");
            Cache::forget("complaints:by-note:{$noteId}");
        }

        return $note;
    }

    public function deleteNote(int $noteId): bool
    {
        // Get note before deletion to clear related caches
        $note = $this->repository->getComplaintByNote($noteId);
        $complaintId = $note?->id;

        $result = $this->repository->deleteNote($noteId);

        if ($result && $complaintId) {
            // Clear notes caches
            Cache::tags(['notes'])->flush();
            Cache::forget("complaints:notes:{$complaintId}");
            Cache::forget("complaints:notes:internal:{$complaintId}");
            Cache::forget("complaints:by-note:{$noteId}");
        }

        return $result;
    }

    /* ================= LOCK METHODS (NO CACHE - Real-time required) ================= */

    public function acquireLock(Complaint $complaint, int $userId)
    {
        // No cache - locks must be real-time
        $result = $this->repository->acquireLock($complaint, $userId);

        // Clear complaint cache to reflect lock status
        Cache::forget("complaints:id:{$complaint->id}");

        return $result;
    }

    public function deleteLock(Complaint $complaint)
    {
        // No cache - locks must be real-time
        $result = $this->repository->deleteLock($complaint);

        // Clear complaint cache to reflect lock status
        Cache::forget("complaints:id:{$complaint->id}");

        return $result;
    }

    /* ================= HELPER METHODS (NO CACHE) ================= */

    public function generateReferenceNumber(): string
    {
        // No cache needed - generates unique values
        return $this->repository->generateReferenceNumber();
    }
}
