<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Record an auditable action for the current company.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(
        string $action,
        Model $entity,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
        ?int $employeeId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => auth()->id(),
            'employee_id' => $employeeId,
            'action' => $action,
            'entity_type' => Str::snake(class_basename($entity)),
            'entity_id' => $entity->getKey(),
            'description' => $description,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * Old and new values for only the attributes that changed on a saved model.
     *
     * @param  array<string, mixed>  $original
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function diff(Model $model, array $original): array
    {
        $changes = array_diff_key($model->getChanges(), array_flip(['updated_at']));
        $old = array_intersect_key($original, $changes);

        return [$old, $changes];
    }
}
