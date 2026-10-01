<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Enums\ExitType;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => 'EMP-'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['male', 'female']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('98########'),
            'joining_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'status' => EmployeeStatus::Active,
        ];
    }

    /**
     * Indicate that the employee has left the company.
     */
    public function past(string $lastWorkingDate = '2026-08-31'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::Past,
            'exit_date' => $lastWorkingDate,
            'last_working_date' => $lastWorkingDate,
            'exit_type' => ExitType::Resignation,
        ]);
    }
}
