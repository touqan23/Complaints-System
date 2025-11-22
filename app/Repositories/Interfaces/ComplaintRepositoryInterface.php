<?php

namespace App\Repositories\Interfaces;

interface ComplaintRepositoryInterface extends BaseRepositoryInterface
{
    public function generateReferenceNumber();
    public function attachFiles($complaintId, array $files);
    public function getByReferenceNumber(string $referenceNumber);
    public function getByCitizen(int $citizenId);
    public function getByStatus(string $status);
    public function getByGovernmentEntity(int $entityId);
    public function updateStatus($complaint, string $status);
}
