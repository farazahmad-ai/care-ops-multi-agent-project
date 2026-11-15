<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Carer;
use App\Models\ComplianceCheck;
use App\Models\Interview;
use Carbon\Carbon;

class RecruitmentService
{
    // Valid status progression: applied -> screening -> interview -> offered -> hired (or rejected)
    protected array $validTransitions = [
        'applied' => ['screening', 'rejected'],
        'screening' => ['interview', 'rejected'],
        'interview' => ['offered', 'rejected'],
        'offered' => ['hired', 'rejected'],
        'hired' => [],
        'rejected' => ['screening'], // Re-application
    ];

    public function transitionCandidateStatus(Candidate $candidate, string $newStatus): Candidate
    {
        $allowed = $this->validTransitions[$candidate->status] ?? [];

        // R12: Candidate status can only move forward along valid transitions
        if (!in_array($newStatus, $allowed, true)) {
            throw new BusinessRuleException(
                'INVALID_TRANSITION',
                "Cannot transition candidate from '{$candidate->status}' to '{$newStatus}'.",
                422,
                ['current_status' => $candidate->status, 'target_status' => $newStatus]
            );
        }

        $candidate->update(['status' => $newStatus]);
        return $candidate;
    }

    public function bookInterview(Candidate $candidate, array $data): Interview
    {
        return Interview::create([
            'candidate_id' => $candidate->id,
            'scheduled_at' => $data['scheduled_at'],
            'interviewer' => $data['interviewer'],
            'outcome' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    // R8: A candidate can only be hired if status is offered and they have valid DBS and right-to-work
    public function hireCandidate(Candidate $candidate): Carer
    {
        if ($candidate->status !== 'offered') {
            throw new BusinessRuleException(
                'CANDIDATE_NOT_HIREABLE',
                "Candidate must be in 'offered' status to be hired. Current status: {$candidate->status}",
                422,
                ['status' => $candidate->status]
            );
        }

        $now = Carbon::now()->format('Y-m-d');

        $validDbs = ComplianceCheck::where('candidate_id', $candidate->id)
            ->where('type', 'dbs')
            ->where('expires_on', '>=', $now)
            ->exists();

        $validRtw = ComplianceCheck::where('candidate_id', $candidate->id)
            ->where('type', 'right_to_work')
            ->where('expires_on', '>=', $now)
            ->exists();

        if (!$validDbs || !$validRtw) {
            throw new BusinessRuleException(
                'CANDIDATE_NOT_HIREABLE',
                'Candidate cannot be hired without valid unexpired DBS and Right-to-Work checks.',
                422,
                ['dbs_valid' => $validDbs, 'right_to_work_valid' => $validRtw]
            );
        }

        $candidate->update(['status' => 'hired']);

        $carer = Carer::create([
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'postcode' => $candidate->postcode,
            'role' => 'carer',
            'status' => 'active',
            'hired_at' => Carbon::now(),
            'candidate_id' => $candidate->id,
        ]);

        // Transfer compliance checks from candidate to carer
        ComplianceCheck::where('candidate_id', $candidate->id)
            ->update(['carer_id' => $carer->id]);

        return $carer;
    }
}

