<?php

namespace Database\Factories;

use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkShift>
 */
class WorkShiftFactory extends Factory
{
    /**
     * Define the model's default state: 09:00 to 18:00 with a one-hour break.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'required_minutes' => 480,
            'break_minutes' => 60,
            'is_active' => true,
        ];
    }

    /**
     * A shift with the given start, end and required hours. The break is
     * whatever part of the span is not required working time.
     */
    public function timing(string $start, string $end, int $requiredMinutes): static
    {
        return $this->state(function (array $attributes) use ($start, $end, $requiredMinutes) {
            $span = WorkShift::timeToMinutes($end) - WorkShift::timeToMinutes($start);

            return [
                'start_time' => $start,
                'end_time' => $end,
                'required_minutes' => $requiredMinutes,
                'break_minutes' => max(0, $span - $requiredMinutes),
            ];
        });
    }
}
