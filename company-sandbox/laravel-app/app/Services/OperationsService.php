<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\Carer;
use App\Models\ShiftAssignment;
use App\Models\LeaveRequest;
use App\Models\ComplianceCheck;
use Carbon\Carbon;

class OperationsService
{
    // R1, R2, R3, R4: Assign a carer to a shift
    public function assignCarerToShift(Shift $shift, int $carerId, ?int $assignedBy = null): ShiftAssignment
    {
        $carer = Carer::findOrFail($carerId);

        // Idempotency: Repeating an identical assign request returns existing assignment (200)
        $existing = ShiftAssignment::where('shift_id', $shift->id)
            ->where('carer_id', $carer->id)
            ->where('status', 'confirmed')
            ->first();

        if ($existing) {
            return $existing;
        }

        // R4: Can only assign to a shift that is 'open'
        if ($shift->status !== 'open') {
            throw new BusinessRuleException(
                'SHIFT_NOT_OPEN',
                "Shift #{$shift->id} is not open for assignment (current status: {$shift->status}).",
                409,
                ['shift_id' => $shift->id, 'status' => $shift->status]
            );
        }

        // R4: Role mismatch check. Senior carer may fill carer shift, but carer cannot fill senior_carer shift.
        if ($shift->role_required === 'senior_carer' && $carer->role !== 'senior_carer') {
            throw new BusinessRuleException(
                'ROLE_MISMATCH',
                "Shift requires role 'senior_carer' but carer has role '{$carer->role}'.",
                422,
                ['required_role' => $shift->role_required, 'carer_role' => $carer->role]
            );
        }

        // R1: Cannot assign a carer whose DBS or right-to-work is missing or expired on the shift date
        $shiftDate = Carbon::parse($shift->starts_at)->format('Y-m-d');

        $validDbs = ComplianceCheck::where('carer_id', $carer->id)
            ->where('type', 'dbs')
            ->where('expires_on', '>=', $shiftDate)
            ->exists();

        $validRtw = ComplianceCheck::where('carer_id', $carer->id)
            ->where('type', 'right_to_work')
            ->where('expires_on', '>=', $shiftDate)
            ->exists();

        if (!$validDbs || !$validRtw) {
            throw new BusinessRuleException(
                'CARER_COMPLIANCE_EXPIRED',
                "Carer compliance check (DBS or Right-to-Work) is expired or missing on shift date ({$shiftDate}).",
                422,
                ['dbs_valid' => $validDbs, 'right_to_work_valid' => $validRtw, 'shift_date' => $shiftDate]
            );
        }

        // R3: Cannot assign a carer on approved leave
        $shiftDay = Carbon::parse($shift->starts_at)->format('Y-m-d');
        $onLeave = LeaveRequest::where('carer_id', $carer->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $shiftDay)
            ->where('end_date', '>=', $shiftDay)
            ->exists();

        if ($onLeave) {
            throw new BusinessRuleException(
                'CARER_ON_LEAVE',
                "Carer is on approved leave on the shift date ({$shiftDay}).",
                409,
                ['shift_date' => $shiftDay]
            );
        }

        // R2: Cannot assign a carer to overlapping shifts
        if ($this->hasOverlappingShift($carer->id, $shift)) {
            throw new BusinessRuleException(
                'SHIFT_OVERLAP',
                'Carer has an overlapping confirmed shift assignment during this time window.',
                409,
                ['shift_starts_at' => $shift->starts_at, 'shift_ends_at' => $shift->ends_at]
            );
        }

        $assignment = ShiftAssignment::create([
            'shift_id' => $shift->id,
            'carer_id' => $carer->id,
            'status' => 'confirmed',
            'assigned_at' => Carbon::now(),
            'assigned_by' => $assignedBy,
        ]);

        $shift->update(['status' => 'assigned']);

        return $assignment;
    }

    public function cancelShift(Shift $shift): Shift
    {
        $shift->update(['status' => 'cancelled']);
        ShiftAssignment::where('shift_id', $shift->id)->update(['status' => 'cancelled']);
        return $shift;
    }

    // Returns eligible carers passing R1, R2, R3, R4
    public function getEligibleCarersForShift(Shift $shift): array
    {
        $shiftDate = Carbon::parse($shift->starts_at)->format('Y-m-d');
        $shiftDay = $shiftDate;

        $carers = Carer::where('status', 'active');

        if ($shift->role_required === 'senior_carer') {
            $carers->where('role', 'senior_carer');
        }

        $allCarers = $carers->get();

        return $allCarers->filter(function ($carer) use ($shift, $shiftDate, $shiftDay) {
            // DBS & RTW
            $validDbs = ComplianceCheck::where('carer_id', $carer->id)->where('type', 'dbs')->where('expires_on', '>=', $shiftDate)->exists();
            $validRtw = ComplianceCheck::where('carer_id', $carer->id)->where('type', 'right_to_work')->where('expires_on', '>=', $shiftDate)->exists();
            if (!$validDbs || !$validRtw) return false;

            // Leave
            $onLeave = LeaveRequest::where('carer_id', $carer->id)->where('status', 'approved')->where('start_date', '<=', $shiftDay)->where('end_date', '>=', $shiftDay)->exists();
            if ($onLeave) return false;

            // Overlap
            return !$this->hasOverlappingShift($carer->id, $shift);
        })->values()->toArray();
    }

    private function hasOverlappingShift(int $carerId, Shift $shift): bool
    {
        return ShiftAssignment::where('carer_id', $carerId)
            ->where('status', 'confirmed')
            ->whereHas('shift', function ($q) use ($shift) {
                $q->where('id', '!=', $shift->id)
                  ->where('status', '!=', 'cancelled')
                  ->where('starts_at', '<', $shift->ends_at)
                  ->where('ends_at', '>', $shift->starts_at);
            })->exists();
    }
}

