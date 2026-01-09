<?php

namespace App\Repositories\Eloquent;

use App\Models\ActivityLog;
use App\Models\BackupLog;
use App\Models\Citizen;
use App\Models\Complaint;
use App\Models\ComplaintVersion;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Repositories\Interfaces\CitizenRepositoryInterface;
use App\Repositories\Interfaces\ActivityLogRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class EmployeeRepository extends BaseRepository implements EmployeeRepositoryInterface {

    public function __construct(Employee $employee)
    {
        parent::__construct($employee);
    }

    public function create(array $data)
    {
        return Employee::create([
            'user_id' => $data['user_id'],
            'department_id' => $data['department_id'],
            'serial_number' => $data['serial_number'],
            'status' => 'active',
        ]);
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

        return $employee->fresh();
    }

    //////////////////////////////admin

    public function getAll()
    {
        return Employee::with([
            'user',
            'department'
        ])->get();
    }

    public function getAllComplaints()
    {
        return Complaint::with([
            'files',
            'notes.employee.user',
            'citizen',
            'department'
        ])->get();
    }

    public function getAllDepartments()
    {
        return Department::select('id', 'name', 'government_entity_id')
            ->with(['governments:id,name']) // علاقة الحكومة مع تحديد الحقول المطلوبة فقط
            ->get();
    }



    //Statistics and Reports
    //1- Monitor system performance
    public function getVersioningSummary()
    {
        $data = ComplaintVersion::latest()
            ->paginate(20, [
                'id',
                'complaint_id',
                'action',
                'field_name',
                'old_value',
                'new_value',
                'created_by',
                'created_at'
            ]);

        return [
            'summary' => [
                'total_records' => ComplaintVersion::count(),
                'last_update' => ComplaintVersion::latest()->value('created_at'),
            ],
            'data' => $data
        ];
    }

    /**  Backup Report */
    public function getBackupLogs()
    {
        $data = BackupLog::latest()
            ->paginate(20, [
                'file_path',
                'disk',
                'success',
                'message',
                'created_at'
            ]);

        return [
            'summary' => [
                'total_backups' => BackupLog::count(),
                'successful_backups' => BackupLog::where('success', true)->count(),
                'failed_backups' => BackupLog::where('success', false)->count(),
                'last_successful_backup' => BackupLog::where('success', true)->latest()->value('created_at'),
            ],
            'data' => $data
        ];
    }

    /* User Activity Logs */
    public function getActivityLogs()
    {
        $logs = Activity::with(['causer:id,f_name,email'])
            ->latest()
            ->paginate(20, [
                'id',
                'description',
                'subject_type',
                'causer_id',
                'properties',
                'created_at'
            ]);

        return [
            'summary' => [
                'total_logs' => Activity::count(),
                'active_users' => Activity::distinct('causer_id')->count('causer_id'),
                'last_log' => Activity::latest()->value('created_at'),
            ],
            'data' => $logs,
        ];
    }

    /** Stats only */
    public function getBackupStats()
    {
        return [
            'total_backups' => BackupLog::count(),
            'successful_backups' => BackupLog::where('success', true)->count(),
            'failed_backups' => BackupLog::where('success', false)->count(),
            'last_successful_backup' => BackupLog::where('success', true)->latest()->value('created_at'),
        ];
    }


    ////Exports system performance reports
    public function getBackupLogsForExport()
    {
        return BackupLog::latest()
            ->get([
                'file_path',
                'disk',
                'success',
                'message',
                'created_at'
            ])
            ->map(function($log) {
                return [
                    'file_path' => $log->file_path,
                    'disk' => $log->disk,
                    'success' => $log->success ? 'success' : 'fail',
                    'message' => $log->message ?? '-',
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });
    }

    public function getActivityLogsForExport()
    {
        return ActivityLog::with([
            'causer' => function (MorphTo $morph) {
                $morph->morphWith([
                    User::class => [],
                    Employee::class => ['user'],
                ]);
            }
        ])
            ->latest()
            ->get()
            ->map(function ($log) {

                $causerName = 'unknown';

                if ($log->causer instanceof User) {
                    // Citizen
                    $causerName = $log->causer->f_name . ' ' . $log->causer->l_name;
                } elseif ($log->causer instanceof Employee && $log->causer->user) {
                    // Employee
                    $causerName = $log->causer->user->f_name . ' ' . $log->causer->user->l_name;
                }

                return [
                    'number'        => $log->id,
                    'description'   => $log->description,
                    'subject_type'  => $log->subject_type
                        ? class_basename($log->subject_type)
                        : null,
                    'causer name'   => $causerName,
                    'date'          => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

    }

    public function getVersioningSummaryForExport()
    {
        return ComplaintVersion::with(['complaint:id,reference_number', 'user:id,f_name,l_name'])
            ->latest()
            ->get()
            ->map(function($version) {
                return [
                    'complaint number ' => $version->complaint->reference_number ?? '-',
                    'user' => ($version->user)
                        ? $version->user->f_name . ' ' . $version->user->l_name
                        : '-',
                    'action' =>$version->action,
                    'date' => $version->created_at->format('Y-m-d H:i:s'),
                ];
            });
    }

    //2-System statistics
    /** عدد الشكاوى حسب الحالة */
    public function complaintsByStatus()
    {
        return Complaint::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get();
    }

    /** نسب وعدد الشكاوى لكل قسم */
    public function complaintsByDepartment()
    {
        return Complaint::select('department_id', DB::raw('COUNT(*) as total'))
            ->with('department:id,name')
            ->groupBy('department_id')
            ->get();
    }

    /** عدد الشكاوى التي عالجها كل موظف مع متوسط زمن المعالجة */
    public function employeePerformance()
    {
        return Employee::select(
            'employees.id',
            'employees.user_id',
            DB::raw('COUNT(complaints.id) as total_handled'),
            DB::raw('AVG(complaints.processing_time_minutes) as avg_processing_time')
        )
            ->leftJoin('complaints', 'complaints.processed_by', '=', 'employees.user_id')
            ->whereNotNull('complaints.processing_time_minutes')
            ->groupBy('employees.id', 'employees.user_id')
            ->with('user:id,f_name,l_name')
            ->get()
            ->map(function ($item) {
                $item->avg_processing_time = $item->avg_processing_time
                    ? round($item->avg_processing_time, 2) . ' minutes'
                    : 'No data';

                return $item;
            });
    }


    /** أشهر المواقع ظهوراً في الشكاوى */
    public function topLocations()
    {
        return Complaint::select('location', DB::raw('COUNT(*) as total'))
            ->groupBy('location')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }

    /** أكثر الموظفين نشاطًا */
    public function mostActiveEmployees()
    {
        return Employee::select(
            'employees.id',
            'employees.user_id',
            DB::raw('COUNT(complaints.id) as handled')
        )
            ->leftJoin('complaints', 'complaints.processed_by', '=', 'employees.user_id')
            ->whereNotNull('complaints.processing_finished_at') // فقط الشكاوى التي انتهت فعلاً
            ->groupBy('employees.id', 'employees.user_id')
            ->orderByDesc('handled')
            ->with('user:id,f_name,l_name')
            ->limit(10)
            ->get();
    }

    /** إحصائيات عامة */
    public function generalStats()
    {
        $totalComplaints = Complaint::count();
        $resolvedCount = Complaint::where('status', 'resolved')->count() / max(Complaint::count(), 1) * 100; // حسب طلبك سابقًا
        $avgProcessingTime = Complaint::whereNotNull('processing_time_minutes')->avg('processing_time_minutes');

        return [
            'total_complaints' => $totalComplaints,
            'resolved_percentage' => $resolvedCount,
            'avg_processing_time_minutes' => round($avgProcessingTime ?? 0, 2),
        ];
    }


}

