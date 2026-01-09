<?php

namespace App\Services;

use App\Enums\NotificationPlatform;
use App\Jobs\UploadComplaintFilesJob;
use App\Models\Complaint;
use App\Models\ComplaintVersion;
use App\Models\Employee;
use App\Models\Notes;
use App\Repositories\Eloquent\ComplaintRepository;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Prompts\Note;
use Spatie\Activitylog\Models\Activity;

class ComplaintService
{
    public $complaints, $logs, $notification;

    public function __construct(ComplaintRepository $complaints, LoggingService $logs, FirebaseNotificationService $notification)
    {
        $this->complaints = $complaints;
        $this->logs = $logs;
        $this->notification = $notification;
    }

    /**
     * Create a new complaint with files if any.
     */
    public function createComplaint(array $data, $files)
    {
        // Create complaint
        $complaint = $this->complaints->create($data);

        // Create file records and dispatch upload jobs
        if (!empty($files)) {
            $fileIds = $this->complaints->createPendingFileRecords($complaint->id, $files);

            // Dispatch a job for each file
            foreach ($fileIds as $fileId) {
                UploadComplaintFilesJob::dispatch($fileId);
            }

            Log::channel('system')->info("Dispatched " . count($fileIds) . " file upload jobs for complaint {$complaint->id}");
        }

        // Log complaint creation
        $this->logs->complaint($complaint, "Complaint created SUCCESSFULLY", [
            "complaint_id" => $complaint->id,
            "reference_number" => $complaint->reference_number,
        ]);

        // Reload complaint with relations
        $complaint = $this->complaints->getByReferenceNumber($complaint->reference_number);

        return $complaint;
    }

    /**
     * Get complaint with file status details
     */
    public function getComplaintWithFileStatus(string $referenceNumber)
    {
        $complaint = $this->complaints->getByReferenceNumber($referenceNumber);

        if ($complaint) {
            $complaint->files_status = $this->complaints->getFilesStatusSummary($complaint->id);
            $complaint->all_files_processed = $this->complaints->areAllFilesProcessed($complaint->id);
        }
        $this->logs->complaint($complaint, "Get complaint file status SUCCESSFULLY", [
            "reference_number" => $referenceNumber
        ]);

        return $complaint;
    }

    /**
     * Retry failed file uploads for a complaint
     */
    public function retryFailedFileUploads(string $referenceNumber)
    {
        $complaint = $this->complaints->getByReferenceNumber($referenceNumber);

        if (!$complaint) {
            throw new \Exception("Complaint not found");
        }

        $retriedCount = $this->complaints->retryFailedUploads($complaint->id);

        $this->logs->complaint($complaint, "Retried failed file uploads", [
            "complaint_id" => $complaint->id,
            "retried_count" => $retriedCount
        ]);

        return $retriedCount;
    }

