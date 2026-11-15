<?php

namespace Database\Factories;

use App\Models\ShiftAssignment;
use App\Models\Shift;
use App\Models\Carer;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftAssignmentFactory extends Factory
{
    protected $model = ShiftAssignment::class;

    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'carer_id' => Carer::factory(),
            'status' => 'confirmed',
            'assigned_at' => now(),
            'assigned_by' => StaffUser::factory(),
        ];
    }
}

