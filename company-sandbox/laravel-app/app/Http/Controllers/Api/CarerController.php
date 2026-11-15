<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carer;
use App\Models\LeaveRequest;
use App\Services\HrService;
use App\Services\BusinessRuleException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CarerController extends Controller
{
    public function __construct(protected HrService $hrService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Carer::query();

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->has('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        return response()->json($query->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        $carer = Carer::with(['complianceChecks'])->find($id);

        if (!$carer) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Carer not found.', 'details' => []]], 404);
        }

        return response()->json($carer);
    }

    public function getCompliance(int $id): JsonResponse
    {
        $carer = Carer::with('complianceChecks')->find($id);
        if (!$carer) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Carer not found.', 'details' => []]], 404);
        }

        $checks = $this->hrService->getCarerComplianceWithValidity($carer);
        return response()->json($checks);
    }

    public function recordCompliance(Request $request, int $id): JsonResponse
    {
        $carer = Carer::find($id);
        if (!$carer) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Carer not found.', 'details' => []]], 404);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:dbs,right_to_work,moving_handling,safeguarding',
            'issued_on' => 'required|date',
            'expires_on' => 'required|date',
        ]);

        $check = $this->hrService->recordComplianceCheck($carer, $validated);
        return response()->json($check, 201);
    }

    public function listLeave(Request $request): JsonResponse
    {
        $query = LeaveRequest::with('carer');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('carer_id')) {
            $query->where('carer_id', $request->query('carer_id'));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        return response()->json($query->paginate($perPage));
    }

    public function requestLeave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'carer_id' => 'required|exists:carers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'type' => 'nullable|string|in:annual,sick,unpaid',
        ]);

        try {
            $leave = $this->hrService->submitLeaveRequest($validated);
            return response()->json($leave, 201);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function decideLeave(Request $request, int $id): JsonResponse
    {
        $leave = LeaveRequest::find($id);
        if (!$leave) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Leave request not found.', 'details' => []]], 404);
        }

        $request->validate(['status' => 'required|string|in:approved,rejected']);

        try {
            $updated = $this->hrService->decideLeaveRequest($leave, $request->input('status'));
            return response()->json($updated);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }
}