    public function startProcess (string $referenceNumber, $user) // transaction here
    {
        $complaint = Complaint::where('reference_number', $referenceNumber)
            ->lockForUpdate()
            ->first();

        if (!$complaint) {
            $this->logs->complaintWarning(null, "Start process FAILED — complaint not found", [
                "reference_number" => $referenceNumber,
                "user_id" => $user->id
            ]);
            return ['error' => 'Complaint not found'];
        }

        if ($complaint->locked_by != $user->id && $complaint->locked_by != null) {
            $this->logs->complaintWarning($complaint, "Start process BLOCKED — locked by another user", [
                "complaint_id" => $complaint->id,
                "locked_by" => $complaint->locked_by,
                "attempted_by" => $user->id
            ]);
            return ['error' => 'You are not allowed to unlock this complaint'];
        }

        // إذا لم تبدأ المعالجة من قبل نسجل الوقت
        if ($complaint->processing_started_at === null) {
            $complaint->processing_started_at = now();
        }
        $complaint->processed_by = $user->id;
        $this->complaints->acquireLock($complaint, $user->id);
        $this->complaints->updateStatus($complaint, 'processing');
        $this->logs->complaint($complaint, "Complaint processing STARTED", [
            "complaint_id" => $complaint->id,
            "processed_by" => $user->id,
            "started_at" => now()->toDateTimeString()
        ]);

        return $complaint;
    }
    public function finishProcess (string $referenceNumber, $user) // transaction here
    {
        $complaint = $this->complaints->findBy('reference_number', $referenceNumber);
        if (!$complaint) {
            $this->logs->complaintWarning(null, "Finish process FAILED — complaint not found", [
                "reference_number" => $referenceNumber,
                "user_id" => $user->id
            ]);
            return ['error'=>'Complaint not found'];
        }

        if (! $complaint->locked_by) {
            $this->logs->complaintWarning($complaint, "Finish process FAILED — complaint not locked", [
                "complaint_id" => $complaint->id,
                "user_id" => $user->id
            ]);
            return ['error' => 'Complaint is not locked'];
        }

        if ($complaint->locked_by != $user->id) {
            $this->logs->complaintWarning($complaint, "Finish process BLOCKED — not locked by user", [
                "complaint_id" => $complaint->id,
                "locked_by" => $complaint->locked_by,
            ]);
            return ['error' => 'You are not allowed to unlock this complaint'];
        }

        $complaint->processing_finished_at = now();

        // حساب وقت المعالجة بالدقائق
        if ($complaint->processing_started_at && $complaint->processing_finished_at) {
            $complaint->processing_time_minutes =
                $complaint->processing_started_at->diffInMinutes($complaint->processing_finished_at);
        }

        $this->complaints->deleteLock($complaint);
        $this->logs->complaint($complaint, "Complaint processing FINISHED", [
            "complaint_id" => $complaint->id,
            "processed_by" => $user->id
        ]);

        return ['user' => $user , 'complaint' => $complaint];
    }

