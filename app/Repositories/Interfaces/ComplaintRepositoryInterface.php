<?php

namespace App\Repositories\Interfaces;

use App\Models\Complaint;
use Laravel\Prompts\Note;

interface ComplaintRepositoryInterface extends BaseRepositoryInterface
{
    public function generateReferenceNumber();//انشا الرقم التسلسلي للشكوى
    public function attachFiles($complaintId, array $files);//رفع الملفات على الs3
    public function getByReferenceNumber(string $referenceNumber);//رجع الشكوى بناء على رقمها التسلسلي

    public function getByCitizenNationalNumber(string $nationalNumber);    public function getByCitizen(int $citizenId);//رجع شكاوي المواطن
    public function getByStatus(string $status);//رجع الشكوى حسب حالتها
    public function getByGovernmentEntity(int $entityId);
    public function updateStatus($complaint, string $status);//هاد للموظف بعدل حالتها
    public function addNote(int $complaintId, int $employeeId, string $note, bool $requestedToCitizen = false);
    public function getNotesByComplaint(int $complaintId);
    public function getCitizenNotes(int $complaintId);
    public function getInternalNotes(int $complaintId);
    public function deleteNote(int $noteId);
    public function updateNote(int $noteId, array $data);
    public function acquireLock(Complaint $complaint, int $employeeId);
    public function refreshLock(Complaint $complaint);




}
