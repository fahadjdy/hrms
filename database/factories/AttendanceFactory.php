<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state: a full present day on an 8-hour shift.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => '2026-09-01',
            'status' => AttendanceStatus::Present,
            'check_in' => '09:00',
            'check_out' => '18:00',
            'required_minutes' => 480,
            'worked_minutes' => 480,
            'short_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'source' => Attendance::SOURCE_MANUAL,
        ];
    }

    /**
     * A day with the given status and no hours worked.
     */
    public function status(AttendanceStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'check_in' => null,
            'check_out' => null,
            'required_minutes' => $status->isNonWorkingDay() ? 0 : 480,
            'worked_minutes' => 0,
        ]);
    }
}
