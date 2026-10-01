<?php

namespace Database\Factories;

use App\Enums\DesignationChangeType;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDesignationChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDesignationChange>
 */
class EmployeeDesignationChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'from_designation_id' => null,
            'to_designation_id' => Designation::factory(),
            'type' => DesignationChangeType::Initial,
            'effective_date' => '2025-01-01',
            'reason' => null,
        ];
    }
}
