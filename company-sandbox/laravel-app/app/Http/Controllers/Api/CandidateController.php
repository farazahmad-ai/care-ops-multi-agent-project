<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\RecruitmentService;
use App\Services\BusinessRuleException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CandidateController extends Controller
{
    public function __construct(protected RecruitmentService $recruitmentService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Candidate::query();

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
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
        $candidate = Candidate::with(['interviews', 'complianceChecks'])->find($id);

        if (!$candidate) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Candidate not found.', 'details' => []]], 404);
        }

        return response()->json($candidate);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $candidate = Candidate::find($id);
        if (!$candidate) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Candidate not found.', 'details' => []]], 404);
        }

        $request->validate(['status' => 'required|string']);

        try {
            $updated = $this->recruitmentService->transitionCandidateStatus($candidate, $request->input('status'));
            return response()->json($updated);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function getCompliance(int $id): JsonResponse
    {
        $candidate = Candidate::find($id);
        if (!$candidate) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Candidate not found.', 'details' => []]], 404);
        }

        return response()->json($candidate->complianceChecks);
    }

    public function bookInterview(Request $request, int $id): JsonResponse
    {
        $candidate = Candidate::find($id);
        if (!$candidate) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Candidate not found.', 'details' => []]], 404);
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'interviewer' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $interview = $this->recruitmentService->bookInterview($candidate, $validated);
        return response()->json($interview, 201);
    }

    public function hire(int $id): JsonResponse
    {
        $candidate = Candidate::find($id);
        if (!$candidate) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Candidate not found.', 'details' => []]], 404);
        }

        try {
            $carer = $this->recruitmentService->hireCandidate($candidate);
            return response()->json($carer, 201);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }
}

