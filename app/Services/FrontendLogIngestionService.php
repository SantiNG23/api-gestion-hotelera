<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FrontendObservabilityLog;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FrontendLogIngestionService
{
    private const MAX_SANITIZATION_DEPTH = 6;
    private const REQUEST_ID_MAX_LENGTH = 100;

    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'authorization',
        'token',
        'password',
        'access_token',
        'refresh_token',
        'cookie',
        'secret',
        'api_key',
    ];

    public function ingest(array $data, Request $request): FrontendObservabilityLog
    {
        try {
            $occurredAt = Carbon::parse($data['timestamp'])->utc();
        } catch (InvalidFormatException) {
            throw ValidationException::withMessages([
                'timestamp' => ['El campo timestamp debe ser una fecha ISO8601 válida.'],
            ]);
        }

        $ingestedAt = now()->utc();

        $record = FrontendObservabilityLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $request->user()?->tenant_id,
            'user_id' => $request->user()?->id,
            'level' => $data['level'],
            'scope' => is_string($data['scope']) ? trim($data['scope']) : $data['scope'],
            'context' => $this->normalizeContext($data['context'] ?? null),
            'event_name' => isset($data['event_name']) && is_string($data['event_name'])
                ? trim($data['event_name'])
                : null,
            'meta' => $this->sanitizePayload($data['meta'] ?? null),
            'args' => $this->sanitizePayload($data['args'] ?? null),
            'occurred_at' => $occurredAt,
            'ingested_at' => $ingestedAt,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $this->extractRequestId($request),
        ]);

        $this->incrementIngestionMetrics($record->level);

        return $record;
    }

    private function normalizeContext(mixed $context): ?array
    {
        if (! is_array($context)) {
            return null;
        }

        return array_values(array_map(static function ($item) {
            return is_string($item) ? trim($item) : $item;
        }, $context));
    }

    private function sanitizePayload(mixed $value, int $depth = 0, ?string $key = null): mixed
    {
        if ($depth > self::MAX_SANITIZATION_DEPTH) {
            return '[MAX_DEPTH_REACHED]';
        }

        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $childKey => $childValue) {
                $normalizedKey = is_string($childKey) ? $childKey : null;
                $sanitized[$childKey] = $this->sanitizePayload($childValue, $depth + 1, $normalizedKey);
            }

            return $sanitized;
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        return in_array($normalized, self::SENSITIVE_KEYS, true);
    }

    private function extractRequestId(Request $request): ?string
    {
        $requestId = $request->header('X-Request-Id')
            ?? $request->header('X-Request-ID')
            ?? $request->attributes->get('request_id');

        if ($requestId === null) {
            return null;
        }

        $normalized = trim((string) $requestId);
        $normalized = preg_replace('/[\x00-\x1F\x7F]/u', '', $normalized) ?? '';

        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, self::REQUEST_ID_MAX_LENGTH);
    }

    private function incrementIngestionMetrics(string $level): void
    {
        $day = now()->format('Ymd');

        $globalKey = "metrics:frontend_observability_logs:{$level}";
        $dailyKey = "metrics:frontend_observability_logs:{$level}:{$day}";

        Cache::add($globalKey, 0, now()->addDays(30));
        Cache::add($dailyKey, 0, now()->endOfDay());

        Cache::increment($globalKey);
        Cache::increment($dailyKey);
    }
}
