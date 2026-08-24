<?php

namespace App\Services\Audit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OwenItAuditWriter
{
    public function isEnabled(): bool
    {
        if (!filter_var(env('AUDITING_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return Schema::hasTable('audits');
    }

    /**
     * Insert one Owen It–compatible row into `audits` (same shape as owen-it/laravel-auditing).
     *
     * @param  'created'|'updated'|'deleted'|'restored'  $event
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(string $event, string $auditableType, int $auditableId, ?array $oldValues, ?array $newValues, ?string $tags = null): void
    {
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $user = Auth::user();
            $userType = $user ? get_class($user) : null;
            $userId = $user ? $user->getAuthIdentifier() : null;
            $now = Carbon::now();

            $request = request();
            $url = $request ? $request->fullUrl() : null;
            $ip = $request ? $request->ip() : null;
            $ua = $request ? substr((string) $request->userAgent(), 0, 1023) : null;

            $row = [
                'user_type' => $userType,
                'user_id' => $userId,
                'event' => $event,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'old_values' => $this->encodeAuditValues($oldValues),
                'new_values' => $this->encodeAuditValues($newValues),
                'url' => $url,
                'ip_address' => $ip,
                'user_agent' => $ua,
                'tags' => $tags,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            DB::table('audits')->insert($row);
        } catch (\Throwable $e) {
            Log::warning('OwenItAuditWriter: audit record failed', [
                'message' => $e->getMessage(),
                'event' => $event,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     */
    private function encodeAuditValues(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        $flags = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;
        if (\defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $flags |= \JSON_PARTIAL_OUTPUT_ON_ERROR;
        }

        $json = json_encode($values, $flags);

        return $json === false ? '{"_audit_json_error":true}' : $json;
    }
}
