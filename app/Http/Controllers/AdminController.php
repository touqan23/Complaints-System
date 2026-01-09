<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRegisterRequest;
use App\Http\Requests\EmployeeUpdateRequest;
use App\Models\Employee;
use App\Repositories\Eloquent\CitizenRepository;
use App\Services\AdminService;
use App\Services\AuthService;
use App\Services\EmployeeService;
use App\Support\ServiceExecutor;
use Illuminate\Http\Request;

class AdminController extends BaseController
{
    protected $employeeService, $authService, $adminService;

    public function __construct(
        EmployeeService $employeeService,
        AuthService $authService ,
        AdminService $adminService,
        ServiceExecutor $executor)
    {
        parent::__construct($executor);
        $this->employeeService = $employeeService;
        $this->authService = $authService;
        $this->adminService = $adminService;

    }
    public function addEmployee(EmployeeRegisterRequest $request)
    {
        $validated = $request->validated();
        return response()->json([
            'status'=>'success',
            'result' => $this->exec(
                fn () => $this->employeeService->registerEmployee($validated),
                channel: 'admin',
                action: 'Add employee',
                context: $validated,
            ),
            ]);
    }

    public function update(EmployeeUpdateRequest $request)
    {
        $validated = $request->validated();
        $employee = $this->employeeService->findEmployee($validated['employee_id']);
        if($employee) {
            //$updated = $this->employeeService->update($employee, $request->validated());
             $updated = $this->exec(
                fn () => $this->employeeService->update($employee, $request->validated()),
                channel: 'admin',
                action: 'Update employee',
                context: ['employee_id' => $validated['employee_id']]);

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
            //$this->employeeService->delete($employee);
            $this->exec(
                fn () => $this->employeeService->delete($employee),
                channel: 'admin',
                action: 'Delete employee',
                context: ['employee_id' => $id]
            );
            return response()->json(['status' => 'success', 'message' => 'Employee deleted successfully',]);
        }
        return response()->json(['message' => 'Employee not found'], 404);
    }


    //Monitor system performance
    public function dashboard()
    {
        return response()->json([
            "status" => "success",
            "data" => $this->exec(
                fn () => $this->adminService->getDashboardData(),
                channel: 'admin',
                action: 'View admin dashboard'
            )
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

        /*return $format === 'csv'
            ? $this->adminService->exportCSV($type)
            : $this->adminService->exportPDF($type);*/
        return $this->exec(
            fn () => $format === 'csv'
                ? $this->adminService->exportCSV($type)
                : $this->adminService->exportPDF($type),
            channel: 'admin',
            action: 'Export admin report',
            context: [
                'type'   => $type,
                'format' => $format
            ]
        );
    }

    //Statistics And Reports
    public function statisticsDashboard()
    {
        //return response()->json($this->adminService->getDashboardReport());
        return response()->json(
            $this->exec(
                fn () => $this->adminService->getDashboardReport(),
                channel: 'admin',
                action: 'View statistics dashboard'
            )
        );
    }

    public function statisticsExport($format)
    {
        if ($format === 'pdf') return $this->adminService->statisticsExportPDF();
        if ($format === 'csv') return $this->adminService->statisticsExportCSV();

        return response()->json(['error' => 'Invalid export format'], 400);
    }

    ///////////////////////////////
    public function AllEmployee()
    {
      $employees = $this->adminService->getAllEmployees();

            return response()->json([
                'status' => 'success',
                'data' => $employees
            ]);
    }

    public function AllComplaints()
    {
        $complaints = $this->adminService->getAllComplaints();

        return response()->json([
            'status' => 'success',
            'data' => $complaints
        ]);
    }

    public function AllDepartments()
    {
        $department = $this->adminService->getAllDepartments();

        return response()->json([
            'status' => 'success',
            'data' => $department
        ]);
    }

    public function AllCitizens()
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->adminService->getAllCitizens()
        ]);
    }

    public function showCitizen($id)
    {
        $citizen = $this->adminService->getCitizen($id);

        if (!$citizen) {
            return response()->json(['message' => 'Citizen not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $citizen
        ]);
    }

    public function updateCitizen(Request $request)
    {
        $citizen = $this->adminService->getCitizen($request->citizen_id);

        if (!$citizen) {
            return response()->json(['message' => 'Citizen not found'], 404);
        }

        //$updated = $this->adminService->updateCitizen($citizen, $request->all());
        $updated = $this->exec(
            fn () => $this->adminService->updateCitizen($citizen, $request->all()),
            channel: 'admin',
            action: 'Update citizen',
            context: ['citizen_id' => $request->citizen_id]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Citizen updated successfully',
            'data' => $updated
        ]);
    }

    public function deleteCitizen($id)
    {
        $citizen = $this->adminService->getCitizen($id);

        if (!$citizen) {
            return response()->json(['message' => 'Citizen not found'], 404);
        }

        //$this->adminService->deleteCitizen($citizen);
        $this->exec(
            fn () => $this->adminService->deleteCitizen($citizen),
            channel: 'admin',
            action: 'Delete citizen',
            context: ['citizen_id' => $id]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Citizen deleted successfully'
        ]);
    }

    /////////////////////////////////error log api

    public function errorLogs(Request $request)
    {
        //$logs = $this->adminService->getErrorLogs($request->only([
        //    'channel', 'user_id', 'from', 'to', 'per_page']));
        $logs = $this->exec(
            fn () => $this->adminService->getErrorLogs(
                $request->only(['channel', 'user_id', 'from', 'to', 'per_page'])
            ),
            channel: 'admin',
            action: 'View error logs',
            context: $request->only(['channel', 'user_id'])
        );

        return response()->json([
            'status' => 'success',
            'data'   => $logs
        ]);
    }
}
