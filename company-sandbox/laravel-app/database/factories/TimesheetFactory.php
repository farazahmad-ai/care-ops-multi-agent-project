<?php

namespace Database\Factories;

use App\Models\Timesheet;
use App\Models\ShiftAssignment;
use App\Models\Carer;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimesheetFactory extends Factory
{
    protected $model = Timesheet::class;

    public function definition(): array
    {
        return [
            'shift_assignment_id' => ShiftAssignment::factory(),
            'carer_id' => Carer::factory(),
            'hours_worked' => 8.00,
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by' => StaffUser::factory(),
        ];
    }
}

