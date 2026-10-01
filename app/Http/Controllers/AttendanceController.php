<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceCalculationService;
use App\Services\WorkingCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Daily attendance: the company-wide picture for one date.
     */
    public function index(Request $request, AttendanceCalculationService $attendance, WorkingCalendarService $calendar): Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $today = $this->tenant()->today();
        $date = isset($validated['date']) ? CarbonImmutable::parse($validated['date']) : $today;

        // Days off (and present days in automatic mode) are stored on first view.
        $attendance->generateForDate($date);

        $status = $validated['status'] ?? null;

        $employees = $this->employedOn($date)
            ->search($validated['search'] ?? null)
            ->when($validated['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->when($status === 'unmarked', fn ($query) => $query->whereDoesntHave(
                'attendances',
                fn ($attendances) => $attendances->where('date', $date->toDateString()),
            ))
            ->when($status !== null && $status !== 'unmarked', fn ($query) => $query->whereHas(
                'attendances',
                fn ($attendances) => $attendances->where('date', $date->toDateString())->where('status', $status),
            ))
            ->with(['department:id,name', 'designation:id,name', 'shiftAssignments'])
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $days = $attendance->resolveDate($employees->getCollection(), $date);

        return Inertia::render('attendance/Daily', [
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'isFuture' => $date->gt($today),
            'holidayName' => $calendar->holidayName($date),
            'isWeeklyOff' => $calendar->isWeeklyOff($date),
            'mode' => $this->tenant()->settings()->attendance_mode->value,
            'kpis' => $this->kpis($date),
            'employees' => $employees->through(fn (Employee $employee): array => [
                ...$employee->toBrief(),
                'day' => $days[$employee->id],
            ]),
            'filters' => [
                'search' => $validated['search'] ?? '',
                'department_id' => $validated['department_id'] ?? null,
                'status' => $status,
            ],
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
        ]);
    }

    /**
     * Pick an employee to open their attendance calendar.
     */
    public function calendar(Request $request): Response
    {
        $filters = $request->only(['search', 'department_id']);

        return Inertia::render('attendance/Calendar', [
            'employees' => Employee::query()
                ->current()
                ->search($filters['search'] ?? null)
                ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
                ->with(['department:id,name', 'designation:id,name'])
                ->orderBy('first_name')
                ->orderBy('id')
                ->paginate(24)
                ->withQueryString()
                ->through(fn (Employee $employee): array => $employee->toBrief()),
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'month' => $this->tenant()->today()->format('Y-m'),
        ]);
    }

    /**
     * @return list<array{value: string, label: string, code: string}>
     */
    public static function statusOptions(): array
    {
        return array_map(
            fn (AttendanceStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'code' => $status->shortCode(),
            ],
            AttendanceStatus::cases(),
        );
    }

    /**
     * Counts for the date, straight from the stored records.
     *
     * @return array<string, int>
     */
    private function kpis(CarbonImmutable $date): array
    {
        $total = $this->employedOn($date)->count();

        $records = Attendance::query()
            ->where('date', $date->toDateString())
            ->whereIn('employee_id', $this->employedOn($date)->select('id'))
            ->selectRaw('status, count(*) as total, sum(case when overtime_minutes > 0 then 1 else 0 end) as with_overtime, sum(case when short_minutes > 0 then 1 else 0 end) as with_short')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->keyBy('status');

        $count = fn (AttendanceStatus ...$statuses): int => (int) collect($statuses)
            ->sum(fn (AttendanceStatus $status): int => (int) ($records[$status->value]->total ?? 0));

        return [
            'total' => $total,
            'present' => $count(AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::ShortHours, AttendanceStatus::WorkFromHome, AttendanceStatus::HalfDay),
            'absent' => $count(AttendanceStatus::Absent),
            'on_leave' => $count(AttendanceStatus::PaidLeave, AttendanceStatus::UnpaidLeave),
            'wfh' => $count(AttendanceStatus::WorkFromHome),
            'late' => $count(AttendanceStatus::Late),
            'short_hours' => (int) $records->sum('with_short'),
            'overtime' => (int) $records->sum('with_overtime'),
            'unmarked' => max(0, $total - (int) $records->sum('total')),
        ];
    }

    /**
     * Current employees who had joined by the given date.
     *
     * @return Builder<Employee>
     */
    private function employedOn(CarbonImmutable $date): Builder
    {
        return Employee::query()->current()->where('joining_date', '<=', $date->toDateString());
    }
}
