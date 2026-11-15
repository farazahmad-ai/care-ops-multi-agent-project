<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use App\Models\Invoice;
use App\Models\PayRate;
use App\Services\FinanceService;
use App\Services\BusinessRuleException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function __construct(protected FinanceService $financeService) {}

    public function payRates(): JsonResponse
    {
        return response()->json(PayRate::all());
    }

    public function listTimesheets(Request $request): JsonResponse
    {
        $query = Timesheet::with(['assignment.shift.client', 'carer', 'approvedByUser']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('carer_id')) {
            $query->where('carer_id', $request->query('carer_id'));
        }

        if ($request->has('from')) {
            $query->where('submitted_at', '>=', $request->query('from'));
        }

        if ($request->has('to')) {
            $query->where('submitted_at', '<=', $request->query('to'));
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        return response()->json($query->paginate($perPage));
    }

    public function submitTimesheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shift_assignment_id' => 'required|exists:shift_assignments,id',
            'hours_worked' => 'required|numeric',
        ]);

        try {
            $timesheet = $this->financeService->submitTimesheet($validated);
            return response()->json($timesheet, 201);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function updateHours(Request $request, int $id): JsonResponse
    {
        $timesheet = Timesheet::find($id);
        if (!$timesheet) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Timesheet not found.', 'details' => []]], 404);
        }

        $request->validate(['hours_worked' => 'required|numeric']);

        try {
            $updated = $this->financeService->updateTimesheetHours($timesheet, (float) $request->input('hours_worked'));
            return response()->json($updated);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $timesheet = Timesheet::find($id);
        if (!$timesheet) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Timesheet not found.', 'details' => []]], 404);
        }

        $approvedBy = $request->input('approved_by');

        try {
            $approved = $this->financeService->approveTimesheet($timesheet, $approvedBy ? (int)$approvedBy : null);
            return response()->json($approved);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $timesheet = Timesheet::find($id);
        if (!$timesheet) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Timesheet not found.', 'details' => []]], 404);
        }

        $reason = (string) $request->input('reason', 'Rejected');

        try {
            $rejected = $this->financeService->rejectTimesheet($timesheet, $reason);
            return response()->json($rejected);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }

    public function listInvoices(Request $request): JsonResponse
    {
        $query = Invoice::with(['client', 'lines']);

        if ($request->has('client_id')) {
            $query->where('client_id', $request->query('client_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        return response()->json($query->paginate($perPage));
    }

    public function showInvoice(int $id): JsonResponse
    {
        $invoice = Invoice::with(['client', 'lines.timesheet.carer'])->find($id);
        if (!$invoice) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Invoice not found.', 'details' => []]], 404);
        }

        return response()->json($invoice);
    }

    public function generateInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        try {
            $invoice = $this->financeService->generateInvoice(
                (int) $validated['client_id'],
                $validated['period_start'],
                $validated['period_end']
            );
            return response()->json($invoice, 201);
        } catch (BusinessRuleException $e) {
            return response()->json($e->toResponseArray(), $e->getStatusCode());
        }
    }
}

