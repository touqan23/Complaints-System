<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Services\ComplaintService;
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
            'government_entity_id' => 'required|integer|exists:government_entities,id',
            'type' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'files.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,gif,xlsx,txt',
        ]);

        $files = $request->file('files') ? array_filter($request->file('files')) : [];

        $complaint = $this->service->createComplaint($validated, $files)->load('files');

        return response()->json([
            'message' => 'تم تقديم الشكوى بنجاح',
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
            return response()->json(['message' => 'Complaint not found'], 404);
        }

        return response()->json($complaint);
    }

    /**
     * Complaints by citizen
     */
    public function citizenComplaints(Request $request, $citizenId)
    {
        $user = auth()->user();

        return response()->json(
            $this->service->getByCitizen($citizenId, $user)
        );
    }

    /**
     * Complaints by status
     */
    public function complaintsByStatus(Request $request, $status)
    {
        $user = auth()->user();

        return response()->json(
            $this->service->getByStatus($status, $user)
        );
    }

    /**
     * Complaints assigned to a government entity
     */
    public function entityComplaints(Request $request, $entityId)
    {
        $user = auth()->user();

        return response()->json(
            $this->service->getByEntity($entityId, $user)
        );
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
        if (!$request->user()->is_employee) {
            return response()->json(['message' => 'You are not authorized'], 403);
        }

        $updated = $this->service->updateStatus(
            $complaint,
            $validated['status'],
            $request->user()
        );

        return response()->json([
            'message' => 'Status updated successfully',
            'complaint' => $updated
        ]);
    }

    /**
     * Citizen updating complaint after request for more information
     */
    public function updateByCitizen(Request $request, $complaintId)
    {
        $complaint = Complaint::findOrFail($complaintId);

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

            $result = $this->service->addEmployeeNote(
                $request->complaintId,
                $employeeId,
                $validated['note'],
                $requestedToCitizen
            );

            return response()->json([
                'message' => 'Note added successfully.',
                'complaint' => $result['complaint'],
               // 'note' => $result['note']
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getNotes($complaintId)
    {
        return response()->json(
            $this->service->getNotesForComplaint($complaintId)
        );
    }

    public function getCitizenNotes($complaintId)
    {
        return response()->json(
            $this->service->getCitizenNotes($complaintId)
        );
    }

    public function deleteNote($noteId)
    {
        $this->service->deleteNote($noteId);

        return response()->json([
            'message' => 'Note deleted successfully'
        ]);
    }

    public function updateNote(Request $request)
    {
        $request->validate([
            'note' => 'required|string',
            'noteid'=>'required|integer|exists:notes,id',
        ]);

        $note = $this->service->updateNote($request->noteid, [
            'note' => $request->note
        ]);

        return response()->json([
            'message' => 'Note updated successfully',
            'data' => $note
        ]);
    }





}
