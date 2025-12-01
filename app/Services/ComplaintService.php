<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Notes;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Laravel\Prompts\Note;
use Spatie\Activitylog\Models\Activity;

class ComplaintService
{
    public $complaints;

    public function __construct(ComplaintRepositoryInterface $complaints)
    {
        $this->complaints = $complaints;
    }

    /**
     * Create a new complaint with files if any.
     */
    public function createComplaint(array $data, $files)
    {
        return DB::transaction(function () use ($data, $files) {
            $complaint = $this->complaints->create($data);
            $this->complaints->attachFiles($complaint->id, $files);
            $complaint = $this->complaints->getByReferenceNumber($complaint->reference_number);
            return $complaint;
        });
    }

    /**
     * Find complaint by reference number with files and notes
     */
    public function findByReference(string $referenceNumber, $user)
    {
        $complaint = $this->complaints->getByReferenceNumber($referenceNumber);

        if ($complaint) {
            $complaint->load([
                'files',
                'citizen.user',
                'department',
            ]);

            // إذا كان موظف، يرى جميع الملاحظات
            if ($user && $user->hasRole('employee')) {
                //print "innnnn if belongs to employee";
                $hasLock = $this->complaints->acquireLock($complaint, $user->id);
                $complaint->load('notes');
                return [
                    'complaint' => $complaint,
                    'has_lock' => $hasLock
                ];
            }
            // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
            else {
              //  print "innnnn if belongs to citizen";

               // $complaint->load('notes.employee.user');

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
        return $this->complaints->find($id);
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
            ]);;
        }
        // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
        else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
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
        } else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
        }

        return $complaints;
    }


    /**
     * Get complaints by status
     */
    public function getByStatus(string $status, $user)
    {
        $complaints = $this->complaints->getByStatus($status);

        // إذا كان موظف، يرى جميع الملاحظات
        if ($user && $user->hasRole('employee')) {
            $complaints->load([
                'notes',
                'files',
                'citizen.user',
                'department',
            ]);;
        }
        // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
        else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
        }

        return $complaints;
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
            ]);;;
        } else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            }]);
        }

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
        } else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            },'files' ,'citizen.user','department']);
        }

        return $complaints;
    }


    /**
     * Update status
     */
    public function updateStatus($complaint, string $status, $user = null)
    {
        $this->complaints->refreshLock($complaint);

        $updated = $this->complaints->updateStatus($complaint, $status);
        $updated->load([
            'notes',
            'files',
            'citizen.user',
            'department',
        ]);

        activity()
            ->performedOn($complaint)
            ->causedBy(auth()->user())
            ->withProperties(['status' => $status])
            ->log("Status changed to {$status}");

        return $updated;
    }

    /**
     * Citizen Update complaint
     */
    public function updateComplaintByCitizen($complaint, array $data, $files = [], $user = null)
    {
        return DB::transaction(function () use ($complaint, $data, $files, $user) {
            $complaint->update($data);

            if (!empty($files)) {
                $this->complaints->attachFiles($complaint->id, $files);
            }

            $complaint = $this->complaints->updateStatus($complaint, 'resubmitted');
            activity()
                ->performedOn($complaint)
                ->causedBy(auth()->user())
                ->withProperties(['status' => 'resubmitted'])
                ->log("Status changed to resubmitted");

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

            activity()
                ->performedOn($complaint)
                ->causedBy(auth()->user())
                ->withProperties($data)
                ->log('Citizen updated the complaint');


            return $complaint;
        });
    }


    ///////////////////notes
    public function addEmployeeNote($complaintId, $employeeId, string $noteText, bool $requestedToCitizen)
    {
        return DB::transaction(function () use ($complaintId, $employeeId, $noteText, $requestedToCitizen) {


            // 1) نحضر الشكوى
            $complaint = $this->complaints->find($complaintId);
            if (!$complaint) {
                throw new \Exception("Complaint not found.");
            }

            $this->complaints->refreshLock($complaint);

            // 2) نضيف الملاحظة
            $note = $this->complaints->addNote(
                $complaintId,
                $employeeId,
                $noteText,
                $requestedToCitizen
            );

            $this->complaints->updateStatus($complaint, 'processing');


            // 3) إذا كانت الملاحظة موجهة للمواطن → نغيّر حالة الشكوى
            if ($requestedToCitizen) {
                $this->complaints->updateStatus($complaint, 'need_more_info');
            }

            activity()
                ->performedOn($complaint)
                ->causedBy(auth()->user())
                ->withProperties([
                    'note' => $note->note,
                    'requested_to_citizen' => $requestedToCitizen,
                ])
                ->log('Employee added a note');


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

    public function getCitizenNotes(int $complaintId)
    {
        return $this->complaints->getCitizenNotes($complaintId);
    }

    public function deleteNote(int $noteId)
    {
        $note= Notes::findOrFail($noteId);
        $complaint = $this->complaints->getComplaintByNote($noteId);

        $this->complaints->refreshLock($complaint);

        activity()
            ->performedOn($complaint)
            ->causedBy(auth()->user())
            ->withProperties([
                'note deleted' => $note->note,
            ])
            ->log('Employee deleted a note');


        return $this->complaints->deleteNote($noteId);
    }

    public function updateNote(int $noteId, array $data)
    {
        // Get the note (correct way)
        $note = Notes::findOrFail($noteId);

        // Get complaint linked to this note
        $complaint = $this->complaints->getComplaintByNote($noteId);

        // Refresh the complaint lock
        $this->complaints->refreshLock($complaint);

        // Store old + new values
        $oldValue = $note->note;
        $newValue = $data['note'];

        // Log using Spatie - this supports arrays safely
        activity()
            ->performedOn($complaint)
            ->causedBy(auth()->user())
            ->withProperties([
                'old_note' => $oldValue,
                'new_note' => $newValue,
            //    'changes'  => $data,  // you can store all array values safely
            ])
            ->log('Note updated');

        // Apply the update
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



}
