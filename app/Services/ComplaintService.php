<?php

namespace App\Services;

use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use Illuminate\Support\Facades\DB;

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
            $complaint->load('files');

            // إذا كان موظف، يرى جميع الملاحظات
            if ($user && $user->hasRole('employee')) {
//                print "innnnn if belongs to employee";
                $complaint->load('notes');
            }
            // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
            else {
                $complaint->load('notes');

                $complaint->load(['notes' => function ($query) {
                    $query->where('requested_to_citizen', true);
                }]);
            }
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
        if ($user && $user->hasRole('employee')) {
            $complaints->load('notes');
        }
        // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
        else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            }]);
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
            $complaints->load('notes');
        }
        // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
        else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            }]);
        }

        return $complaints;
    }

    /**
     * Get complaints by government entity
     */
    public function getByEntity(int $entityId, $user)
    {
        $complaints = $this->complaints->getByGovernmentEntity($entityId);

        // إذا كان موظف، يرى جميع الملاحظات
        if ($user && $user->hasRole('employee')) {
            $complaints->load('notes');
        }
        // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
        else {
            $complaints->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            }]);
        }

        return $complaints;
    }

    /**
     * Update status
     */
    public function updateStatus($complaint, string $status, $user = null)
    {
        $updated = $this->complaints->updateStatus($complaint, $status);
        $updated->load('files');

        // إذا كان موظف، يرى جميع الملاحظات
        if ($user && $user->is_employee) {
            $updated->load('notes');
        }
        // إذا كان مواطن، يرى فقط الملاحظات المطلوبة منه
        else if ($user) {
            $updated->load(['notes' => function ($query) {
                $query->where('requested_to_citizen', true);
            }]);
        }

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
            $complaint->load('files');

            // المواطن يرى فقط الملاحظات المطلوبة منه
            if ($user && !$user->hasRole('employee')) {
                $complaint->load(['notes' => function ($query) {
                    $query->where('requested_to_citizen', true);
                }]);
            }

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

            // 4) نحضّر الشكوى مع الملاحظات
            $complaint->load('notes');

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
        return $this->complaints->deleteNote($noteId);
    }

    public function updateNote(int $noteId, array $data)
    {
        return $this->complaints->updateNote($noteId, $data);
    }



}
