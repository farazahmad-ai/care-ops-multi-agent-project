<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. staff_users (internal staff who approve actions and shifts)
        Schema::create('staff_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('department'); // recruitment, hr, operations, finance
            $table->timestamps();
        });

        // 2. candidates (job applicants)
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('postcode');
            $table->text('cv_text')->nullable();
            $table->string('status')->default('applied'); // applied, screening, interview, offered, hired, rejected
            $table->string('source')->default('website'); // website, agency, referral, job_board
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamps();

            $table->index('status');
        });

        // 3. interviews
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('interviewer');
            $table->string('outcome')->default('pending'); // pending, pass, fail, no_show
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. carers (active or past care staff)
        Schema::create('carers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('postcode');
            $table->string('role')->default('carer'); // carer, senior_carer
            $table->string('status')->default('active'); // active, suspended, left
            $table->timestamp('hired_at')->useCurrent();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'role']);
        });

        // 5. compliance_checks (DBS, right to work, training)
        Schema::create('compliance_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carer_id')->nullable()->constrained('carers')->cascadeOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->cascadeOnDelete();
            $table->string('type'); // dbs, right_to_work, moving_handling, safeguarding
            $table->date('issued_on');
            $table->date('expires_on');
            $table->string('status')->default('valid'); // valid, expiring_soon, expired
            $table->timestamps();

            $table->index(['type', 'expires_on']);
        });

        // 6. clients (care homes and domiciliary clients)
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('care_home'); // care_home, domiciliary
            $table->string('address_line');
            $table->string('postcode');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();

            $table->index('status');
        });

        // 7. shifts (work requirements at client locations)
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('role_required')->default('carer'); // carer, senior_carer
            $table->string('status')->default('open'); // open, assigned, completed, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'status']);
        });

        // 8. shift_assignments (allocating carer to a shift)
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('carer_id')->constrained('carers')->cascadeOnDelete();
            $table->string('status')->default('confirmed'); // confirmed, cancelled, no_show
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();

            // Note: Deliberately no unique constraint on (shift_id, carer_id) or overlap constraint
            // to allow legacy data flaws (P3: overlapping shifts) to exist as legacy data.
        });

        // 9. leave_requests (holidays, sick leave)
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carer_id')->constrained('carers')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type')->default('annual'); // annual, sick, unpaid
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['carer_id', 'status']);
        });

        // 10. pay_rates (charge and pay rates per role)
        Schema::create('pay_rates', function (Blueprint $table) {
            $table->id();
            $table->string('role'); // carer, senior_carer
            $table->decimal('hourly_pay', 8, 2);
            $table->decimal('hourly_charge', 8, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        // 11. timesheets (hours logged for shifts)
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_assignment_id')->constrained('shift_assignments')->cascadeOnDelete();
            $table->foreignId('carer_id')->constrained('carers')->cascadeOnDelete();
            $table->decimal('hours_worked', 5, 2);
            $table->string('status')->default('submitted'); // submitted, approved, rejected
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();

            // Note: Deliberately no unique constraint on shift_assignment_id (legacy allows duplicate timesheets P4)
            $table->index(['carer_id', 'status']);
        });

        // 12. invoices (billing to clients)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total', 10, 2)->default(0.00);
            $table->string('status')->default('draft'); // draft, sent, paid
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        // 13. invoice_lines (timesheet details on an invoice)
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('timesheet_id')->nullable()->constrained('timesheets')->nullOnDelete();
            $table->decimal('hours', 5, 2);
            $table->decimal('rate', 8, 2);
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('pay_rates');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('compliance_checks');
        Schema::dropIfExists('carers');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('staff_users');
    }
};

