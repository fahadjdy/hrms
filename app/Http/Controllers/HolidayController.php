<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Services\AuditLogger;
use App\Services\WorkingCalendarService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) ($validated['year'] ?? $this->tenant()->today()->year);

        return Inertia::render('attendance/Holidays', [
            'year' => $year,
            'today' => $this->tenant()->today()->toDateString(),
            'holidays' => Holiday::query()
                ->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])
                ->orderBy('date')
                ->get()
                ->map(fn (Holiday $holiday): array => [
                    ...$holiday->only(['id', 'name', 'type', 'description']),
                    'type_label' => Holiday::TYPES[$holiday->type] ?? $holiday->type,
                    'date' => $holiday->date->toDateString(),
                    'weekday' => $holiday->date->format('l'),
                ]),
            'types' => collect(Holiday::TYPES)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function store(Request $request, WorkingCalendarService $calendar, AuditLogger $audit): RedirectResponse
    {
        $holiday = Holiday::query()->create($this->validated($request));
        $calendar->flush();

        $audit->log('holiday.created', $holiday, null, ['name' => $holiday->name, 'date' => $holiday->date->toDateString()], "Holiday {$holiday->name} added");
        $this->toast('Holiday added.');

        return back();
    }

    public function update(Request $request, Holiday $holiday, WorkingCalendarService $calendar, AuditLogger $audit): RedirectResponse
    {
        $old = ['name' => $holiday->name, 'date' => $holiday->date->toDateString()];
        $holiday->update($this->validated($request, $holiday));
        $calendar->flush();

        $audit->log('holiday.updated', $holiday, $old, ['name' => $holiday->name, 'date' => $holiday->date->toDateString()], "Holiday {$holiday->name} updated");
        $this->toast('Holiday updated.');

        return back();
    }

    public function destroy(Holiday $holiday, WorkingCalendarService $calendar, AuditLogger $audit): RedirectResponse
    {
        $audit->log('holiday.deleted', $holiday, ['name' => $holiday->name, 'date' => $holiday->date->toDateString()], null, "Holiday {$holiday->name} deleted");
        $holiday->delete();
        $calendar->flush();

        $this->toast('Holiday deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Holiday $holiday = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d', TenantRule::unique('holidays', 'date')->ignore($holiday?->id)],
            'type' => ['required', Rule::in(array_keys(Holiday::TYPES))],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'date.unique' => 'A holiday already exists on this date.',
        ]);
    }
}
