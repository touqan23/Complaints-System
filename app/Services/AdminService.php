<?php

namespace App\Services;

use App\Repositories\Eloquent\ActivityLogRepository;
use App\Repositories\Eloquent\CitizenRepository;
use App\Repositories\Eloquent\EmployeeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Response;
use Barryvdh\Snappy\Facades\SnappyPdf as Pdf;


class AdminService
{

    protected $repo, $citizenRepository, $logs, $activity;

    public function __construct(
        EmployeeRepository $repo ,
        CitizenRepository $citizenRepository,
        LoggingService $logs,
        ActivityLogRepository $activity)
    {
        $this->repo = $repo;
        $this->citizenRepository = $citizenRepository;
        $this->logs = $logs;
        $this->activity = $activity;
    }


    // Monitor system performance
    public function getDashboardData()
    {
        $data = [
            'BackupStats' => $this->repo->getBackupStats(),
            'versions' => $this->repo->getVersioningSummary(),
            'activity' => $this->repo->getActivityLogs(),
        ];

        $this->logs->admin(null, "Dashboard data retrieved SUCCESS", [
            "backup_count" => count($data['BackupStats'] ?? []),
            "version_count" => count($data['versions'] ?? []),
            "activity_count" => count($data['activity'] ?? [])
        ]);

        return $data;
    }

    public function getDataForExport($type)
    {
        $data = match ($type) {
            'versions' => $this->repo->getVersioningSummaryForExport(),
            'backups' => $this->repo->getBackupLogsForExport(),
            'activity' => $this->repo->getActivityLogsForExport(),
            default => throw new \InvalidArgumentException("Invalid export type: {$type}")
        };

        $this->logs->admin(null, "Export data retrieved SUCCESS", [
            "type" => $type,
            "record_count" => is_countable($data) ? count($data) : 0
        ]);

        return $data;
    }

