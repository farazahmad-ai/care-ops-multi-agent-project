<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Carer;
use App\Models\Client;
use App\Models\ComplianceCheck;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\PayRate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffUser;
use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareOpsApiEndpointsTest extends TestCase
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

    public function test_unconfigured_service_token_fails_closed(): void
    {
        config(['services.legacy.service_token' => null]);

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/candidates')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_validation_error_uses_standard_422_format(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/shifts', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }

    public function test_candidates_list_filters_pagination_show_compliance_and_interview(): void
    {
        $c1 = Candidate::factory()->create(['first_name' => 'Zara', 'status' => 'screening']);
        Candidate::factory()->count(3)->create(['status' => 'applied']);
        ComplianceCheck::factory()->create(['candidate_id' => $c1->id, 'type' => 'dbs']);

        // Filter by status & search + per_page
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/candidates?status=screening&search=Zara&per_page=2')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('per_page', 2);

        // Show & 404
        $this->withHeaders($this->authHeaders())->getJson("/api/candidates/{$c1->id}")->assertStatus(200);
        $this->withHeaders($this->authHeaders())->getJson('/api/candidates/999999')->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        // Candidate compliance
        $this->withHeaders($this->authHeaders())
            ->getJson("/api/candidates/{$c1->id}/compliance")
            ->assertStatus(200)
            ->assertJsonCount(1);

        // Book interview
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/candidates/{$c1->id}/interviews", [
                'scheduled_at' => '2026-12-01 10:00:00',
                'interviewer' => 'Alice Walker',
                'notes' => 'Initial screen',
            ])
            ->assertStatus(201)
            ->assertJsonPath('candidate_id', $c1->id);
    }

    public function test_carers_endpoints_filters_compliance_and_leave_workflow(): void
    {
        $carer = Carer::factory()->create(['first_name' => 'Elena', 'role' => 'senior_carer', 'status' => 'active']);
        Carer::factory()->create(['role' => 'carer', 'status' => 'left']);

        // Filter carers
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/carers?status=active&role=senior_carer&search=Elena')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Show carer + 404
        $this->withHeaders($this->authHeaders())->getJson("/api/carers/{$carer->id}")->assertStatus(200);
        $this->withHeaders($this->authHeaders())->getJson('/api/carers/999999')->assertStatus(404);

        // Record compliance checks: expiring_soon (15d), valid (200d), expired (-10d)
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/carers/{$carer->id}/compliance-checks", [
                'type' => 'dbs',
                'issued_on' => Carbon::now()->subMonths(1)->format('Y-m-d'),
                'expires_on' => Carbon::now()->addDays(15)->format('Y-m-d'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'expiring_soon');

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/carers/{$carer->id}/compliance-checks", [
                'type' => 'right_to_work',
                'issued_on' => Carbon::now()->subMonths(1)->format('Y-m-d'),
                'expires_on' => Carbon::now()->addDays(200)->format('Y-m-d'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'valid');

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/carers/{$carer->id}/compliance-checks", [
                'type' => 'safeguarding',
                'issued_on' => Carbon::now()->subYear()->format('Y-m-d'),
                'expires_on' => Carbon::now()->subDays(10)->format('Y-m-d'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'expired');

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/carers/{$carer->id}/compliance")
            ->assertStatus(200)
            ->assertJsonPath('0.computed_status', 'expiring_soon')
            ->assertJsonPath('1.computed_status', 'valid')
            ->assertJsonPath('2.computed_status', 'expired');

        // Leave request create -> approve -> overlap rejection
        $createRes = $this->withHeaders($this->authHeaders())
            ->postJson('/api/leave-requests', [
                'carer_id' => $carer->id,
                'start_date' => '2026-12-10',
                'end_date' => '2026-12-12',
                'type' => 'annual',
            ])
            ->assertStatus(201);

        $leaveId = $createRes->json('id');

        $this->withHeaders($this->authHeaders())
            ->patchJson("/api/leave-requests/{$leaveId}", ['status' => 'approved'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'approved');

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/leave-requests?carer_id={$carer->id}&status=approved")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // R7 overlap check on new request
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/leave-requests', [
                'carer_id' => $carer->id,
                'start_date' => '2026-12-11',
                'end_date' => '2026-12-13',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'LEAVE_OVERLAP');
    }

    public function test_operations_clients_shifts_eligible_idempotent_assign_and_cancel(): void
    {
        $client = Client::factory()->create();
        $staff = StaffUser::factory()->create();
        $seniorCarer = Carer::factory()->create(['role' => 'senior_carer', 'status' => 'active']);
        ComplianceCheck::factory()->create(['carer_id' => $seniorCarer->id, 'type' => 'dbs', 'expires_on' => Carbon::now()->addYear()]);
        ComplianceCheck::factory()->create(['carer_id' => $seniorCarer->id, 'type' => 'right_to_work', 'expires_on' => Carbon::now()->addYear()]);

        // Clients list & show
        $this->withHeaders($this->authHeaders())->getJson('/api/clients')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->withHeaders($this->authHeaders())->getJson("/api/clients/{$client->id}")->assertStatus(200);
        $this->withHeaders($this->authHeaders())->getJson('/api/clients/999999')->assertStatus(404);

        // Create shift
        $shiftRes = $this->withHeaders($this->authHeaders())
            ->postJson('/api/shifts', [
                'client_id' => $client->id,
                'starts_at' => Carbon::tomorrow()->setTime(8, 0)->format('Y-m-d H:i:s'),
                'ends_at' => Carbon::tomorrow()->setTime(16, 0)->format('Y-m-d H:i:s'),
                'role_required' => 'carer',
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'open');

        $shiftId = $shiftRes->json('id');

        // Filter shifts & show
        $this->withHeaders($this->authHeaders())
            ->getJson("/api/shifts?status=open&client_id={$client->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->authHeaders())->getJson("/api/shifts/{$shiftId}")->assertStatus(200);

        // Eligible carers (senior carer is eligible for carer shift)
        $this->withHeaders($this->authHeaders())
            ->getJson("/api/shifts/{$shiftId}/eligible-carers")
            ->assertStatus(200)
            ->assertJsonCount(1);

        // Assign + idempotent retry returns identical assignment (200) without duplicate
        $assign1 = $this->withHeaders($this->authHeaders())
            ->postJson("/api/shifts/{$shiftId}/assign", ['carer_id' => $seniorCarer->id, 'assigned_by' => $staff->id])
            ->assertStatus(200);

        $assign2 = $this->withHeaders($this->authHeaders())
            ->postJson("/api/shifts/{$shiftId}/assign", ['carer_id' => $seniorCarer->id, 'assigned_by' => $staff->id])
            ->assertStatus(200);

        $this->assertSame($assign1->json('id'), $assign2->json('id'));
        $this->assertDatabaseCount('shift_assignments', 1);

        // Cancel shift cascades to assignment
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/shifts/{$shiftId}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('shift_assignments', ['id' => $assign1->json('id'), 'status' => 'cancelled']);
    }

    public function test_finance_pay_rates_timesheets_reject_and_invoicing_r11(): void
    {
        PayRate::create(['role' => 'carer', 'hourly_pay' => 12.50, 'hourly_charge' => 21.00, 'effective_from' => '2025-01-01']);
        PayRate::create(['role' => 'senior_carer', 'hourly_pay' => 15.00, 'hourly_charge' => 25.50, 'effective_from' => '2025-01-01']);

        $client = Client::factory()->create();
        $carer = Carer::factory()->create(['role' => 'senior_carer']);
        $shift = Shift::factory()->create([
            'client_id' => $client->id,
            'starts_at' => '2026-10-10 08:00:00',
            'ends_at' => '2026-10-10 16:00:00',
            'status' => 'completed',
        ]);
        $assignment = ShiftAssignment::factory()->create([
            'shift_id' => $shift->id,
            'carer_id' => $carer->id,
            'status' => 'confirmed',
        ]);

        // Pay rates
        $this->withHeaders($this->authHeaders())->getJson('/api/pay-rates')->assertStatus(200)->assertJsonCount(2);

        // Submit, edit, reject, then submit fresh and approve
        $tsRes = $this->withHeaders($this->authHeaders())
            ->postJson('/api/timesheets', ['shift_assignment_id' => $assignment->id, 'hours_worked' => 7.5])
            ->assertStatus(201);
        $tsId = $tsRes->json('id');

        $this->withHeaders($this->authHeaders())
            ->patchJson("/api/timesheets/{$tsId}", ['hours_worked' => 8.0])
            ->assertStatus(200)
            ->assertJsonPath('hours_worked', '8.00');

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/timesheets/{$tsId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('status', 'approved');

        // Cannot reject an approved timesheet
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/timesheets/{$tsId}/reject", ['reason' => 'Too late'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'TIMESHEET_LOCKED');

        // List timesheets filtered
        $this->withHeaders($this->authHeaders())
            ->getJson("/api/timesheets?carer_id={$carer->id}&status=approved")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // R11: Generate invoice for Oct 2026 (8h * 25.50 = 204.00)
        $invRes = $this->withHeaders($this->authHeaders())
            ->postJson('/api/invoices', [
                'client_id' => $client->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-31',
            ])
            ->assertStatus(201)
            ->assertJsonPath('total', '204.00');

        $invoiceId = $invRes->json('id');

        $this->withHeaders($this->authHeaders())->getJson('/api/invoices')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->withHeaders($this->authHeaders())->getJson("/api/invoices/{$invoiceId}")->assertStatus(200);

        // R11: Generating again fails with NOTHING_TO_INVOICE because timesheet is already invoiced
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/invoices', [
                'client_id' => $client->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-31',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NOTHING_TO_INVOICE');
    }
}

