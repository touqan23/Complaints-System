<?php

namespace App\Repositories\Interfaces;

use App\Models\Complaint;
use Laravel\Prompts\Note;

interface ComplaintRepositoryInterface extends BaseRepositoryInterface
{
    public function generateReferenceNumber();
    public function attachFiles($complaintId, array $files);
    public function getByReferenceNumber(string $referenceNumber);
    public function getByCitizen(int $citizenId);
    public function getByStatus(string $status);
    public function getByGovernmentEntity(int $entityId);
    public function updateStatus($complaint, string $status);//هاد للموظغ بعدل حالتها
    public function addNote(int $complaintId, int $employeeId, string $note, bool $requestedToCitizen = false);
    public function getNotesByComplaint(int $complaintId);
    public function getCitizenNotes(int $complaintId);
    public function getInternalNotes(int $complaintId);
    public function deleteNote(int $noteId);
    public function updateNote(int $noteId, array $data);
    public function acquireLock(Complaint $complaint, int $employeeId);
    public function refreshLock(Complaint $complaint);


}
