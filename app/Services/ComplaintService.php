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
    public function findByReference(string $referenceNumber)
    {
        $complaint = $this->complaints->getByReferenceNumber($referenceNumber);
        if ($complaint) {
            $complaint->load('files', 'notes');
        }
        return $complaint;
    }

    /**
     * Get complaints by citizen
     */
    public function getByCitizen(int $citizenId)
    {
        $complaints = $this->complaints->getByCitizen($citizenId)->load('notes');

        $complaints->each(function ($complaint) {
        });

        return $complaints;
    }

    /**
     * Get complaints by status
     */
    public function getByStatus(string $status)
    {
        $complaints = $this->complaints->getByStatus($status)->load('notes');

        $complaints->each(function ($complaint) {
        });

        return $complaints;
    }

    /**
     * Get complaints by government entity
     */
    public function getByEntity(int $entityId)
    {
        $complaints = $this->complaints->getByGovernmentEntity($entityId)->load('notes');

        $complaints->each(function ($complaint) {
        });

        return $complaints;
    }

    /**
     * Update status
     */
    public function updateStatus($complaint, string $status)
    {
        $updated = $this->complaints->updateStatus($complaint, $status);
        $updated->load('files', 'notes');
        return $updated;
    }
}
