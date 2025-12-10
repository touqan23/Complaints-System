<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRegisterRequest;
use App\Http\Requests\EmployeeUpdateRequest;
use App\Models\Employee;
use App\Services\AdminService;
use App\Services\AuthService;
use App\Services\EmployeeService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected $employeeService, $authService , $adminService;

    public function __construct(EmployeeService $employeeService, AuthService $authService , AdminService $adminService) {
        $this->employeeService = $employeeService;
        $this->authService = $authService;
        $this->adminService = $adminService;
    }
    public function addEmployee(EmployeeRegisterRequest $request)
    {
        $validated = $request->validated();
        $result = $this->employeeService->registerEmployee($validated);
        return response()->json([
            'status'=>'success',
            'result' => $result,
            ]);
    }

    public function update(EmployeeUpdateRequest $request)
    {
        $validated = $request->validated();
        $employee = $this->employeeService->findEmployee($validated['employee_id']);
        if($employee) {
            $updated = $this->employeeService->update($employee, $request->validated());
            return response()->json([
                'status' => 'success',
                'message' => 'Employee updated successfully',
                'data' => $updated,
            ]);
        }
        return response()->json(['message' => 'Employee not found'], 404);
    }

    public function delete($id)
    {
        $employee = $this->employeeService->findEmployee($id);
        if($employee) {
            $this->employeeService->delete($employee);
            return response()->json(['status' => 'success', 'message' => 'Employee deleted successfully',]);
        }
        return response()->json(['message' => 'Employee not found'], 404);
    }


    //Monitor system performance
    public function dashboard()
    {
        return response()->json([
            "status" => "success",
            "data" => $this->adminService->getDashboardData()
        ]);
    }

    public function export($type, $format)
    {
        // Validate type and format
        if (!in_array($type, ['versions', 'backups', 'activity'])) {
            abort(404, 'Invalid report type');
        }

        if (!in_array($format, ['csv', 'pdf'])) {
            abort(404, 'Invalid format');
        }

        return $format === 'csv'
            ? $this->adminService->exportCSV($type)
            : $this->adminService->exportPDF($type);
    }

    //Statistics And Reports
    public function statisticsDashboard()
    {
        return response()->json($this->adminService->getDashboardReport());
    }

    public function statisticsExport($format)
    {
        if ($format === 'pdf') return $this->adminService->statisticsExportPDF();
        if ($format === 'csv') return $this->adminService->statisticsExportCSV();

        return response()->json(['error' => 'Invalid export format'], 400);
    }
}
