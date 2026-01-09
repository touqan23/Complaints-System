<?php

namespace App\Repositories\Cached;

use App\Models\Employee;
use App\Repositories\Eloquent\BaseRepository;
use App\Repositories\Eloquent\EmployeeRepository;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class CachedEmployeeRepository extends CachedBaseRepository implements EmployeeRepositoryInterface
{
    public function __construct(EmployeeRepository $repo)
    { parent::__construct($repo); }

    protected function invalidateSpecificCache($model): void
    {
        if ($model instanceof Employee) {
            // Invalidate specific employee caches if you have any
            Cache::forget("employee:{$model->id}");
            Cache::forget("employee:serial:{$model->serial_number}");
        }
    }


    /* ================= Dashboard ================= */

    public function getBackupStats()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:dashboard:backup_stats',
                now()->addMinutes(5),
                fn () => $this->repo->getBackupStats()
            );
    }

    public function getVersioningSummary()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:dashboard:versioning_summary',
                now()->addMinutes(5),
                fn () => $this->repo->getVersioningSummary()
            );
    }

    public function getActivityLogs()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:dashboard:activity_logs',
                now()->addMinutes(3),
                fn () => $this->repo->getActivityLogs()
            );
    }

    /* ================= Lists ================= */

    public function getAll()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:employees:all',
                now()->addMinutes(10),
                fn () => $this->repo->getAll()
            );
    }

    public function getAllComplaints()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:complaints:all',
                now()->addMinutes(5),
                fn () => $this->repo->getAllComplaints()
            );
    }

    public function getAllDepartments()
    {
        return Cache::tags(['admin'])
            ->rememberForever(
                'admin:departments:all',
                fn () => $this->repo->getAllDepartments()
            );
    }

    /* ================= Statistics ================= */

    public function complaintsByStatus()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:stats:complaints_by_status',
                now()->addMinutes(10),
                fn () => $this->repo->complaintsByStatus()
            );
    }

    public function complaintsByDepartment()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:stats:complaints_by_department',
                now()->addMinutes(10),
                fn () => $this->repo->complaintsByDepartment()
            );
    }

    public function employeePerformance()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:stats:employee_performance',
                now()->addMinutes(10),
                fn () => $this->repo->employeePerformance()
            );
    }

    public function topLocations()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:stats:top_locations',
                now()->addMinutes(15),
                fn () => $this->repo->topLocations()
            );
    }

    public function mostActiveEmployees()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:stats:most_active_employees',
                now()->addMinutes(15),
                fn () => $this->repo->mostActiveEmployees()
            );
    }

    public function generalStats()
    {
        return Cache::tags(['admin'])
            ->remember(
                'admin:stats:general',
                now()->addMinutes(5),
                fn () => $this->repo->generalStats()
            );
    }

    /////////////////////////write functions(invalidate)

    public function create(array $data)
    {
        $employee = Employee::create([
            'user_id' => $data['user_id'],
            'department_id' => $data['department_id'],
            'serial_number' => $data['serial_number'],
            'status' => 'active',
        ]);
        Cache::forget('admin:employees:all');
        return $employee;
    }

    public function updateEmployee(Employee $employee, array $data)
    {
        $filtered = collect($data)->only([
            'serial_number',
            'department_id'
        ])->filter()->toArray();

        if (!empty($filtered)) {
            $employee->update($filtered);
        }

        Cache::forget('admin:employees:all');
        return $employee->fresh();
    }
}

