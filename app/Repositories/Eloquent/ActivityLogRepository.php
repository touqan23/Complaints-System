<?php

namespace App\Repositories\Eloquent;

use App\Models\ActivityLog;
use App\Repositories\Eloquent\BaseRepository;
use App\Repositories\Interfaces\ActivityLogRepositoryInterface;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function getErrorLogs(array $filters = []): LengthAwarePaginator
    {
        $query = Activity::query()
            ->where('properties->level', 'error')
            ->orderByDesc('created_at');

        // Optional filters
        if (!empty($filters['channel'])) {
            $query->where('log_name', $filters['channel']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('causer_id', $filters['user_id']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
