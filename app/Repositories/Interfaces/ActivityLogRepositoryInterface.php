<?php

namespace App\Repositories\Interfaces;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityLogRepositoryInterface {
    public function getErrorLogs(array $filters = []): LengthAwarePaginator;
}
