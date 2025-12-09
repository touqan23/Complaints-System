<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintVersion;
use App\Models\Employee;
use App\Models\Notes;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Prompts\Note;
use Spatie\Activitylog\Models\Activity;

class ComplaintService
{
    public $complaints, $logs, $notification;

    public function __construct(ComplaintRepositoryInterface $complaints, LoggingService $logs, FirebaseNotificationService $notification)
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
        return DB::transaction(function () use ($data, $files) {
            $complaint = $this->complaints->create($data);
            $this->complaints->attachFiles($complaint->id, $files);
            $this->logs->complaint($complaint,"Complaint created SUCCESSFULLY", [
                "complaint_id" => $complaint->id
            ]);
            $complaint = $this->complaints->getByReferenceNumber($complaint->reference_number);
            return $complaint;
        });
    }

    public function startProcess (string $referenceNumber, $user)
    {
        return DB::transaction(function () use ($referenceNumber, $user) {

            $complaint = Complaint::where('reference_number', $referenceNumber)
                ->lockForUpdate()
                ->first();

            if (!$complaint) {
                return ['error' => 'Complaint not found'];
            }

            if ($complaint->locked_by != $user->id && $complaint->locked_by != null) {
                return ['error' => 'You are not allowed to unlock this complaint'];
            }

            // إذا لم تبدأ المعالجة من قبل نسجل الوقت
            if ($complaint->processing_started_at === null) {
                $complaint->processing_started_at = now();
            }
            $complaint->processed_by = $user->id;
            $this->complaints->acquireLock($complaint, $user->id);
            $this->complaints->updateStatus($complaint, 'processing');

            return $complaint;
        });
    }
    public function finishProcess (string $referenceNumber, $user)
    {
        $complaint = $this->complaints->findBy('reference_number', $referenceNumber);
        if (!$complaint) {
            return ['error'=>'Complaint not found'];
        }

        if (! $complaint->locked_by) {
            return ['error' => 'Complaint is not locked'];
        }

        if ($complaint->locked_by != $user->id) {
            return ['error' => 'You are not allowed to unlock this complaint'];
        }

        $complaint->processing_finished_at = now();

        // حساب وقت المعالجة بالدقائق
        if ($complaint->processing_started_at && $complaint->processing_finished_at) {
            $complaint->processing_time_minutes =
                $complaint->processing_started_at->diffInMinutes($complaint->processing_finished_at);
        }



        $this->complaints->deleteLock($complaint);

        return $complaint;
    }

    /**
     * Find complaint by reference number with files and notes
     */
    public function findByReference(string $referenceNumber, $user)
    {
        $complaint = $this->complaints->getByReferenceNumber($referenceNumber);
        $this->logs->complaint($complaint,"Complaint find SUCCESS", [
            "complaint_id" => $complaint->id,
        ]);

        if ($complaint) {
            $complaint->load([
                'files',
                'citizen.user',
                'department',
            ]);

            // إذا كان موظف، يرى جميع الملاحظات
            if ($user && $user->hasRole('employee')) {
                $complaint->load('notes');
                return $complaint;
            }
            // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
            else {
                $complaint->load(['notes' => function ($query) {
                    $query->where('requested_to_citizen', true);
                }]);
                return $complaint;
            }
        }
    }

    //find complaints by id
    public function findById($id)
    {
        $complaint = $this->complaints->find($id);
        $this->logs->complaint($complaint,"Complaint find SUCCESS", [
            "complaint_id" => $id,
        ]);
        return $complaint;
    }

    /**
     * Get complaints by citizen
     */
    public function getByCitizen(int $citizenId, $user)
    {
        $complaints = $this->complaints->getByCitizen($citizenId);

        // إذا كان موظف، يرى جميع الملاحظات
        if ($user && $user->hasRole('employee')) {
            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);
            $this->logs->complaint($complaints,"Complaint get with all information SUCCESS");
        }
        // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
        else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
            $this->logs->complaint($complaints,"Complaint get for citizen SUCCESS");
        }

        return $complaints;
    }

    public function getByCitizenNationalNumber(int $nationalNumber, $user)
    {
        $complaints = $this->complaints->getByCitizenNationalNumber($nationalNumber);

        if ($user && $user->hasRole('employee')) {
            // employee sees all notes
            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);
            $this->logs->complaint($complaints,"Complaint get with all information SUCCESS");
        } else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
            $this->logs->complaint($complaints,"Complaint get for citizen SUCCESS");
        }

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

        if ($user && $user->hasRole('employee')) {
            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);
            foreach ($complaints as $complaint) {
                $this->logs->complaint($complaint, "Complaint get with all information SUCCESS");
            }        } else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            }]);
            foreach ($complaints as $complaint) {
                $this->logs->complaint($complaint,"Complaint get for citizen SUCCESS");
            }        }

        return $complaints;
    }

    /**
     * Get complaints by department
     */
    public function getByDepartment(int $departmentId, $user)
    {
        $complaints = $this->complaints->getByDepartmentId($departmentId);

        if ($user && $user->hasRole('employee')) {
            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);
            //$this->logs->complaint($complaints,"Complaint get with all information SUCCESS");
            foreach ($complaints as $complaint) {
                $this->logs->complaint($complaint,"Complaint get with all information SUCCESS");
            }
        } else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
            //$this->logs->complaint($complaints,"Complaint get for citizen SUCCESS");
            foreach ($complaints as $complaint) {
                $this->logs->complaint($complaint,"Complaint get for citizen SUCCESS");
            }
        }

        return $complaints;
    }


    /**
     * Update status
     */
    public function updateStatus($complaint, string $status, $user = null)
    {
        $oldStatus = $complaint->status;
        $updated = $this->complaints->updateStatus($complaint, $status);
        $this->logs->complaint($updated,"Status update", [
            "complaint_id" => $complaint->id,
            "new_status" => $status
        ]);

        if ($status !== 'resubmitted') {
            $reference_number = $this->complaints->find($complaint->id)->reference_number;
            $this->notification->notifyUser(
                $user,
                "Complaint status Updated",
                "Your Complaint {$reference_number} has been {$status} , please check it.",
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
    public function updateComplaintByCitizen($complaint, array $data, $files = [], $user = null)
    {
        return DB::transaction(function () use ($complaint, $data, $files, $user) {

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
                    'new_value' => count($files) . " ملف جديد",
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

            $this->logs->complaint($complaint,"Status update", [
                "complaint_id" => $complaint->id,
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
        });
    }


    ///////////////////notes
    public function addEmployeeNote($complaintId, $employeeId, string $noteText, bool $requestedToCitizen)
    {
        return DB::transaction(function () use ($complaintId, $employeeId, $noteText, $requestedToCitizen) {

            $user = Employee::find($employeeId);
            $complaint = $this->complaints->find($complaintId);
            if (!$complaint) {
                throw new \Exception("Complaint not found.");
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
        });
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

        $this->logs->complaint($complaint,"Employee deleted a note", [
            "note_id" => $noteId
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
        $this->logs->complaint($complaint,"Employee updated a note", [
            "note_id" => $noteId
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