    /**
     * Find complaint by reference number with files and notes
     */
    public function findByReference(string $referenceNumber, $user)
    {
        $complaint = $this->complaints->getByReferenceNumber($referenceNumber);
        if (!$complaint) {
            $this->logs->complaintWarning(null, "Complaint find FAILED — not found", [
                "reference_number" => $referenceNumber,
                "user_id" => $user?->id
            ]);
            return null;
        }

        $complaint->load([
            'files',
            'citizen.user',
            'department',
        ]);

        // إذا كان موظف، او ادمن يرى جميع الملاحظات
        if ($user &&  $user->hasAnyRole(['employee', 'admin'])) {
            $complaint->load('notes');
            return $complaint;
        }
        // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
        else {
            $complaint->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            }]);
        }
        $this->logs->complaint($complaint, "Complaint find SUCCESS", [
            "complaint_id" => $complaint->id,
            "reference_number" => $referenceNumber,
            "accessed_by" => $user?->id,
            "user_role" => $user?->roles->pluck('name')->first()
        ]);
        return $complaint;
    }

    //find complaints by id
    public function findById($id)
    {
        $complaint = $this->complaints->find($id);

        if ($complaint) {
            $this->logs->complaint($complaint, "Complaint find by ID SUCCESS", [
                "complaint_id" => $id
            ]);
        }

        return $complaint;
    }

    /**
     * Get complaints by citizen
     */
    public function getByCitizen(int $citizenId, $user)
    {
        $complaints = $this->complaints->getByCitizen($citizenId);

        // إذا كان موظف، يرى جميع الملاحظات
        if ($user &&  $user->hasAnyRole(['employee', 'admin'])) {
            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);
        }
        // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
        else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
        }

        $this->logs->complaint(null, "Get complaints by citizen SUCCESS", [
            "citizen_id" => $citizenId,
            "count" => $complaints->count(),
        ]);

        return $complaints;
    }

    public function getByCitizenNationalNumber(int $nationalNumber, $user)
    {
        $complaints = $this->complaints->getByCitizenNationalNumber($nationalNumber);

        if ($user &&  $user->hasAnyRole(['employee', 'admin'])) {
            // employee sees all notes
            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);
        } else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
        }

        $this->logs->complaint(null, "Get complaints by national number SUCCESS", [
            "national_number" => $nationalNumber,
            "count" => $complaints->count()
        ]);

        return $complaints;
    }


    /**
     * Get complaints by status
     */
    public function getByStatus(string $status, $user)
    {
        $complaints = $this->complaints->getByStatus($status);

        if ($user && $user->hasRole('admin')) {

            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);

            return $complaints->values();
        }

        if ($user && $user->hasRole('employee')) {

            $departmentId = $user->employee->department_id;

            $complaints = $complaints->where('department_id', $departmentId);

            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);

            return $complaints->values();
        }

        $complaints->load([
            'notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },
            'files',
            'citizen.user',
            'department'
        ]);

        $this->logs->complaint(null, "Get complaints by status SUCCESS", [
            "status" => $status,
            "count" => $complaints->count(),
            "accessed_by" => $user?->id
        ]);

        return $complaints
            ->where('citizen_id', $user->citizen->id)
            ->values();
    }

    /**
     * Get complaints by government entity
     */
    public function getByEntity(int $entityId, $user)
    {
        $complaints = $this->complaints->getByGovernmentEntity($entityId);

        if ($user &&  $user->hasAnyRole(['employee', 'admin'])) {
            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);
        } else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            }]);
        }
        $this->logs->complaint(null,"Complaints get for citizen SUCCESS");
        return $complaints;
    }

    /**
     * Get complaints by department
     */
    public function getByDepartment(int $departmentId, $user)
    {
        $complaints = $this->complaints->getByDepartmentId($departmentId);

        if ($user &&  $user->hasAnyRole(['employee', 'admin'])) {
            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);
        } else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
        }
        $this->logs->complaint(null,"Complaint get for citizen SUCCESS");
        return $complaints;
    }


    /**
     * Update status
     */
    public function updateStatus($complaint, string $status, $user = null)
    {
        $oldStatus = $complaint->status;
        $updated = $this->complaints->updateStatus($complaint, $status);
        $this->logs->complaint($updated, "Complaint status updated", [
            "complaint_id" => $complaint->id,
            "old_status" => $oldStatus,
            "new_status" => $status,
            "updated_by" => $user?->id
        ]);

        if($status === 'need_more_info'){
            $reference_number = $this->complaints->find($complaint->id)->reference_number;
            $this->notification->notifyUser(
                $user->id,
                NotificationPlatform::MOBILE,
                "Complaint is incomplete",
                "Your Complaint {$reference_number} has been {$status} , please check it and complete the miss information.",
                [
                    'type' => 'need more info',
                    'action' => 'updateStatus',
                ]
            );
        } else {
            $reference_number = $this->complaints->find($complaint->id)->reference_number;
            $this->notification->notifyUser(
                $user->id,
                NotificationPlatform::MOBILE,
                "Complaint status Updated",
                "Your Complaint {$reference_number} has been {$status} , you can check it.",
                [
                    'type' => 'Status updated',
                    'action' => 'updateStatus',
                ]
            );
        }
        $this->saveVersion($complaint, $user, 'status_changed', [
            [
                'action' => 'status_changed',
                'field_name' => 'status',
                'old_value' => $oldStatus,
                'new_value' => $status,
            ]
        ]);

        $updated->load([
            'notes',
            'files',
            'citizen.user',
            'department',
        ]);

        return $updated;
    }

    /**
     * Citizen Update complaint
     */
    public function updateComplaintByCitizen($complaint, array $data, $files = [], $user = null) //transaction here
    {
        $oldData = [
            'title' => $complaint->title,
            'description' => $complaint->description,
            'location' => $complaint->location,
        ];

        $complaint->update($data);

        $changes = [];
        foreach ($data as $field => $newValue) {
            if (isset($oldData[$field]) && $oldData[$field] != $newValue) {
                $changes[] = [
                    'action' => 'field_updated',
                    'field_name' => $field,
                    'old_value' => $oldData[$field],
                    'new_value' => $newValue,
                ];
            }
        }

        if (!empty($files)) {
            $this->complaints->attachFiles($complaint->id, $files);
            $changes[] = [
                'action' => 'attachments_added',
                'field_name' => 'files',
                'old_value' => null,
                'new_value' => count($files) . "new file",
            ];
        }
        $oldStatus = $complaint->status;
        $complaint = $this->complaints->updateStatus($complaint, 'resubmitted');
        $changes[] = [
            'action' => 'status_changed',
            'field_name' => 'status',
            'old_value' => $oldStatus,
            'new_value' => 'resubmitted',
        ];

        $this->saveVersion($complaint, $user, 'citizen_updated', $changes);

        $this->logs->complaint($complaint, "Citizen updated complaint", [
            "complaint_id" => $complaint->id,
            "updated_fields" => array_keys($data),
            "files_added" => !empty($files) ? count($files) : 0,
            "new_status" => 'resubmitted'
        ]);

        $complaint->load([
            'notes',
            'files',
            'citizen.user',
            'department',
        ]);
        // المواطن يرى فقط الملاحظات المطلوبة منه
        if ($user && $user->hasRole('citizen')) {
            $complaint->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
        }
        $this->logs->complaint($complaint,"Citizen updated the complaint", [
            "complaint_id" => $complaint->id
        ]);
        return $complaint;
    }


    ///////////////////notes
    public function addEmployeeNote($complaintId, $employeeId, string $noteText, bool $requestedToCitizen) //transaction here
    {
        $user = Employee::find($employeeId);
        $complaint = $this->complaints->find($complaintId);
        if (!$complaint) {
            //throw new \Exception("Complaint not found.");
            $this->logs->complaintWarning(null,"Complaint not found.");
        }
        $oldStatus = $complaint->status;


        // 2) نضيف الملاحظة
        $note = $this->complaints->addNote(
            $complaintId,
            $employeeId,
            $noteText,
            $requestedToCitizen
        );

        $this->logs->complaint($complaint,"Employee added a note", [
            "complaint_id" => $complaintId,
            "employee_id" => $employeeId,
        ]);

        $this->logs->complaint($complaint, "Employee added note", [
            "complaint_id" => $complaintId,
            "employee_id" => $employeeId,
            "requested_to_citizen" => $requestedToCitizen,
        ]);

        $changes = [
            [
                'action' => 'note_added',
                'field_name' => 'notes',
                'old_value' => null,
                'new_value' => $noteText,
            ]
        ];

        $this->complaints->updateStatus($complaint, 'processing');


        // 3) إذا كانت الملاحظة موجهة للمواطن → نغيّر حالة الشكوى
        if ($requestedToCitizen) {
            $this->complaints->updateStatus($complaint, 'need_more_info');
            $changes[] = [
                'action' => 'status_changed',
                'field_name' => 'status',
                'old_value' => $oldStatus,
                'new_value' => 'need_more_info',
            ];
            $changes[] = [
                'action' => 'note_requested_from_citizen',
                'field_name' => 'requested_to_citizen',
                'old_value' => 'false',
                'new_value' => 'true',
            ];
        }


        $this->saveVersion($complaint, $user->user, 'note_added', $changes);

        // 4) نحضّر الشكوى مع الملاحظات
        $complaint->load([
            'notes',
            'files',
            'citizen.user',
            'department',
        ]);;;

        return [
            'complaint' => $complaint,
        ];
    }

    public function getNotesForComplaint(int $complaintId)
    {
        return $this->complaints->getNotesByComplaint($complaintId);
    }

    public function getCitizenNotes(int $nationalNumber)
    {
        return $this->complaints->getCitizenNotes($nationalNumber);
    }

    public function deleteNote(int $noteId)
    {
        $note= Notes::findOrFail($noteId);
        $complaint = $this->complaints->getComplaintByNote($noteId);
        $noteContent = $note->note;
        $user = Auth::user();

        $this->logs->complaint($complaint, "Employee deleted note", [
            "note_id" => $noteId,
            "complaint_id" => $complaint->id,
            "deleted_by" => $user->id
        ]);

        $this->saveVersion($complaint, $user, 'note_deleted', [
            [
                'action' => 'note_deleted',
                'field_name' => 'notes',
                'old_value' => $noteContent,
                'new_value' => null,
            ]
        ]);
        return $this->complaints->deleteNote($noteId);
    }

    public function updateNote(int $noteId, array $data)
    {
        // Get the note (correct way)
        $note = Notes::findOrFail($noteId);

        // Get complaint linked to this note
        $complaint = $this->complaints->getComplaintByNote($noteId);

        // Store old + new values
        $oldValue = $note->note;
        $newValue = $data['note'];
        $user = Auth::user();

        // حفظ التغيير
        $this->saveVersion($complaint, $user, 'note_updated', [
            [
                'action' => 'note_updated',
                'field_name' => 'notes',
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]
        ]);
        // Apply the update
        $this->logs->complaint($complaint, "Employee updated note", [
            "note_id" => $noteId,
            "complaint_id" => $complaint->id,
            "updated_by" => $user->id
        ]);
        return $this->complaints->updateNote($noteId, $data);
    }

    public function getComplaintByNote(int $noteId)
    {
        return $this->complaints->getComplaintByNote($noteId);
    }

    //return complaint history from log table

    public function getHistory(Complaint $complaint)
    {
        return $complaint->activities()->orderBy('created_at', 'desc')->get();
    }

    public function getSystemActivity()
    {
        return Activity::with(['causer'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
////////versioning
    /**
     * Save detailed version with specific changes
     */
    private function saveVersion(Complaint $complaint, $user, string $action, array $specificChanges = [])
    {
        $currentData = $complaint->fresh()->toArray();
        $previousVersion = $complaint->versions()->latest()->first();
        $previousData = $previousVersion ? $previousVersion->snapshot : [];

        // إذا كانت هناك تغييرات محددة (مثل status, note, etc)
        if (!empty($specificChanges)) {
            foreach ($specificChanges as $change) {
                ComplaintVersion::create([
                    'complaint_id' => $complaint->id,
                    'snapshot'     => $currentData,
                    'version'      => ($previousVersion ? $previousVersion->version + 1 : 1),
                    'created_by'   => $user?->id,
                    'action'       => $change['action'],
                    'field_name'   => $change['field_name'] ?? null,
                    'old_value'    => $change['old_value'] ?? null,
                    'new_value'    => $change['new_value'] ?? null,
                ]);
                $previousVersion = $complaint->versions()->latest()->first(); // update for next iteration
            }
        } else {
            // حفظ snapshot عام بدون تفاصيل
            ComplaintVersion::create([
                'complaint_id' => $complaint->id,
                'snapshot'     => $currentData,
                'version'      => ($previousVersion ? $previousVersion->version + 1 : 1),
                'created_by'   => $user?->id,
                'action'       => $action,
            ]);
        }
    }

    public function history($referenceNumber)
    {
        $complaint = Complaint::where('reference_number', $referenceNumber)->firstOrFail();

        $versions = $complaint->versions()
            ->with('user:id,name,email')
            ->orderBy('version', 'desc')
            ->get()
            ->map(function ($version) {
                return [
                    'version' => $version->version,
                    'action' => $version->action,
                    'field_name' => $version->field_name,
                    'old_value' => $version->old_value,
                    'new_value' => $version->new_value,
                    'created_by' => $version->user ? $version->user->name : 'system',
                    'created_at' => $version->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $version->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            "status" => "success",
            "reference" => $referenceNumber,
            "total_versions" => $versions->count(),
            "versions" => $versions
        ]);
    }
}
