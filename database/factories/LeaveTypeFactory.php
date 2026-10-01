<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Leave',
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'is_paid' => true,
            'annual_allowance' => 12,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that leave of this type is not paid.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => false,
            'annual_allowance' => 0,
        ]);
    }
}
