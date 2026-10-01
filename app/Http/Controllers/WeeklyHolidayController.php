<?php

namespace App\Http\Controllers;

use App\Models\WeeklyHoliday;
use App\Services\AuditLogger;
use App\Services\WorkingCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyHolidayController extends Controller
{
    public function edit(WorkingCalendarService $calendar): Response
    {
        $today = $this->tenant()->today();

        return Inertia::render('attendance/WeeklyHolidays', [
            'days' => $calendar->weeklyOffDays(),
            'workingDaysThisMonth' => $calendar->workingDaysBetween($today->startOfMonth(), $today->endOfMonth()->startOfDay()),
            'monthLabel' => $today->format('F Y'),
        ]);
    }

    /**
     * Replace the company's weekly off days. Attendance, working days and
     * payroll all follow this setting.
     */
    public function update(Request $request, WorkingCalendarService $calendar, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['present', 'array', 'max:6'],
            'days.*' => ['integer', 'between:0,6', 'distinct'],
        ], [
            'days.max' => 'At least one day of the week must be a working day.',
        ]);

        $old = $calendar->weeklyOffDays();
        $days = array_map(intval(...), $validated['days']);
        sort($days);

        DB::transaction(function () use ($days): void {
            WeeklyHoliday::query()->delete();

            foreach ($days as $day) {
                WeeklyHoliday::query()->create(['day_of_week' => $day]);
            }
        });

        $calendar->flush();

        $audit->log('weekly_holidays.updated', $this->company(), ['days' => $old], ['days' => $days], 'Weekly holidays updated');
        $this->toast('Weekly holidays saved.');

        return back();
    }
}
