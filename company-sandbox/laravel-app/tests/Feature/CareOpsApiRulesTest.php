<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\StaffUser;
use App\Models\Candidate;
use App\Models\Carer;
use App\Models\ComplianceCheck;
use App\Models\Client;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Timesheet;
use App\Models\LeaveRequest;
use Carbon\Carbon;

class CareOpsApiRulesTest extends TestCase
{
    use RefreshDatabase;

    protected string $token = 'test-only-service-token';

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_auth_missing_token_returns_401(): void
    {
        $response = $this->getJson('/api/candidates');
        $response->assertStatus(401)
                 ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_auth_wrong_token_returns_401(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer wrong_token'])
                         ->getJson('/api/candidates');
        $response->assertStatus(401)
                 ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_health_check_does_not_require_auth(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200)
                 ->assertJsonPath('status', 'ok')
                 ->assertJsonPath('database', 'connected');
    }

    public function test_r1_cannot_assign_carer_with_expired_compliance(): void
    {
        $carer = Carer::factory()->create();
        ComplianceCheck::factory()->create([
            'carer_id' => $carer->id,
            'type' => 'dbs',
            'expires_on' => Carbon::yesterday()->format('Y-m-d'),
        ]);
        ComplianceCheck::factory()->create([
            'carer_id' => $carer->id,
            'type' => 'right_to_work',
            'expires_on' => Carbon::tomorrow()->format('Y-m-d'),
        ]);

        $shift = Shift::factory()->create([
            'starts_at' => Carbon::tomorrow()->setTime(8, 0),
            'ends_at' => Carbon::tomorrow()->setTime(16, 0),
            'role_required' => 'carer',
            'status' => 'open',
        ]);

        $response = $this->withHeaders($this->authHeaders())
                         ->postJson("/api/shifts/{$shift->id}/assign", ['carer_id' => $carer->id]);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'CARER_COMPLIANCE_EXPIRED');
    }

    public function test_r2_cannot_assign_carer_to_overlapping_shifts(): void
    {
        $carer = Carer::factory()->create(['role' => 'carer', 'status' => 'active']);
        ComplianceCheck::factory()->create(['carer_id' => $carer->id, 'type' => 'dbs', 'expires_on' => Carbon::now()->addYear()]);
        ComplianceCheck::factory()->create(['carer_id' => $carer->id, 'type' => 'right_to_work', 'expires_on' => Carbon::now()->addYear()]);

        $shift1 = Shift::factory()->create([
            'starts_at' => Carbon::tomorrow()->setTime(8, 0),
            'ends_at' => Carbon::tomorrow()->setTime(16, 0),
            'role_required' => 'carer',
            'status' => 'open',
        ]);
        $shift2 = Shift::factory()->create([
            'starts_at' => Carbon::tomorrow()->setTime(14, 0),
            'ends_at' => Carbon::tomorrow()->setTime(20, 0),
            'role_required' => 'carer',
            'status' => 'open',
        ]);

        // Assign shift1 first
        $this->withHeaders($this->authHeaders())
             ->postJson("/api/shifts/{$shift1->id}/assign", ['carer_id' => $carer->id])
             ->assertStatus(200);

        // Attempt overlapping shift2
        $response = $this->withHeaders($this->authHeaders())
                         ->postJson("/api/shifts/{$shift2->id}/assign", ['carer_id' => $carer->id]);

        $response->assertStatus(409)
                 ->assertJsonPath('error.code', 'SHIFT_OVERLAP');
    }

    public function test_r3_cannot_assign_carer_on_approved_leave(): void
    {
        $carer = Carer::factory()->create(['role' => 'carer', 'status' => 'active']);
        ComplianceCheck::factory()->create(['carer_id' => $carer->id, 'type' => 'dbs', 'expires_on' => Carbon::now()->addYear()]);
        ComplianceCheck::factory()->create(['carer_id' => $carer->id, 'type' => 'right_to_work', 'expires_on' => Carbon::now()->addYear()]);

        LeaveRequest::create([
            'carer_id' => $carer->id,
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'status' => 'approved',
        ]);

        $shift = Shift::factory()->create([
            'starts_at' => Carbon::tomorrow()->setTime(8, 0),
            'ends_at' => Carbon::tomorrow()->setTime(16, 0),
            'role_required' => 'carer',
            'status' => 'open',
        ]);

        $response = $this->withHeaders($this->authHeaders())
                         ->postJson("/api/shifts/{$shift->id}/assign", ['carer_id' => $carer->id]);

        $response->assertStatus(409)
                 ->assertJsonPath('error.code', 'CARER_ON_LEAVE');
    }

    public function test_r4_cannot_assign_carer_to_non_open_shift_or_role_mismatch(): void
    {
        $carer = Carer::factory()->create(['role' => 'carer']);
        ComplianceCheck::factory()->create(['carer_id' => $carer->id, 'type' => 'dbs', 'expires_on' => Carbon::now()->addYear()]);
        ComplianceCheck::factory()->create(['carer_id' => $carer->id, 'type' => 'right_to_work', 'expires_on' => Carbon::now()->addYear()]);

        // Non-open shift
        $cancelledShift = Shift::factory()->create(['status' => 'cancelled']);
        $this->withHeaders($this->authHeaders())
             ->postJson("/api/shifts/{$cancelledShift->id}/assign", ['carer_id' => $carer->id])
             ->assertStatus(409)
             ->assertJsonPath('error.code', 'SHIFT_NOT_OPEN');

        // Role mismatch
        $seniorShift = Shift::factory()->create(['role_required' => 'senior_carer', 'status' => 'open']);
        $this->withHeaders($this->authHeaders())
             ->postJson("/api/shifts/{$seniorShift->id}/assign", ['carer_id' => $carer->id])
             ->assertStatus(422)
             ->assertJsonPath('error.code', 'ROLE_MISMATCH');
    }

