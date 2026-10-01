<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee documents are stored on the private disk and only ever served
 * through this controller, after the tenant and permission checks.
 */
class EmployeeDocumentController extends Controller
{
    public const array TYPES = ['ID Proof', 'Address Proof', 'Offer Letter', 'Contract', 'Certificate', 'Bank Details', 'Other'];

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'employee_id', 'type']);

        $documents = EmployeeDocument::query()
            ->when($filters['employee_id'] ?? null, fn ($query, $id) => $query->where('employee_id', $id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhereHas('employee', fn ($employee) => $employee->search($search))))
            ->with(['employee:id,first_name,last_name,employee_code', 'uploader:id,name'])
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (EmployeeDocument $document): array => [
                ...$document->only(['id', 'title', 'type', 'original_name', 'size']),
                'employee' => ['id' => $document->employee->id, 'name' => $document->employee->full_name, 'code' => $document->employee->employee_code],
                'uploaded_by' => $document->uploader?->name,
                'created_at' => $document->created_at?->toDateString(),
            ]);

        return Inertia::render('employees/Documents', [
            'documents' => $documents,
            'filters' => $filters,
            'types' => self::TYPES,
            'employees' => Employee::query()
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => "{$employee->full_name} ({$employee->employee_code})"]),
        ]);
    }

    public function store(Request $request, Employee $employee, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:'.config('hrms.document_max_kilobytes')],
        ]);

        $file = $request->file('file');

        $document = EmployeeDocument::query()->create([
            'employee_id' => $employee->id,
            'title' => $validated['title'],
            'type' => $validated['type'] ?? null,
            'file_path' => $file->store("employee-documents/{$employee->company_id}/{$employee->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $audit->log('document.uploaded', $document, null, ['title' => $document->title], "Document \"{$document->title}\" uploaded for {$employee->full_name}", $employee->id);

        $this->toast('Document uploaded.');

        return back();
    }

    public function show(EmployeeDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroy(EmployeeDocument $document, AuditLogger $audit): RedirectResponse
    {
        $audit->log('document.deleted', $document, ['title' => $document->title], null, "Document \"{$document->title}\" deleted", $document->employee_id);

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        $this->toast('Document deleted.');

        return back();
    }
}
