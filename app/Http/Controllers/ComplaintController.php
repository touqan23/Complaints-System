<?php

namespace App\Http\Controllers;

use App\Services\ComplaintService;
use Illuminate\Http\Request;

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
            $complaint = $this->service->createComplaint($validated, $files);

            return response()->json([
                'message' => 'تم تقديم الشكوى بنجاح',
                'complaint' => $complaint,
            ], 201);

    }

    /**
     * Get details by reference number
     */
    public function showByReference($referenceNumber)
    {
        $complaint = $this->service->findByReference($referenceNumber);

        if (!$complaint) {
            return response()->json(['message' => 'Complaint not found'], 404);
        }

        return response()->json($complaint);
    }

    /**
     * Complaints by citizen
     */
    public function citizenComplaints($citizenId)
    {
        return response()->json(
            $this->service->getByCitizen($citizenId)
        );
    }

    /**
     * Complaints by status
     */
    public function complaintsByStatus($status)
    {
        return response()->json(
            $this->service->getByStatus($status)
        );
    }

    /**
     * Complaints assigned to a government entity
     */
    public function entityComplaints($entityId)
    {
        return response()->json(
            $this->service->getByEntity($entityId)
        );
    }

    /**
     * Update status
     */
    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'complaint_id' => 'required|integer|exists:complaints,id',
            'status' => 'required|in:new,processing,resolved,rejected'
        ]);

        $complaintId = $validated['complaint_id'];
        $complaint = $this->service->complaints->find($complaintId); // أو استخدم Complaint::find($complaintId) لو مش معرّف find في Repository

        if (!$complaint) {
            return response()->json(['message' => 'Complaint not found'], 404);
        }

        $updated = $this->service->updateStatus($complaint, $validated['status']);

        return response()->json([
            'message' => 'Status updated successfully',
            'complaint' => $updated
        ]);
    }
}
