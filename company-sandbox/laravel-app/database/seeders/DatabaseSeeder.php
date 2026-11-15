<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\StaffUser;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Carer;
use App\Models\ComplianceCheck;
use App\Models\Client;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\LeaveRequest;
use App\Models\PayRate;
use App\Models\Timesheet;
use App\Models\Invoice;
use App\Models\InvoiceLine;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Enforce deterministic seed
        $seed = (int) env('SEED_RANDOM_VALUE', 12345);
        mt_srand($seed);
        fake()->seed($seed);

        $plantedManifest = [];

        // 2. Seed Pay Rates
        PayRate::create([
            'role' => 'carer',
            'hourly_pay' => 12.50,
            'hourly_charge' => 21.00,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);
        PayRate::create([
            'role' => 'senior_carer',
            'hourly_pay' => 15.00,
            'hourly_charge' => 25.50,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);

        // 3. Seed Staff Users (10 internal staff)
        $staffRecruitment = StaffUser::factory()->create(['name' => 'Alice Walker', 'email' => 'alice.recruitment@example.test', 'department' => 'recruitment']);
        $staffHR = StaffUser::factory()->create(['name' => 'Bob Davies', 'email' => 'bob.hr@example.test', 'department' => 'hr']);
        $staffOps = StaffUser::factory()->create(['name' => 'Charlie Smith', 'email' => 'charlie.ops@example.test', 'department' => 'operations']);
        $staffFinance = StaffUser::factory()->create(['name' => 'Diana Evans', 'email' => 'diana.finance@example.test', 'department' => 'finance']);
        StaffUser::factory()->count(6)->create();
        $allStaff = StaffUser::all();

        // 4. Seed Clients (~40 clients)
        $clients = Client::factory()->count(40)->create();

        // Plant P9: 2 pairs of duplicate clients (same client, slightly different spelling)
        $dupClient1 = Client::create([
            'name' => 'Sunrise Meadow Care Home',
            'type' => 'care_home',
            'address_line' => '14 High Street',
            'postcode' => 'OX1 2JD',
            'contact_name' => 'Sarah Jenkins',
            'contact_email' => 's.jenkins@sunrisemeadow.example.test',
            'status' => 'active',
        ]);
        $dupClient1_alt = Client::create([
            'name' => 'Sunrise Meadows Care Home Ltd',
            'type' => 'care_home',
            'address_line' => '14 High St.',
            'postcode' => 'OX1 2JD',
            'contact_name' => 'Sarah Jenkins',
            'contact_email' => 'sarah.jenkins@sunrisemeadows.example.test',
            'status' => 'active',
        ]);

        $dupClient2 = Client::create([
            'name' => 'Oakwood Residential Lodge',
            'type' => 'care_home',
            'address_line' => '82 Church Lane',
            'postcode' => 'SW1A 1AA',
            'contact_name' => 'Mark Taylor',
            'contact_email' => 'm.taylor@oakwoodlodge.example.test',
            'status' => 'active',
        ]);
        $dupClient2_alt = Client::create([
            'name' => 'Oakwood Lodge Residential Home',
            'type' => 'care_home',
            'address_line' => '82 Church Ln',
            'postcode' => 'SW1A 1AA',
            'contact_name' => 'Mark Taylor',
            'contact_email' => 'mark.taylor@oakwood.example.test',
            'status' => 'active',
        ]);

        $plantedManifest[] = [
            'id' => 'P9',
            'type' => 'duplicate_client_records',
            'table' => 'clients',
            'record_ids' => [$dupClient1->id, $dupClient1_alt->id, $dupClient2->id, $dupClient2_alt->id],
            'description' => 'Two pairs of duplicate client records with minor spelling differences.',
        ];

        // 5. Seed Candidates (~120 candidates)
        $candidates = Candidate::factory()->count(120)->create();
        foreach ($candidates->take(60) as $cand) {
            Interview::factory()->create([
                'candidate_id' => $cand->id,
                'interviewer' => $staffRecruitment->name,
            ]);
        }

        // Plant P5: 15 candidates with incomplete CVs (missing work history or empty text)
        $incompleteCandidates = $candidates->take(15);
        foreach ($incompleteCandidates as $cand) {
            $cand->update([
                'cv_text' => fake()->randomElement([
                    '',
                    'Name only. Contact phone: missing. Experience: none provided.',
                    'Quick application via job board: experienced carer, please call me.',
                ])
            ]);
        }
        $plantedManifest[] = [
            'id' => 'P5',
            'type' => 'incomplete_candidate_cvs',
            'table' => 'candidates',
            'record_ids' => $incompleteCandidates->pluck('id')->toArray(),
            'description' => '15 candidates with incomplete CVs missing contact details or employment history.',
        ];

        // 6. Seed Carers (~300 carers)
        $carers = Carer::factory()->count(300)->create();

        // Seed baseline compliance for carers (DBS, Right to Work, Moving & Handling, Safeguarding)
        foreach ($carers as $carer) {
            foreach (['dbs', 'right_to_work', 'moving_handling', 'safeguarding'] as $type) {
                ComplianceCheck::create([
                    'carer_id' => $carer->id,
                    'candidate_id' => null,
                    'type' => $type,
                    'issued_on' => Carbon::now()->subMonths(3)->format('Y-m-d'),
                    'expires_on' => Carbon::now()->addMonths(9)->format('Y-m-d'),
                    'status' => 'valid',
                ]);
            }
        }

        // Plant P2: 8 carers whose right-to-work expires within 30 days (warning)
        $p2Carers = $carers->slice(10, 8);
        $p2CheckIds = [];
        foreach ($p2Carers as $carer) {
            $rtw = ComplianceCheck::where('carer_id', $carer->id)->where('type', 'right_to_work')->first();
            $rtw->update([
                'issued_on' => Carbon::now()->subYear()->addDays(15)->format('Y-m-d'),
                'expires_on' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'status' => 'expiring_soon',
            ]);
            $p2CheckIds[] = $rtw->id;
        }
        $plantedManifest[] = [
            'id' => 'P2',
            'type' => 'right_to_work_expiring_soon',
            'table' => 'compliance_checks',
            'record_ids' => $p2CheckIds,
            'carer_ids' => $p2Carers->pluck('id')->toArray(),
            'description' => '8 carers whose Right to Work check expires within 30 days.',
        ];

        // 7. Seed Shifts (~1,000 shifts)
        // 700 past shifts (-8 weeks to yesterday) and 300 future shifts (tomorrow to +4 weeks)
        $shifts = [];
        $allClients = Client::all();

        // 700 Past Shifts
        for ($i = 0; $i < 700; $i++) {
            $dayOffset = mt_rand(1, 56);
            $startHour = mt_rand(7, 19);
            $startsAt = Carbon::now()->subDays($dayOffset)->setTime($startHour, 0, 0);
            $endsAt = (clone $startsAt)->addHours(8);

            $shift = Shift::create([
                'client_id' => $allClients->random()->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'role_required' => (mt_rand(1, 4) === 1) ? 'senior_carer' : 'carer',
                'status' => 'completed',
            ]);
            $shifts[] = $shift;
        }

        // 300 Future Shifts
        for ($i = 0; $i < 300; $i++) {
            $dayOffset = mt_rand(1, 28);
            $startHour = mt_rand(7, 19);
            $startsAt = Carbon::now()->addDays($dayOffset)->setTime($startHour, 0, 0);
            $endsAt = (clone $startsAt)->addHours(8);

            $shift = Shift::create([
                'client_id' => $allClients->random()->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'role_required' => (mt_rand(1, 4) === 1) ? 'senior_carer' : 'carer',
                'status' => (mt_rand(1, 3) === 1) ? 'open' : 'assigned',
            ]);
            $shifts[] = $shift;
        }

        // Plant P7: 4 shifts still 'open' but already in the past
        $p7ShiftIds = [];
        for ($i = 0; $i < 4; $i++) {
            $pastOpen = Shift::create([
                'client_id' => $allClients->random()->id,
                'starts_at' => Carbon::now()->subDays(mt_rand(3, 14))->setTime(8, 0, 0),
                'ends_at' => Carbon::now()->subDays(mt_rand(3, 14))->setTime(16, 0, 0),
                'role_required' => 'carer',
                'status' => 'open',
                'notes' => 'Legacy unfilled shift left in open status.',
            ]);
            $p7ShiftIds[] = $pastOpen->id;
        }
        $plantedManifest[] = [
            'id' => 'P7',
            'type' => 'past_open_shifts',
            'table' => 'shifts',
            'record_ids' => $p7ShiftIds,
            'description' => '4 shifts still marked open despite being in the past.',
        ];

        // 8. Seed Shift Assignments & Timesheets (collision-free baseline so only P3 overlaps)
        $activeCarers = Carer::where('status', 'active')->get()->values();
        $carerCount = $activeCarers->count();
        $assignedWindows = [];

        $pickNonOverlappingCarer = function (int $startIdx, Carbon $startsAt, Carbon $endsAt, array $excludedIds = []) use ($activeCarers, $carerCount, &$assignedWindows) {
            for ($offset = 0; $offset < $carerCount; $offset++) {
                $candidate = $activeCarers[($startIdx + $offset) % $carerCount];
                if (in_array($candidate->id, $excludedIds, true)) {
                    continue;
                }
                $conflict = false;
                foreach ($assignedWindows[$candidate->id] ?? [] as [$wStart, $wEnd]) {
                    if ($startsAt->lt($wEnd) && $endsAt->gt($wStart)) {
                        $conflict = true;
                        break;
                    }
                }
                if (!$conflict) {
                    $assignedWindows[$candidate->id][] = [clone $startsAt, clone $endsAt];
                    return $candidate;
                }
            }
            return $activeCarers[$startIdx % $carerCount];
        };

        $p1Carers = $activeCarers->slice(20, 12)->values();
        $p3Carers = $activeCarers->slice(40, 6)->values();
        $p8Carers = $activeCarers->slice(60, 3)->values();
        $reservedFutureIds = $p1Carers->pluck('id')->merge($p3Carers->pluck('id'))->merge($p8Carers->pluck('id'))->all();

        // Assign past completed shifts
        $pastShifts = Shift::where('status', 'completed')->get();
        foreach ($pastShifts as $idx => $shift) {
            $carer = $pickNonOverlappingCarer($idx, Carbon::parse($shift->starts_at), Carbon::parse($shift->ends_at));
            $assignment = ShiftAssignment::create([
                'shift_id' => $shift->id,
                'carer_id' => $carer->id,
                'status' => 'confirmed',
                'assigned_at' => (clone $shift->starts_at)->subDays(3),
                'assigned_by' => $staffOps->id,
            ]);

            // Timesheet for past shift
            Timesheet::create([
                'shift_assignment_id' => $assignment->id,
                'carer_id' => $carer->id,
                'hours_worked' => 8.00,
                'status' => 'approved',
                'submitted_at' => (clone $shift->ends_at)->addHours(2),
                'approved_at' => (clone $shift->ends_at)->addHours(24),
                'approved_by' => $staffFinance->id,
            ]);
        }

        // Assign future shifts marked 'assigned' (avoid P1/P3/P8 carers so ground truth stays exact)
        $futureAssignedShifts = Shift::where('status', 'assigned')->get();
        foreach ($futureAssignedShifts as $idx => $shift) {
            $carer = $pickNonOverlappingCarer($idx + 70, Carbon::parse($shift->starts_at), Carbon::parse($shift->ends_at), $reservedFutureIds);
            ShiftAssignment::create([
                'shift_id' => $shift->id,
                'carer_id' => $carer->id,
                'status' => 'confirmed',
                'assigned_at' => Carbon::now()->subDays(2),
                'assigned_by' => $staffOps->id,
            ]);
        }

        // Plant P1: 12 carers with expired DBS who have future confirmed shift assignments
        $p1AssignmentIds = [];
        $p1CarerIds = [];
        foreach ($p1Carers as $idx => $carer) {
            // Expire DBS
            $dbs = ComplianceCheck::where('carer_id', $carer->id)->where('type', 'dbs')->first();
            $dbs->update([
                'issued_on' => Carbon::now()->subYears(2)->format('Y-m-d'),
                'expires_on' => Carbon::now()->subMonths(2)->format('Y-m-d'),
                'status' => 'expired',
            ]);

            // Assign to future shift
            $futureShift = Shift::create([
                'client_id' => $allClients->random()->id,
                'starts_at' => Carbon::now()->addDays($idx + 3)->setTime(8, 0, 0),
                'ends_at' => Carbon::now()->addDays($idx + 3)->setTime(16, 0, 0),
                'role_required' => 'carer',
                'status' => 'assigned',
            ]);

            $assign = ShiftAssignment::create([
                'shift_id' => $futureShift->id,
                'carer_id' => $carer->id,
                'status' => 'confirmed',
                'assigned_at' => Carbon::now()->subDays(1),
                'assigned_by' => $staffOps->id,
            ]);

            $p1AssignmentIds[] = $assign->id;
            $p1CarerIds[] = $carer->id;
        }
        $plantedManifest[] = [
            'id' => 'P1',
            'type' => 'expired_dbs_future_shift',
            'table' => 'shift_assignments',
            'record_ids' => $p1AssignmentIds,
            'carer_ids' => $p1CarerIds,
            'description' => '12 carers with expired DBS certificates who have confirmed future shifts.',
        ];

        // Plant P3: 6 carers with overlapping confirmed shifts
        $p3AssignmentIds = [];
        foreach ($p3Carers as $idx => $carer) {
            $baseDay = Carbon::now()->addDays($idx + 5);
            $shiftA = Shift::create([
                'client_id' => $allClients->random()->id,
                'starts_at' => (clone $baseDay)->setTime(8, 0, 0),
                'ends_at' => (clone $baseDay)->setTime(16, 0, 0),
                'role_required' => 'carer',
                'status' => 'assigned',
            ]);
            $shiftB = Shift::create([
                'client_id' => $allClients->random()->id,
                'starts_at' => (clone $baseDay)->setTime(14, 0, 0), // Overlaps by 2 hours
                'ends_at' => (clone $baseDay)->setTime(22, 0, 0),
                'role_required' => 'carer',
                'status' => 'assigned',
            ]);

            $asgnA = ShiftAssignment::create([
                'shift_id' => $shiftA->id,
                'carer_id' => $carer->id,
                'status' => 'confirmed',
                'assigned_at' => Carbon::now(),
                'assigned_by' => $staffOps->id,
            ]);
            $asgnB = ShiftAssignment::create([
                'shift_id' => $shiftB->id,
                'carer_id' => $carer->id,
                'status' => 'confirmed',
                'assigned_at' => Carbon::now(),
                'assigned_by' => $staffOps->id,
            ]);

            $p3AssignmentIds[] = $asgnA->id;
            $p3AssignmentIds[] = $asgnB->id;
        }
        $plantedManifest[] = [
            'id' => 'P3',
            'type' => 'overlapping_confirmed_shifts',
            'table' => 'shift_assignments',
            'record_ids' => $p3AssignmentIds,
            'carer_ids' => $p3Carers->pluck('id')->toArray(),
            'description' => '6 carers scheduled into overlapping shifts on the same day.',
        ];

        // Plant P4: 10 duplicate timesheets for the same assignment
        $p4SampleAssignments = ShiftAssignment::take(10)->get();
        $p4TimesheetIds = [];
        foreach ($p4SampleAssignments as $assignment) {
            $duplicateTs = Timesheet::create([
                'shift_assignment_id' => $assignment->id,
                'carer_id' => $assignment->carer_id,
                'hours_worked' => 8.00,
                'status' => 'submitted',
                'submitted_at' => Carbon::now()->subDays(1),
                'approved_at' => null,
                'approved_by' => null,
            ]);
            $p4TimesheetIds[] = $duplicateTs->id;
        }
        $plantedManifest[] = [
            'id' => 'P4',
            'type' => 'duplicate_timesheets',
            'table' => 'timesheets',
            'record_ids' => $p4TimesheetIds,
            'description' => '10 duplicate timesheets submitted for already timesheeted shift assignments.',
        ];

        // Plant P6: 5 timesheets with hours far above shift length
        $p6Timesheets = Timesheet::where('status', 'approved')->take(5)->get();
        foreach ($p6Timesheets as $ts) {
            $ts->update(['hours_worked' => 24.00]); // 24 hours on an 8 hour shift
        }
        $plantedManifest[] = [
            'id' => 'P6',
            'type' => 'excessive_timesheet_hours',
            'table' => 'timesheets',
            'record_ids' => $p6Timesheets->pluck('id')->toArray(),
            'description' => '5 timesheets claiming 24.00 hours for standard 8-hour shifts.',
        ];

        // Plant P8: 3 carers assigned during approved leave
        $p8AssignmentIds = [];
        foreach ($p8Carers as $idx => $carer) {
            $leaveStart = Carbon::now()->addDays($idx + 8);
            $leaveEnd = (clone $leaveStart)->addDays(4);

            LeaveRequest::create([
                'carer_id' => $carer->id,
                'start_date' => $leaveStart->format('Y-m-d'),
                'end_date' => $leaveEnd->format('Y-m-d'),
                'type' => 'annual',
                'status' => 'approved',
                'requested_at' => Carbon::now()->subWeeks(2),
                'decided_at' => Carbon::now()->subWeeks(1),
            ]);

            $clashingShift = Shift::create([
                'client_id' => $allClients->random()->id,
                'starts_at' => (clone $leaveStart)->addDay()->setTime(8, 0, 0),
                'ends_at' => (clone $leaveStart)->addDay()->setTime(16, 0, 0),
                'role_required' => 'carer',
                'status' => 'assigned',
            ]);

            $clashAssignment = ShiftAssignment::create([
                'shift_id' => $clashingShift->id,
                'carer_id' => $carer->id,
                'status' => 'confirmed',
                'assigned_at' => Carbon::now()->subDays(1),
                'assigned_by' => $staffOps->id,
            ]);

            $p8AssignmentIds[] = $clashAssignment->id;
        }
        $plantedManifest[] = [
            'id' => 'P8',
            'type' => 'carer_assigned_on_leave',
            'table' => 'shift_assignments',
            'record_ids' => $p8AssignmentIds,
            'carer_ids' => $p8Carers->pluck('id')->toArray(),
            'description' => '3 carers assigned to shifts during periods of approved annual leave.',
        ];

        // 9. Seed Invoices (~40 invoices, one per client for last month)
        $invoices = [];
        $periodStart = Carbon::now()->subMonths(1)->startOfMonth()->format('Y-m-d');
        $periodEnd = Carbon::now()->subMonths(1)->endOfMonth()->format('Y-m-d');

        foreach ($allClients as $client) {
            $invoice = Invoice::create([
                'client_id' => $client->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'total' => 0.00,
                'status' => 'sent',
            ]);
            $invoices[] = $invoice;
        }

        // Attach invoice lines from approved timesheets
        $approvedTimesheets = Timesheet::where('status', 'approved')->take(200)->get();
        $invoiceIndex = 0;
        foreach ($approvedTimesheets as $ts) {
            $targetInvoice = $invoices[$invoiceIndex % count($invoices)];
            $hours = (float) $ts->hours_worked;
            $rate = 21.00;
            $amount = $hours * $rate;

            InvoiceLine::create([
                'invoice_id' => $targetInvoice->id,
                'timesheet_id' => $ts->id,
                'hours' => $hours,
                'rate' => $rate,
                'amount' => $amount,
            ]);

            $targetInvoice->increment('total', $amount);
            $invoiceIndex++;
        }

        // Plant P10: 5 invoices whose total does not match their lines
        $p10Invoices = Invoice::where('total', '>', 0)->take(5)->get();
        foreach ($p10Invoices as $inv) {
            $inv->update(['total' => $inv->total + 500.00]); // Discrepancy of £500
        }
        $plantedManifest[] = [
            'id' => 'P10',
            'type' => 'invoice_total_mismatch',
            'table' => 'invoices',
            'record_ids' => $p10Invoices->pluck('id')->toArray(),
            'description' => '5 invoices where header total is £500 higher than the sum of invoice lines.',
        ];

        // 10. Write Ground Truth Manifest to file
        $manifestPath = '/var/www/seed-data/planted-problems.json';
        file_put_contents($manifestPath, json_encode($plantedManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
