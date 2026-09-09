<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class AuditService
{
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'token',
        'authorization',
        'api_key',
        'secret_key',
        'server_key',
        'client_key',
        'card_number',
    ];

    public function log(string $activity, string $module, string $action, ?Model $entity = null, array $old = [], array $new = [], string $status = 'success', ?string $description = null): void
    {
        $request = request();
        $user = $request?->user();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_role' => $user?->getRoleNames()->implode(','),
            'activity' => $activity,
            'module' => $module,
            'action' => $action,
            'http_method' => $request?->method(),
            'endpoint' => $request?->path(),
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'old_data' => $this->sanitize($old),
            'new_data' => $this->sanitize($new),
            'changed_fields' => $this->changedFields($old, $new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->attributes->get('request_id'),
            'status' => $status,
            'description' => $description,
        ]);
    }

    public function logRequest(Request $request, string $status, ?string $description = null): void
    {
        $module = $request->segment(3) ?: 'api';
        $action = strtolower($request->method());

        $this->log("{$action}_request", $module, $action, null, [], $request->except($this->sensitiveKeys), $status, $description);
    }

    public function sanitize(array $data): array
    {
        return collect($data)->mapWithKeys(function ($value, $key) {
            $keyString = strtolower((string) $key);
            $isSensitive = collect($this->sensitiveKeys)->contains(fn ($sensitive) => str_contains($keyString, $sensitive));

            return [$key => $isSensitive ? '[masked]' : $this->redactFiles($value)];
        })->all();
    }

    // Uploaded files carry a file-handle resource that can't be JSON-encoded
    // for storage in the audit log, so replace them with their filename.
    private function redactFiles(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return '[file:'.$value->getClientOriginalName().']';
        }
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => $this->redactFiles($item))->all();
        }

        return $value;
    }

    private function changedFields(array $old, array $new): array
    {
        return collect($new)
            ->filter(fn ($value, $key) => Arr::get($old, $key) !== $value)
            ->keys()
            ->values()
            ->all();
    }
}
