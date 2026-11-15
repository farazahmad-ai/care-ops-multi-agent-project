<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\Client;
use App\Services\OperationsService;
use App\Services\BusinessRuleException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    public function __construct(protected OperationsService $operationsService) {}

    public function listClients(): JsonResponse
    {
        return response()->json(Client::paginate(50));
    }

    public function showClient(int $id): JsonResponse
    {
        $client = Client::find($id);
        if (!$client) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Client not found.', 'details' => []]], 404);
        }
        return response()->json($client);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Shift::with(['client', 'assignments.carer']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->query('client_id'));
        }

        if ($request->has('from')) {
            $query->where('starts_at', '>=', $request->query('from'));
        }

        if ($request->has('to')) {
            $query->where('starts_at', '<=', $request->query('to'));
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        return response()->json($query->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        $shift = Shift::with(['client', 'assignments.carer', 'assignments.assignedByUser'])->find($id);
        if (!$shift) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Shift not found.', 'details' => []]], 404);
        }

        return response()->json($shift);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'role_required' => 'nullable|string|in:carer,senior_carer',
            'notes' => 'nullable|string',
        ]);

        $shift = Shift::create($validated)->refresh();
        return response()->json($shift, 201);
    }

    public function eligibleCarers(int $id): JsonResponse
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Shift not found.', 'details' => []]], 404);
        }

        $eligible = $this->operationsService->getEligibleCarersForShift($shift);
        return response()->json($eligible);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Shift not found.', 'details' => []]], 404);
        }

        $validated = $request->validate([
            'carer_id' => 'required|exists:carers,id',
            'assigned_by' => 'nullable|exists:staff_users,id',
        ]);

        try {
            $assignment = $this->operationsService->assignCarerToShift(
                $shift,
                (int) $validated['carer_id'],
                isset($validated['assigned_by']) ? (int) $validated['assigned_by'] : null
            );
            return response()->json($assignment, 200);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function cancel(int $id): JsonResponse
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Shift not found.', 'details' => []]], 404);
        }

        $cancelled = $this->operationsService->cancelShift($shift);
        return response()->json($cancelled);
    }
}

