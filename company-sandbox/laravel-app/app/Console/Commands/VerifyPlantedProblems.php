<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyPlantedProblems extends Command
{
    protected $signature = 'sandbox:verify-manifest {--path=/var/www/seed-data/planted-problems.json}';
    protected $description = 'Verify all P1-P10 planted problems in planted-problems.json match live MySQL rows';

    public function handle(): int
    {
        $path = (string) $this->option('path');
        if (!file_exists($path)) {
            $this->error("Manifest not found at {$path}");
            return self::FAILURE;
        }

        $entries = json_decode((string) file_get_contents($path), true);
        if (!is_array($entries)) {
            $this->error('Invalid JSON manifest');
            return self::FAILURE;
        }

        $byId = [];
        foreach ($entries as $entry) {
            $byId[$entry['id']] = $entry;
        }

        $failures = 0;

        // P1: Expired DBS with future confirmed shift assignments
        $p1Rows = DB::select("
            SELECT DISTINCT sa.id, sa.carer_id
            FROM shift_assignments sa
            JOIN shifts s ON sa.shift_id = s.id
            JOIN compliance_checks cc ON cc.carer_id = sa.carer_id AND cc.type = 'dbs'
            WHERE sa.status = 'confirmed' AND cc.expires_on < CURDATE() AND s.starts_at > NOW()
        ");
        $p1CarersDb = array_values(array_unique(array_map(fn ($r) => (int) $r->carer_id, $p1Rows)));
        $p1ManifestCarers = array_map('intval', $byId['P1']['carer_ids'] ?? []);
        $p1Ok = empty(array_diff($p1ManifestCarers, $p1CarersDb)) && count($p1ManifestCarers) === 12;
        $this->line(sprintf('[%s] P1 expired_dbs_future_shift: manifest=%d carers, db=%d carers', $p1Ok ? 'PASS' : 'FAIL', count($p1ManifestCarers), count($p1CarersDb)));
        if (!$p1Ok) $failures++;

        // P2: RTW expiring within 30 days
        $p2Count = DB::table('compliance_checks')
            ->whereIn('id', $byId['P2']['record_ids'] ?? [])
            ->where('type', 'right_to_work')
            ->whereBetween('expires_on', [now()->format('Y-m-d'), now()->addDays(30)->format('Y-m-d')])
            ->count();
        $p2Ok = $p2Count === 8;
        $this->line(sprintf('[%s] P2 right_to_work_expiring_soon: verified=%d/8', $p2Ok ? 'PASS' : 'FAIL', $p2Count));
        if (!$p2Ok) $failures++;

        // P3: Overlapping confirmed shifts
        $p3DbCarers = DB::select("
            SELECT DISTINCT sa1.carer_id
            FROM shift_assignments sa1
            JOIN shifts s1 ON sa1.shift_id = s1.id
            JOIN shift_assignments sa2 ON sa1.carer_id = sa2.carer_id AND sa1.id < sa2.id
            JOIN shifts s2 ON sa2.shift_id = s2.id
            WHERE sa1.status = 'confirmed' AND sa2.status = 'confirmed'
              AND s1.starts_at < s2.ends_at AND s1.ends_at > s2.starts_at
        ");
        $p3CarerIds = array_map(fn ($r) => (int) $r->carer_id, $p3DbCarers);
        $p3Manifest = array_map('intval', $byId['P3']['carer_ids'] ?? []);
        $p3Ok = empty(array_diff($p3Manifest, $p3CarerIds)) && count($p3Manifest) === 6;
        $this->line(sprintf('[%s] P3 overlapping_confirmed_shifts: manifest=%d carers, db_overlapping=%d', $p3Ok ? 'PASS' : 'FAIL', count($p3Manifest), count($p3CarerIds)));
        if (!$p3Ok) $failures++;

        // P4: Duplicate timesheets for the same assignment
        $p4Db = DB::table('timesheets')
            ->select('shift_assignment_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('shift_assignment_id')
            ->having('cnt', '>', 1)
            ->count();
        $p4Ok = $p4Db >= 10 && count($byId['P4']['record_ids'] ?? []) === 10;
        $this->line(sprintf('[%s] P4 duplicate_timesheets: duplicate_assignments=%d, manifest_records=%d', $p4Ok ? 'PASS' : 'FAIL', $p4Db, count($byId['P4']['record_ids'] ?? [])));
        if (!$p4Ok) $failures++;

        // P5: Incomplete candidate CVs
        $p5Ids = $byId['P5']['record_ids'] ?? [];
        $p5Count = DB::table('candidates')
            ->whereIn('id', $p5Ids)
            ->where(function ($q) {
                $q->where('cv_text', '')->orWhere('cv_text', 'not like', '%Adult social care%');
            })->count();
        $p5Ok = count($p5Ids) === 15 && $p5Count === 15;
        $this->line(sprintf('[%s] P5 incomplete_candidate_cvs: verified=%d/15', $p5Ok ? 'PASS' : 'FAIL', $p5Count));
        if (!$p5Ok) $failures++;

        // P6: Timesheets with hours far above shift length
        $p6Count = DB::table('timesheets as t')
            ->join('shift_assignments as sa', 't.shift_assignment_id', '=', 'sa.id')
            ->join('shifts as s', 'sa.shift_id', '=', 's.id')
            ->whereIn('t.id', $byId['P6']['record_ids'] ?? [])
            ->whereRaw('t.hours_worked > TIMESTAMPDIFF(HOUR, s.starts_at, s.ends_at) + 1')
            ->count();
        $p6Ok = $p6Count === 5;
        $this->line(sprintf('[%s] P6 excessive_timesheet_hours: verified=%d/5', $p6Ok ? 'PASS' : 'FAIL', $p6Count));
        if (!$p6Ok) $failures++;

        // P7: Open shifts in the past
        $p7Count = DB::table('shifts')
            ->whereIn('id', $byId['P7']['record_ids'] ?? [])
            ->where('status', 'open')
            ->where('starts_at', '<', now())
            ->count();
        $p7Ok = $p7Count === 4;
        $this->line(sprintf('[%s] P7 past_open_shifts: verified=%d/4', $p7Ok ? 'PASS' : 'FAIL', $p7Count));
        if (!$p7Ok) $failures++;

        // P8: Carers assigned during approved leave
        $p8Count = DB::table('shift_assignments as sa')
            ->join('shifts as s', 'sa.shift_id', '=', 's.id')
            ->join('leave_requests as lr', 'lr.carer_id', '=', 'sa.carer_id')
            ->whereIn('sa.id', $byId['P8']['record_ids'] ?? [])
            ->where('sa.status', 'confirmed')
            ->where('lr.status', 'approved')
            ->whereRaw('DATE(s.starts_at) BETWEEN lr.start_date AND lr.end_date')
            ->count();
        $p8Ok = $p8Count === 3;
        $this->line(sprintf('[%s] P8 carer_assigned_on_leave: verified=%d/3', $p8Ok ? 'PASS' : 'FAIL', $p8Count));
        if (!$p8Ok) $failures++;

        // P9: Duplicate client records (2 pairs = 4 IDs)
        $p9Ids = $byId['P9']['record_ids'] ?? [];
        $p9Count = DB::table('clients')->whereIn('id', $p9Ids)->count();
        $p9Ok = count($p9Ids) === 4 && $p9Count === 4;
        $this->line(sprintf('[%s] P9 duplicate_client_records: verified=%d/4', $p9Ok ? 'PASS' : 'FAIL', $p9Count));
        if (!$p9Ok) $failures++;

        // P10: Invoices whose total does not match their lines
        $p10Count = DB::table('invoices as i')
            ->whereIn('i.id', $byId['P10']['record_ids'] ?? [])
            ->whereRaw('ABS(i.total - (SELECT COALESCE(SUM(il.amount), 0) FROM invoice_lines il WHERE il.invoice_id = i.id)) > 0.01')
            ->count();
        $p10Ok = $p10Count === 5;
        $this->line(sprintf('[%s] P10 invoice_total_mismatch: verified=%d/5', $p10Ok ? 'PASS' : 'FAIL', $p10Count));
        if (!$p10Ok) $failures++;

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}

