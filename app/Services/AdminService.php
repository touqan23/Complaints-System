<?php

namespace App\Services;

use App\Repositories\Eloquent\EmployeeRepository;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;


class AdminService
{

    protected $repo;

    public function __construct(EmployeeRepository $repo)
    {
        $this->repo = $repo;
    }


    // Monitor system performance
    public function getDashboardData()
    {
        return [
            'BackupStats' => $this->repo->getBackupStats(),
            'versions' => $this->repo->getVersioningSummary(),
            'activity' => $this->repo->getActivityLogs(),
        ];
    }

    public function getDataForExport($type)
    {
        return match ($type) {
            'versions' => $this->repo->getVersioningSummaryForExport(),
            'backups' => $this->repo->getBackupLogsForExport(),
            'activity' => $this->repo->getActivityLogsForExport(),
        };
    }

    public function exportCSV($type)
    {
        $data = collect($this->getDataForExport($type));

        if ($data->isEmpty()) {
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

        return response()->download($fileName)->deleteFileAfterSend(true);
    }

    public function exportPDF($type)
    {
        $data = $this->getDataForExport($type);

        if ($data->isEmpty()) {
            return response()->json(['message' => 'No data available'], 404);
        }

        $fileName = $type . "_report_" . now()->format('Y-m-d') . ".pdf";

        $pdf = Pdf::loadView('reports.export', [
            'data' => $data,
            'title' => strtoupper($type) . " REPORT",
            'date' => now()->format('Y-m-d H:i'),
        ]);

        return $pdf->download($fileName);
    }


    //Statistics And reports
    public function getDashboardReport()
    {
        return [
            'status_summary' => $this->repo->complaintsByStatus(),
            'department_distribution' => $this->repo->complaintsByDepartment(),
            'employee_performance' => $this->repo->employeePerformance(),
            'top_locations' => $this->repo->topLocations(),
            'top_employees' => $this->repo->mostActiveEmployees(),
            'general_stats' => $this->repo->generalStats(),
        ];
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

            // Print Section Title
            fputcsv($file, [strtoupper(str_replace('_', ' ', $section))]);

            // Normalize rows into consistent array format
            if (is_null($rows) || $rows === [] || collect($rows)->isEmpty()) {
                fputcsv($file, ['No data available.']);
                fputcsv($file, []);
                continue;
            }

            // If rows is a single value (not array or collection) → wrap it as list
            if (!is_array($rows) && !($rows instanceof \Illuminate\Support\Collection)) {
                $rows = [$rows];
            }

            // Convert collections to array
            $rows = collect($rows)->toArray();

            // If data is associative array (key => value), convert to row format for clarity
            if (array_keys($rows) !== range(0, count($rows) - 1)) {
                $rows = [$rows];
            }

            // print header based on first row keys
            fputcsv($file, array_keys((array) $rows[0]));

            // print rows
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


            // empty line
            fputcsv($file, []);
        }

        fclose($file);

        return response()->download(storage_path("app/$filename"));
    }

    /** Export as PDF */
    public function statisticsExportPDF()
    {
        $data = $this->getDashboardReport();
        $pdf = Pdf::loadView('reports.statistics', compact('data'));

        return $pdf->download("system_report_" . now()->format('Y-m-d') . ".pdf");
    }



}