    public function exportCSV($type)
    {
        $data = collect($this->getDataForExport($type));

        if ($data->isEmpty()) {
            $this->logs->adminWarning(null, "CSV export FAILED — no data", [
                "type" => $type
            ]);
            return response()->json(['message' => 'No data found to export'], 404);
        }

        $fileName = $type . "_report_" . now()->format('Y-m-d') . ".csv";
        $file = fopen($fileName, 'w');

        // Headers
        fputcsv($file, array_keys($data->first()));

        // Rows
        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);
        $this->logs->admin(null, "CSV export SUCCESS", [
            "type" => $type,
            "filename" => $fileName,
            "record_count" => $data->count()
        ]);
        return response()->download($fileName)->deleteFileAfterSend(true);
    }

    public function exportPDF($type)
    {
        $data = $this->getDataForExport($type);

        if ($data->isEmpty()) {
            $this->logs->adminWarning(null, "PDF export FAILED — no data", [
                "type" => $type
            ]);
            return response()->json(['message' => 'No data available'], 404);
        }

        $fileName = $type . "_report_" . now()->format('Y-m-d') . ".pdf";

        $pdf = Pdf::loadView('reports.export', [
            'data' => $data,
            'title' => strtoupper($type) . " REPORT",
            'date' => now()->format('Y-m-d H:i'),
        ]);

        $this->logs->admin(null, "PDF export SUCCESS", [
            "type" => $type,
            "filename" => $fileName,
        ]);

        return $pdf->download($fileName);
    }


    //Statistics And reports
    public function getDashboardReport()
    {
        $report = [
            'status_summary' => $this->repo->complaintsByStatus(),
            'department_distribution' => $this->repo->complaintsByDepartment(),
            'employee_performance' => $this->repo->employeePerformance(),
            'top_locations' => $this->repo->topLocations(),
            'top_employees' => $this->repo->mostActiveEmployees(),
            'general_stats' => $this->repo->generalStats(),
        ];

        $this->logs->admin(null, "Dashboard report generated SUCCESS", [
            "sections_count" => count($report)
        ]);

        return $report;
    }

    /** Export as CSV */
    public function statisticsExportCSV()
    {
        $data = $this->getDashboardReport();
        $filename = 'system_report_' . now()->format('Y-m-d') . '.csv';

        $file = fopen(storage_path("app/$filename"), 'w');
        // Header
        fputcsv($file, ['Complaints System - Full Report']);
        fputcsv($file, ['Generated at:', now()->format('Y-m-d H:i:s')]);
        fputcsv($file, []); // empty line

        foreach ($data as $section => $rows) {
            fputcsv($file, [strtoupper(str_replace('_', ' ', $section))]);
            if (is_null($rows) || $rows === [] || collect($rows)->isEmpty()) {
                fputcsv($file, ['No data available.']);
                fputcsv($file, []);
                continue;
            }
            if (!is_array($rows) && !($rows instanceof \Illuminate\Support\Collection)) {
                $rows = [$rows];
            }
            $rows = collect($rows)->toArray();
            if (array_keys($rows) !== range(0, count($rows) - 1)) {
                $rows = [$rows];
            }
            fputcsv($file, array_keys((array) $rows[0]));
            foreach ($rows as $row) {

                $normalizedRow = collect((array)$row)->map(function($value) {

                    // إذا القيمة مصفوفة أو object نحولها JSON
                    if (is_array($value) || is_object($value)) {
                        return json_encode($value, JSON_UNESCAPED_UNICODE);
                    }

                    return $value;
                })->toArray();

                fputcsv($file, array_values($normalizedRow));
            }
            fputcsv($file, []);
        }

        fclose($file);

        $this->logs->admin(null, "Statistics CSV export SUCCESS", [
            "filename" => $filename,
            "sections_count" => count($data)
        ]);

        return response()->download(storage_path("app/$filename"));
    }

    /** Export as PDF */
    public function statisticsExportPDF()
    {
        $data = $this->getDashboardReport();

        $pdf = Pdf::loadView('reports.statistics', compact('data'))
            ->setOption('encoding', 'UTF-8')
            ->setOption('enable-local-file-access', true);

        $this->logs->admin(null, "Statistics PDF export SUCCESS");

        return $pdf->download("system_report_" . now()->format('Y-m-d') . ".pdf");
    }



    public function getAllEmployees()
    {
        $employees = $this->repo->getAll();

        $this->logs->admin(null, "Get all employees SUCCESS", [
            "count" => $employees->count()
        ]);

        return $employees;
    }

    public function getAllComplaints()
    {
        $complaints = $this->repo->getAllComplaints();

        $this->logs->admin(null, "Get all complaints SUCCESS", [
            "count" => $complaints->count()
        ]);

        return $complaints;
    }

    public function getAllDepartments()
    {
        $departments = $this->repo->getAllDepartments();

        $this->logs->admin(null, "Get all departments SUCCESS", [
            "count" => $departments->count()
        ]);

        return $departments;
    }

    ///توابع ادارة على المواطنين
    public function getAllCitizens()
    {
        return $this->citizenRepository->AllCitizen();
    }

    public function getCitizen($id)
    {
        $citizen = $this->citizenRepository->findById($id);

        if ($citizen) {
            $this->logs->admin(null, "Get citizen SUCCESS", [
                "citizen_id" => $citizen->id
            ]);
        }

        return $citizen;
    }


    public function updateCitizen($citizen)
    {
        $citizen = $this->citizenRepository->findById($citizen->id);

        if ($citizen) {
            $this->logs->admin(null, "Get citizen SUCCESS", [
                "citizen_id" => $citizen->id
            ]);
        } else {
            $this->logs->adminWarning(null, "Get citizen FAILED — not found", [
                "citizen_id" => $citizen->id
            ]);
        }

        return $citizen;
    }

    public function deleteCitizen($citizen)
    {
        $citizenId = $citizen->id;
        $nationalNumber = $citizen->national_number;

        $result = $this->citizenRepository->deleteCitizen($citizen);

        $this->logs->admin(null, "Citizen deleted SUCCESS", [
            "citizen_id" => $citizenId,
            "national_number" => $nationalNumber
        ]);

        return $result;
    }

    /////////////////////////// logging api

    public function getErrorLogs(array $filters = []): LengthAwarePaginator
    {
        return $this->activity->getErrorLogs($filters);
    }

}
