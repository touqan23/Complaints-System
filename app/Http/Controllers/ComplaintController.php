<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Services\ComplaintService;
use App\Support\ServiceExecutor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends BaseController
{
    protected $service;

    public function __construct(ComplaintService $service, ServiceExecutor $executor)
    {
        parent::__construct($executor);
        $this->service = $service;
    }

    /**
     * Citizen creates a new complaint
     */
    public function store(Request $request)
    {
        $citizen = Auth::user()->citizen;
        $validated = $request->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'type' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'files.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,gif,xlsx,txt',
        ]);
        $validated['citizen_id'] = $citizen->id;


        $files = $request->file('files') ? array_filter($request->file('files')) : [];
        $hasFiles = !empty($files);

        //$complaint = $this->service->createComplaint($validated, $files);
        $complaint = $this->exec(
            fn () => $this->service->createComplaint($validated, $files),
            channel: 'complaint',
            action: 'Complaint creation',
            context: [
                'citizen_id' => $validated['citizen_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
            ],
            transactional: true
        );

        // Load files with their status
        $complaint->load('files');

        $message = 'Complaint created successfully';
        if ($hasFiles) {
            $message .= '. Files are being uploaded in the background.';
        }

        return response()->json([
            "status" => "success",
            'message' => $message,
            'complaint' => $complaint,
            'files_count' => $hasFiles ? count($files) : 0,
        ], 201);
    }


    public function startProcess (Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string|max:255',
            ]);

        $user = Auth::user();
        //$complaint = $this->service->startProcess($request->reference_number, $user);
        $complaint = $this->exec(
            fn () => $this->service->startProcess($request->reference_number, $user),
            channel: 'complaint',
            action: 'Start complaint process',
            context: [
                'reference_number' => $request->reference_number,
                'user_id' => $user->id,
            ],
            transactional: true
        );

        if (isset($complaint['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => $complaint['error']
            ], 403);
        }
        return response()->json(['status' => 'success', 'complaint' => $complaint]);
    }

    public function finishProcess(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string|max:255',
        ]);
        $user = auth()->user();

        //$response = $this->service->finishProcess($request->reference_number, $user);
        $response = $this->exec(
            fn () => $this->service->finishProcess($request->reference_number, $user),
            channel: 'complaint',
            action: 'Finish complaint process',
            context: [
                'reference_number' => $request->reference_number,
                'user_id' => $user->id,
            ],
            transactional: true
        );

        if (isset($response['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => $response['error']
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Complaint unlocked',
            'complaint' => $response
        ], 200);
    }


    /**
     * Get details by reference number
     */
    public function showByReference(Request $request, $referenceNumber)
    {
        $user = auth()->user();
         //return $user;

        //$complaint = $this->service->findByReference($referenceNumber, $user);
        $complaint = $this->exec(
            fn () => $this->service->findByReference($referenceNumber, $user),
            channel: 'complaint',
            action: 'Show complaint by reference',
            context: [
                'reference_number' => $referenceNumber,
                'user_id' => $user->id
            ]
        );

        if (!$complaint) {
            return response()->json([
                'message' => 'Complaint not found'], 404);
        }

        // policy authorization
        //$this->authorize('hasLock', $complaint);
        $result = Gate::inspect('hasLock', $complaint);

        if (! $result->allowed()) {
            return response()->json([
                'message' => $result->message() ?? 'this complaint is being handled by another employee'
            ], 403);
        }

        return response()->json([
            "status" => "success",
            'complaint' => $complaint,
            ]);
    }

    /**
     * Complaints by citizen
     */
    public function citizenComplaints()
    {
        $user = auth()->user();

        return response()->json([
            "status" => "success",
            "complaints" => $this->exec(
                fn () => $this->service->getByCitizen($user->citizen->id, $user),
                channel: 'complaint',
                action: 'Get citizen complaints',
                context: [
                    'citizen_id' => $user->citizen->id,
                    'user_id' => $user->id
                ]
            )
        ]);
    }


    /**
     * Complaints by status
     */
    public function complaintsByStatus($status)
    {
        $user = auth()->user();

        return response()->json([
            "status" => "success",
            "complaints" =>$this->exec(
                fn () => $this->service->getByStatus($status, $user),
                channel: 'complaint',
                action: 'Get complaints by status',
                context: [
                    'status' => $status,
                    'user_id' => $user->id
                ]
            )
       ] );
    }

    /**
     * Complaints assigned to a government entity
     */
    public function entityComplaints($entityId)
    {
        $user = auth()->user();

        return response()->json([
            "status" => "success",
            "complaints" =>$this->exec(
                fn () => $this->service->getByEntity($entityId, $user),
                channel: 'complaint',
                action: 'Get entity complaints',
                context: [
                    'entity_id' => $entityId,
                    'user_id' => $user->id
                ]
            )
        ]);
    }

    /**
     * Complaints assigned to a department in government entity
     */
    public function departmentComplaints($departmentId)
    {
        $user = auth()->user();

        return response()->json([
            "status" => "success",
            "complaints" =>$this->exec(
                fn () => $this->service->getByDepartment($departmentId, $user),
                channel: 'complaint',
                action: 'Get department complaints',
                context: [
                    'department_id' => $departmentId,
                    'user_id' => $user->id
                ])
        ]);
    }


    /**
     * Update status (employee)
     */
    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'complaint_id' => 'required|integer|exists:complaints,id',
            'status' => 'required|in:new,processing,need_more_info,resubmitted,resolved,rejected'
        ]);

        $complaint = Complaint::find($validated['complaint_id']);

        if (!$complaint) {
            return response()->json(['message' => 'Complaint not found'], 404);
        }

        // الموظف فقط يقدر يعدل الحالة
        if (!($request->user()->hasRole('employee') || $request->user()->hasRole('admin'))) {
            return response()->json(['message' => 'You are not authorized'], 403);
        }

        /*$updated = $this->service->updateStatus(
            $complaint,
            $validated['status'],
            $request->user()
        );*/
        $updated = $this->exec(
            fn () => $this->service->updateStatus(
                $complaint,
                $validated['status'],
                $request->user()
            ),
            channel: 'complaint',
            action: 'Update complaint status',
            context: [
                'complaint_id' => $complaint->id,
                'status' => $validated['status'],
                'user_id' => $request->user()->id
            ]
        );

        return response()->json([
            "status" => "success",
            'message' => 'Status updated successfully',
            'complaint' => $updated
        ]);
    }

    public function getcitizenComplaintsbynationalnumber($nationalNumber)
    {
        $user = auth()->user();

        return response()->json([
            "status" => "success",
            "complaints" =>$this->service->getByCitizenNationalNumber($nationalNumber, $user)
        ]);
    }


    /**
     * Citizen updating complaint after request for more information
     */
    public function updateByCitizen(Request $request)
    {
        $complaint = Complaint::findOrFail($request->complaint_Id);

        // Policy Check
        $this->authorize('update', $complaint);


        $validated = $request->validate([
            'type' => 'sometimes|string',
            'location' => 'sometimes|string',
            'description' => 'nullable|string',
            'files.*' => 'file|max:10240',
        ]);

        $files = $request->file('files') ?? [];

        /*$updatedComplaint = $this->service
            ->updateComplaintByCitizen(
                $complaint,
                $validated,
                $files,
                $request->user()
            );*/
        $updatedComplaint = $this->exec(
            fn () => $this->service->updateComplaintByCitizen(
                $complaint,
                $validated,
                $files,
                $request->user()
            ),
            channel: 'complaint',
            action: 'Citizen update complaint',
            context: [
                'complaint_id' => $complaint->id,
                'user_id' => $request->user()->id
            ],
            transactional: true
        );



        return response()->json([
            "status" => "success",
            'message' => 'Complaint updated and resubmitted successfully.',
            'data' => $updatedComplaint
        ], 200);
    }



    ////////////////////////notes
    public function addNote(Request $request)
    {
        try {
            $validated = $request->validate([
                'complaintId'=>'required|integer|exists:complaints,id',
                'note' => 'required|string',
                'requested_to_citizen' => 'boolean',
            ]);

            $employeeId = Auth::user()->employee->id;

            $requestedToCitizen = $validated['requested_to_citizen'] ?? false;

            /*$result = $this->service->addEmployeeNote(
                $request->complaintId,
                $employeeId,
                $validated['note'],
                $requestedToCitizen
            );*/
            $result = $this->exec(
                fn () => $this->service->addEmployeeNote(
                    $validated['complaintId'],
                    $employeeId,
                    $validated['note'],
                    $validated['requested_to_citizen'] ?? false
                ),
                channel: 'complaint',
                action: 'Add employee note',
                context: [
                    'complaint_id' => $validated['complaintId'],
                    'employee_id' => $employeeId
                ],
                transactional: true
            );

            return response()->json([
                "status" => "success",
                'message' => 'Note added successfully.',
                'complaint' => $result['complaint'],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getNotes($complaintId)
    {
        return response()->json([
            "status" => "success",
            "notes" =>$this->service->getNotesForComplaint($complaintId)
        ]);
    }

    public function getCitizenNotes($complaintId)
    {
        return response()->json([
            "status" => "success",
            "notes"=>$this->service->getCitizenNotes($complaintId)

        ]);
    }

    public function deleteNote($noteId)
    {
        $this->service->deleteNote($noteId);

        return response()->json([
            "status" => "success",
            'message' => 'Note deleted successfully'
        ]);
    }

    public function updateNote(Request $request)
    {
        $request->validate([
            'note' => 'required|string',
            'note_id'=>'required|string|exists:notes,id',
        ]);

        /*$note = $this->service->updateNote($request->note_id, [
            'note' => $request->note
        ]);*/
        $note = $this->exec(
            fn () => $this->service->updateNote($request->note_id, [
                'note' => $request->note
            ]),
            channel: 'complaint',
            action: 'Update employee note',
            context: [
                'note_id' => $request->note_id]
        );

        return response()->json([
            "status" => "success",
            'message' => 'Note updated successfully',
            'data' => $note
        ]);
    }

    public function history($referenceNumber)
    {
        $complaint = Complaint::where('reference_number', $referenceNumber)
            ->with(['versions.user'])
            ->firstOrFail();
        return response()->json([
            "status" => "success",
            'history' => $this->exec(
                fn () => [
                    //'history' => $this->service->getHistory($complaint),
                    'versions' => $complaint->versions,

                ],
                channel: 'complaint',
                action: 'Load complaint history data',
                context: [
                    'complaint_id' => $complaint->id
                ]
            ),
        ]);
    }

    public function activityLog()
    {
        return response()->json([
            "status" => "success",
            "logs" => $this->exec(
                fn () => $this->service->getSystemActivity(),
                channel: 'system',
                action: 'Get system activity logs'
            )
        ]);
    }
}
