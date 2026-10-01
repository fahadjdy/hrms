<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DesignationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('employees/Designations', [
            'designations' => Designation::query()
                ->withCount(['employees' => fn ($query) => $query->current()])
                ->orderBy('name')
                ->get()
                ->map(fn (Designation $designation): array => [
                    ...$designation->only(['id', 'name', 'description', 'is_active']),
                    'employees_count' => $designation->employees_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Designation::query()->create($this->validated($request));

        $this->toast('Designation added.');

        return back();
    }

    public function update(Request $request, Designation $designation): RedirectResponse
    {
        $designation->update($this->validated($request, $designation));

        $this->toast('Designation updated.');

        return back();
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        if ($designation->employees()->exists()) {
            throw ValidationException::withMessages([
                'designation' => 'This designation is still assigned to employees. Reassign them first, or mark it inactive.',
            ]);
        }

        $designation->delete();

        $this->toast('Designation deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Designation $designation = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', TenantRule::unique('designations', 'name')->ignore($designation?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
