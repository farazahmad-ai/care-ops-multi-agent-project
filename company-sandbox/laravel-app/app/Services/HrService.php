<?php

namespace App\Services;

use App\Models\Carer;
use App\Models\ComplianceCheck;
use App\Models\LeaveRequest;
use Carbon\Carbon;

class HrService
{
    public function getCarerComplianceWithValidity(Carer $carer): array
    {
        $checks = $carer->complianceChecks;
        $today = Carbon::now()->startOfDay();

        return $checks->map(function ($check) use ($today) {
            $expiry = Carbon::parse($check->expires_on)->startOfDay();
            [$computedStatus, $daysRemaining] = $this->computeComplianceStatus($expiry, $today);

            return [
                'id' => $check->id,
                'type' => $check->type,
                'issued_on' => $check->issued_on,
                'expires_on' => $check->expires_on,
                'stored_status' => $check->status,
                'computed_status' => $computedStatus,
                'days_remaining' => $daysRemaining,
            ];
        })->toArray();
    }

    public function recordComplianceCheck(Carer $carer, array $data): ComplianceCheck
    {
        $expiry = Carbon::parse($data['expires_on'])->startOfDay();
        $today = Carbon::now()->startOfDay();
        [$status] = $this->computeComplianceStatus($expiry, $today);

        return ComplianceCheck::create([
            'carer_id' => $carer->id,
            'candidate_id' => null,
            'type' => $data['type'],
            'issued_on' => $data['issued_on'],
            'expires_on' => $data['expires_on'],
            'status' => $status,
        ]);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function computeComplianceStatus(Carbon $expiry, Carbon $today): array
    {
        $daysRemaining = (int) $today->diffInDays($expiry, false);

        if ($daysRemaining < 0) {
            return ['expired', $daysRemaining];
        }
        if ($daysRemaining <= 30) {
            return ['expiring_soon', $daysRemaining];
        }

        return ['valid', $daysRemaining];
    }

    // R7: Leave request end date cannot be before start date, and cannot overlap another approved leave
    public function submitLeaveRequest(array $data): LeaveRequest
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if ($end->lt($start)) {
            throw new BusinessRuleException(
                'INVALID_DATES',
                'Leave end date cannot be earlier than start date.',
                422,
                ['start_date' => $data['start_date'], 'end_date' => $data['end_date']]
            );
        }

        // Check for overlapping approved leave
        if ($this->hasOverlappingLeave((int) $data['carer_id'], $data['start_date'], $data['end_date'])) {
            throw new BusinessRuleException(
                'LEAVE_OVERLAP',
                'Leave dates overlap with an existing approved leave request.',
                409,
                ['carer_id' => $data['carer_id']]
            );
        }

        return LeaveRequest::create([
            'carer_id' => $data['carer_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'type' => $data['type'] ?? 'annual',
            'status' => 'pending',
            'requested_at' => Carbon::now(),
        ]);
    }

    public function decideLeaveRequest(LeaveRequest $leave, string $decision): LeaveRequest
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new BusinessRuleException(
                'INVALID_DECISION',
                "Decision must be 'approved' or 'rejected'.",
                422
            );
        }

        if ($decision === 'approved') {
            // Re-verify no overlap at approval time
            $start = Carbon::parse($leave->start_date)->format('Y-m-d');
            $end = Carbon::parse($leave->end_date)->format('Y-m-d');
            if ($this->hasOverlappingLeave((int) $leave->carer_id, $start, $end, $leave->id)) {
                throw new BusinessRuleException(
                    'LEAVE_OVERLAP',
                    'Cannot approve leave that overlaps with already approved leave.',
                    409
                );
            }
        }

        $leave->update([
            'status' => $decision,
            'decided_at' => Carbon::now(),
        ]);

        return $leave;
    }

    private function hasOverlappingLeave(int $carerId, string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        return LeaveRequest::where('carer_id', $carerId)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('status', 'approved')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->exists();
    }
}

