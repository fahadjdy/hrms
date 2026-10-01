<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAttendance;
use App\Services\AttendanceCalculationService;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceGenerationController extends Controller
{
    /**
     * Generate attendance for a date range. Short ranges run immediately;
     * long ranges are queued.
     */
    public function store(Request $request, AttendanceCalculationService $attendance, AuditLogger $audit): RedirectResponse
    {
        $today = $this->tenant()->today()->toDateString();

        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:'.$today],
        ]);

        $start = CarbonImmutable::parse($validated['start_date']);
        $end = CarbonImmutable::parse($validated['end_date']);
        $company = $this->company();

        $audit->log('attendance.generated', $company, null, $validated, "Attendance generated from {$validated['start_date']} to {$validated['end_date']}");

        if ($start->diffInDays($end) + 1 > config('hrms.sync_attendance_day_limit')) {
            GenerateAttendance::dispatch($company->id, $validated['start_date'], $validated['end_date']);

            $this->toast('Attendance generation has been queued and will finish shortly.', 'info');

            return back();
        }

        $created = $attendance->generateForRange($start, $end);

        $this->toast("{$created} attendance record(s) generated.");

        return back();
    }
}
