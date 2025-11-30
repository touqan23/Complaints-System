<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    protected $service;

    public function __construct(ComplaintService $service)
    {
        $this->service = $service;
    }

    /**
     * Citizen creates a new complaint
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'citizen_id' => 'required|integer|exists:citizens,id',
            'department_id' => 'required|integer|exists:departments,id',
            'type' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'files.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,gif,xlsx,txt',
        ]);

        $files = $request->file('files') ? array_filter($request->file('files')) : [];

        $complaint = $this->service->createComplaint($validated, $files)->load('files');

        return response()->json([
            "status" => "success",
            'message' => 'complaint created successfully',
            'complaint' => $complaint,
        ], 201);
    }

    /**
     * Get details by reference number
     */
    public function showByReference(Request $request, $referenceNumber)
    {
        $user = auth()->user();
         //return $user;

        $complaint = $this->service->findByReference($referenceNumber, $user);

        if (!$complaint) {
            return response()->json([
                'message' => 'Complaint not found'], 404);
        }

        return response()->json([
            "status" => "success",
            'complaint' => $complaint,
            ]);
    }

    /**
     * Complaints by citizen
     */
    public function citizenComplaints($citizenId)
    {
        $user = auth()->user();

        return response()->json([
            "status" => "success",
            "complaints" => $this->service->getByCitizen($citizenId, $user)
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
            "complaints" =>$this->service->getByStatus($status, $user)
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
            "complaints" =>$this->service->getByEntity($entityId, $user)
       ] );
    }

    /**
     * Complaints assigned to a department in government entity
     */
    public function departmentComplaints($departmentId)
    {
        $user = auth()->user();

        return response()->json([
            "status" => "success",
            "complaints" =>$this->service->getByDepartment($departmentId, $user)
      ]  );
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
        if (!$request->user()->hasRole('employee')) {
            return response()->json(['message' => 'You are not authorized'], 403);
        }
        // policy authorization
        //$this->authorize('hasLock', $complaint);
        $result = Gate::inspect('hasLock', $complaint);

        if (! $result->allowed()) {
            return response()->json([
                'message' => $result->message() ?? 'this complaint is being handled by another employee'
            ], 403);
        }

        $updated = $this->service->updateStatus(
            $complaint,
            $validated['status'],
            $request->user()
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

        $updatedComplaint = $this->service
            ->updateComplaintByCitizen(
                $complaint,
                $validated,
                $files,
                $request->user()
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
            $complaint = $this->service->findById($validated['complaintId']);

            // policy authorization
            //$this->authorize('hasLock', $complaint);
            $result = Gate::inspect('hasLock', $complaint);
            if (! $result->allowed()) {
                return response()->json([
                    'message' => $result->message() ?? 'this complaint is being handled by another employee'
                ], 403);
            }

            $requestedToCitizen = $validated['requested_to_citizen'] ?? false;

            $result = $this->service->addEmployeeNote(
                $request->complaintId,
                $employeeId,
                $validated['note'],
                $requestedToCitizen
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
        $complaint = $this->service->getComplaintByNote($noteId);
        // policy authorization
        //$this->authorize('hasLock', $complaint);
        $result = Gate::inspect('hasLock', $complaint);

        if (! $result->allowed()) {
            return response()->json([
                'message' => $result->message() ?? 'this complaint is being handled by another employee'
            ], 403);
        }
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

        $complaint = $this->service->getComplaintByNote($request->note_id);
        // policy authorization
        //$this->authorize('hasLock', $complaint);
        $result = Gate::inspect('hasLock', $complaint);

        if (! $result->allowed()) {
            return response()->json([
                'message' => $result->message() ?? 'this complaint is being handled by another employee'
            ], 403);
        }

        $note = $this->service->updateNote($request->note_id, [
            'note' => $request->note
        ]);

        return response()->json([
            "status" => "success",
            'message' => 'Note updated successfully',
            'data' => $note
        ]);
    }

    //return all history by reference number
    public function history($referenceNumber)
    {
        $complaint = Complaint::where('reference_number', $referenceNumber)->firstOrFail();

        return response()->json([
            "status" => "success",
            'reference' => $referenceNumber,
            'history' => $this->service->getHistory($complaint)
        ]);
    }

}
