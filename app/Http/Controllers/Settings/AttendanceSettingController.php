<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AttendanceMode;
use App\Http\Controllers\Controller;
use App\Models\WorkShift;
use App\Services\AuditLogger;
use App\Services\WorkingHoursCalculationService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingController extends Controller
{
    private const array FIELDS = [
        'attendance_mode', 'default_work_shift_id', 'male_work_shift_id', 'female_work_shift_id',
        'other_work_shift_id', 'grace_minutes', 'lates_per_half_day', 'short_hours_tolerance_minutes',
    ];

    public function edit(): Response
    {
        $settings = $this->tenant()->settings();

        return Inertia::render('company-settings/Attendance', [
            'settings' => [
                ...$settings->only(self::FIELDS),
                'attendance_mode' => $settings->attendance_mode->value,
            ],
            'modes' => AttendanceMode::options(),
            'shifts' => WorkShift::query()->where('is_active', true)->orderBy('name')->get()->map(fn (WorkShift $shift): array => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
                'required_minutes' => $shift->required_minutes,
            ]),
        ]);
    }

    public function update(Request $request, WorkingHoursCalculationService $hours, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'attendance_mode' => ['required', Rule::enum(AttendanceMode::class)],
            'default_work_shift_id' => ['required', 'integer', TenantRule::exists('work_shifts')],
            'male_work_shift_id' => ['nullable', 'integer', TenantRule::exists('work_shifts')],
            'female_work_shift_id' => ['nullable', 'integer', TenantRule::exists('work_shifts')],
            'other_work_shift_id' => ['nullable', 'integer', TenantRule::exists('work_shifts')],
            'grace_minutes' => ['required', 'integer', 'between:0,240'],
            'lates_per_half_day' => ['required', 'integer', 'between:0,31'],
            'short_hours_tolerance_minutes' => ['required', 'integer', 'between:0,240'],
        ], [], [
            'default_work_shift_id' => 'company default shift',
            'male_work_shift_id' => 'male staff shift',
            'female_work_shift_id' => 'female staff shift',
            'other_work_shift_id' => 'other staff shift',
        ]);

        $settings = $this->tenant()->settings();
        $original = $settings->getAttributes();
        $settings->update($validated);

        $this->tenant()->flushSettings();
        $hours->flush();

        [$old, $new] = $audit->diff($settings, $original);

        if ($new !== []) {
            $audit->log('settings.attendance_updated', $settings, $old, $new, 'Attendance settings updated');
        }

        $this->toast('Attendance settings saved.');

        return back();
    }
}
