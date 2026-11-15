<?php

namespace App\Services;

use App\Models\Timesheet;
use App\Models\ShiftAssignment;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PayRate;
use App\Models\Client;
use Carbon\Carbon;

class FinanceService
{
    // R9, R10: Submit timesheet
    public function submitTimesheet(array $data): Timesheet
    {
        $assignment = ShiftAssignment::with('shift')->findOrFail($data['shift_assignment_id']);

        // R9: Needs a confirmed assignment
        if ($assignment->status !== 'confirmed') {
            throw new BusinessRuleException(
                'INVALID_ASSIGNMENT',
                'Timesheet can only be submitted for a confirmed shift assignment.',
                422,
                ['assignment_status' => $assignment->status]
            );
        }

        $hours = (float) $data['hours_worked'];

        // R9: Hours must be greater than 0
        if ($hours <= 0) {
            throw new BusinessRuleException(
                'INVALID_HOURS',
                'Timesheet hours worked must be greater than 0.00.',
                422,
                ['hours_worked' => $hours]
            );
        }

        // R9: Cannot duplicate an existing timesheet for that assignment
        $existing = Timesheet::where('shift_assignment_id', $assignment->id)->exists();
        if ($existing) {
            throw new BusinessRuleException(
                'DUPLICATE_TIMESHEET',
                "A timesheet has already been submitted for shift assignment #{$assignment->id}.",
                409,
                ['shift_assignment_id' => $assignment->id]
            );
        }

        // R10: Hours cannot exceed shift duration plus 1 hour
        $shiftStart = Carbon::parse($assignment->shift->starts_at);
        $shiftEnd = Carbon::parse($assignment->shift->ends_at);
        $shiftDuration = $shiftStart->diffInHours($shiftEnd, false);
        $maxAllowedHours = $shiftDuration + 1.0;

        if ($hours > $maxAllowedHours) {
            throw new BusinessRuleException(
                'HOURS_EXCEED_SHIFT',
                "Claimed hours ({$hours}h) exceed maximum allowed duration ({$maxAllowedHours}h: {$shiftDuration}h shift + 1h grace).",
                422,
                ['claimed_hours' => $hours, 'max_allowed_hours' => $maxAllowedHours]
            );
        }

        return Timesheet::create([
            'shift_assignment_id' => $assignment->id,
            'carer_id' => $assignment->carer_id,
            'hours_worked' => $hours,
            'status' => 'submitted',
            'submitted_at' => Carbon::now(),
        ]);
    }

    // R6: An approved timesheet cannot be edited
    public function updateTimesheetHours(Timesheet $timesheet, float $newHours): Timesheet
    {
        if ($timesheet->status === 'approved') {
            throw new BusinessRuleException(
                'TIMESHEET_LOCKED',
                'An approved timesheet cannot be edited.',
                409,
                ['timesheet_id' => $timesheet->id, 'status' => $timesheet->status]
            );
        }

        if ($newHours <= 0) {
            throw new BusinessRuleException(
                'INVALID_HOURS',
                'Hours worked must be greater than 0.',
                422
            );
        }

        $timesheet->update(['hours_worked' => $newHours]);
        return $timesheet;
    }

    // R5: A timesheet cannot be approved twice
    public function approveTimesheet(Timesheet $timesheet, ?int $approvedBy = null): Timesheet
    {
        if ($timesheet->status === 'approved') {
            throw new BusinessRuleException(
                'TIMESHEET_ALREADY_APPROVED',
                "Timesheet #{$timesheet->id} has already been approved and cannot be approved twice.",
                409,
                ['timesheet_id' => $timesheet->id]
            );
        }

        $timesheet->update([
            'status' => 'approved',
            'approved_at' => Carbon::now(),
            'approved_by' => $approvedBy,
        ]);

        return $timesheet;
    }

    public function rejectTimesheet(Timesheet $timesheet, string $reason): Timesheet
    {
        if ($timesheet->status === 'approved') {
            throw new BusinessRuleException(
                'TIMESHEET_LOCKED',
                'Cannot reject an already approved timesheet.',
                409
            );
        }

        $timesheet->update([
            'status' => 'rejected',
        ]);

        return $timesheet;
    }

    // R11: An invoice can only include approved, not-yet-invoiced timesheets
    public function generateInvoice(int $clientId, string $periodStart, string $periodEnd): Invoice
    {
        $client = Client::findOrFail($clientId);

        // Find approved timesheets for this client in this date window not yet invoiced
        $timesheets = Timesheet::where('status', 'approved')
            ->whereDoesntHave('invoiceLines')
            ->whereHas('assignment.shift', function ($q) use ($clientId, $periodStart, $periodEnd) {
                $q->where('client_id', $clientId)
                  ->whereBetween('starts_at', [$periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
            })
            ->with(['assignment.shift', 'carer'])
            ->get();

        if ($timesheets->isEmpty()) {
            throw new BusinessRuleException(
                'NOTHING_TO_INVOICE',
                "No approved, uninvoiced timesheets found for client #{$clientId} in period {$periodStart} to {$periodEnd}.",
                422,
                ['client_id' => $clientId, 'period_start' => $periodStart, 'period_end' => $periodEnd]
            );
        }

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total' => 0.00,
            'status' => 'draft',
        ]);

        $totalAmount = 0.00;
        $carerRate = PayRate::where('role', 'carer')->latest('effective_from')->first()->hourly_charge ?? 21.00;
        $seniorRate = PayRate::where('role', 'senior_carer')->latest('effective_from')->first()->hourly_charge ?? 25.50;

        foreach ($timesheets as $ts) {
            $hours = (float) $ts->hours_worked;
            $rate = ($ts->carer->role === 'senior_carer') ? (float) $seniorRate : (float) $carerRate;
            $amount = round($hours * $rate, 2);

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'timesheet_id' => $ts->id,
                'hours' => $hours,
                'rate' => $rate,
                'amount' => $amount,
            ]);

            $totalAmount += $amount;
        }

        $invoice->update(['total' => $totalAmount]);

        return $invoice->load('lines');
    }
}