    public function test_r5_and_r6_timesheet_approval_and_locking(): void
    {
        $timesheet = Timesheet::factory()->create(['status' => 'submitted', 'hours_worked' => 8.00]);

        // Approve timesheet
        $this->withHeaders($this->authHeaders())
             ->postJson("/api/timesheets/{$timesheet->id}/approve")
             ->assertStatus(200);

        // R5: Cannot approve twice
        $this->withHeaders($this->authHeaders())
             ->postJson("/api/timesheets/{$timesheet->id}/approve")
             ->assertStatus(409)
             ->assertJsonPath('error.code', 'TIMESHEET_ALREADY_APPROVED');

        // R6: Approved timesheet cannot be edited
        $this->withHeaders($this->authHeaders())
             ->patchJson("/api/timesheets/{$timesheet->id}", ['hours_worked' => 9.00])
             ->assertStatus(409)
             ->assertJsonPath('error.code', 'TIMESHEET_LOCKED');
    }

    public function test_r7_leave_request_dates_validation(): void
    {
        $carer = Carer::factory()->create();

        // End before start
        $response = $this->withHeaders($this->authHeaders())
                         ->postJson('/api/leave-requests', [
                             'carer_id' => $carer->id,
                             'start_date' => '2026-12-10',
                             'end_date' => '2026-12-05',
                         ]);
        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'INVALID_DATES');
    }

    public function test_r8_candidate_hire_rule(): void
    {
        $candidate = Candidate::factory()->create(['status' => 'interview']);

        // Cannot hire if not 'offered'
        $this->withHeaders($this->authHeaders())
             ->postJson("/api/candidates/{$candidate->id}/hire")
             ->assertStatus(422)
             ->assertJsonPath('error.code', 'CANDIDATE_NOT_HIREABLE');

        $candidate->update(['status' => 'offered']);

        // Cannot hire without DBS & RTW
        $this->withHeaders($this->authHeaders())
             ->postJson("/api/candidates/{$candidate->id}/hire")
             ->assertStatus(422)
             ->assertJsonPath('error.code', 'CANDIDATE_NOT_HIREABLE');

        // Add compliance checks
        ComplianceCheck::factory()->create(['candidate_id' => $candidate->id, 'type' => 'dbs', 'expires_on' => Carbon::now()->addYear()]);
        ComplianceCheck::factory()->create(['candidate_id' => $candidate->id, 'type' => 'right_to_work', 'expires_on' => Carbon::now()->addYear()]);

        // Hire succeeds
        $response = $this->withHeaders($this->authHeaders())
                         ->postJson("/api/candidates/{$candidate->id}/hire");
        $response->assertStatus(201);
        $this->assertDatabaseHas('carers', ['email' => $candidate->email]);
    }

    public function test_r9_and_r10_timesheet_hours_and_duplicate(): void
    {
        $shift = Shift::factory()->create([
            'starts_at' => '2026-11-20 08:00:00',
            'ends_at' => '2026-11-20 16:00:00', // 8 hour shift
        ]);
        $assignment = ShiftAssignment::factory()->create([
            'shift_id' => $shift->id,
            'status' => 'confirmed'
        ]);

        // R9: Invalid hours <= 0
        $this->withHeaders($this->authHeaders())
             ->postJson('/api/timesheets', [
                 'shift_assignment_id' => $assignment->id,
                 'hours_worked' => 0,
             ])->assertStatus(422)
               ->assertJsonPath('error.code', 'INVALID_HOURS');

        // R10: Hours exceed shift length + 1 hour (8h shift allows max 9.0h, claiming 12h)
        $this->withHeaders($this->authHeaders())
             ->postJson('/api/timesheets', [
                 'shift_assignment_id' => $assignment->id,
                 'hours_worked' => 12.00,
             ])->assertStatus(422)
               ->assertJsonPath('error.code', 'HOURS_EXCEED_SHIFT');

        // Submit valid timesheet (8.00h)
        $this->withHeaders($this->authHeaders())
             ->postJson('/api/timesheets', [
                 'shift_assignment_id' => $assignment->id,
                 'hours_worked' => 8.00,
             ])->assertStatus(201);

        // R9: Duplicate timesheet for same assignment
        $this->withHeaders($this->authHeaders())
             ->postJson('/api/timesheets', [
                 'shift_assignment_id' => $assignment->id,
                 'hours_worked' => 8.00,
             ])->assertStatus(409)
               ->assertJsonPath('error.code', 'DUPLICATE_TIMESHEET');
    }

    public function test_r12_candidate_status_transition(): void
    {
        $candidate = Candidate::factory()->create(['status' => 'applied']);

        // Invalid jump: applied -> offered directly
        $this->withHeaders($this->authHeaders())
             ->patchJson("/api/candidates/{$candidate->id}/status", ['status' => 'offered'])
             ->assertStatus(422)
             ->assertJsonPath('error.code', 'INVALID_TRANSITION');

        // Valid transition: applied -> screening
        $this->withHeaders($this->authHeaders())
             ->patchJson("/api/candidates/{$candidate->id}/status", ['status' => 'screening'])
             ->assertStatus(200);
    }
}

