<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('employees/Departments', [
            'departments' => Department::query()
                ->withCount(['employees' => fn ($query) => $query->current()])
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department): array => [
                    ...$department->only(['id', 'name', 'description', 'is_active']),
                    'employees_count' => $department->employees_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Department::query()->create($this->validated($request));

        $this->toast('Department added.');

        return back();
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request, $department));

        $this->toast('Department updated.');

        return back();
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            throw ValidationException::withMessages([
                'department' => 'This department still has employees. Move them first, or mark the department inactive.',
            ]);
        }

        $department->delete();

        $this->toast('Department deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', TenantRule::unique('departments', 'name')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
