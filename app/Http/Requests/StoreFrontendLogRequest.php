<?php

declare(strict_types=1);

namespace App\Http\Requests;

use DateTimeImmutable;
use Illuminate\Validation\Validator;

class StoreFrontendLogRequest extends ApiRequest
{
    public function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $context = $this->input('context');

        if (is_array($context)) {
            $context = array_map(function ($item) {
                if (! is_string($item)) {
                    return $item;
                }

                $item = trim($item);
                $item = preg_replace('/[\x00-\x1F\x7F]/u', '', $item);
                $item = preg_replace('/\s+/', ' ', $item);

                return $item;
            }, $context);
        }

        $scope = $this->input('scope');
        $eventName = $this->input('event_name');

        if (is_string($scope)) {
            $scope = trim($scope);
        }

        if (is_string($eventName)) {
            $eventName = trim($eventName);
        }

        $this->merge([
            'scope' => $scope,
            'context' => $context,
            'event_name' => $eventName,
        ]);
    }

    public function rules(): array
    {
        return [
            'timestamp' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+\-]\d{2}:\d{2})$/'],
            'level' => ['required', 'string', 'in:warn,error'],
            'scope' => ['required', 'string', 'max:100'],
            'context' => ['nullable', 'array', 'max:10'],
            'context.*' => ['string', 'max:100'],
            'event_name' => ['nullable', 'string', 'max:150'],
            'meta' => ['nullable', 'array'],
            'args' => ['nullable', 'array', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $timestamp = $this->input('timestamp');

            if (is_string($timestamp) && ! $this->isStrictlyParseableIso8601($timestamp)) {
                $validator->errors()->add('timestamp', 'El campo timestamp debe ser una fecha ISO8601 válida.');
            }

            $eventName = $this->input('event_name');
            $args = $this->input('args');

            $hasEventName = is_string($eventName) && trim($eventName) !== '';
            $hasArgs = is_array($args) && count($args) > 0;

            if (! $hasEventName && ! $hasArgs) {
                $validator->errors()->add('event_name', 'Debe enviar al menos uno entre event_name o args.');
            }

            $meta = $this->input('meta');
            if (is_array($meta) && array_is_list($meta)) {
                $validator->errors()->add('meta', 'El campo meta debe ser un objeto JSON.');
            }

            if (strlen($this->getContent()) > 32 * 1024) {
                $validator->errors()->add('payload', 'El payload no puede superar 32KB.');
            }
        });
    }

    private function isStrictlyParseableIso8601(string $timestamp): bool
    {
        if (! preg_match('/^(?<date>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(?<fraction>\d{1,6}))?(?<tz>Z|[+\-]\d{2}:\d{2})$/', $timestamp, $matches)) {
            return false;
        }

        $fraction = $matches['fraction'] ?? null;
        $timezone = $matches['tz'] === 'Z' ? '+00:00' : $matches['tz'];

        $normalizedTimestamp = $matches['date']
            .($fraction !== null && $fraction !== '' ? '.'.str_pad($fraction, 6, '0') : '')
            .$timezone;

        $format = $fraction !== null && $fraction !== ''
            ? 'Y-m-d\TH:i:s.uP'
            : 'Y-m-d\TH:i:sP';

        $parsed = DateTimeImmutable::createFromFormat($format, $normalizedTimestamp);

        if ($parsed === false) {
            return false;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return false;
        }

        return $parsed->format($format) === $normalizedTimestamp;
    }

    public function messages(): array
    {
        return [
            'timestamp.required' => 'El campo timestamp es obligatorio.',
            'timestamp.regex' => 'El campo timestamp debe estar en formato ISO8601.',
            'level.required' => 'El campo level es obligatorio.',
            'level.in' => 'El campo level debe ser warn o error.',
            'scope.required' => 'El campo scope es obligatorio.',
            'scope.max' => 'El campo scope no debe superar los 100 caracteres.',
            'context.array' => 'El campo context debe ser un arreglo.',
            'context.max' => 'El campo context no debe tener más de 10 elementos.',
            'context.*.max' => 'Cada valor de context no debe superar los 100 caracteres.',
            'event_name.max' => 'El campo event_name no debe superar los 150 caracteres.',
            'meta.array' => 'El campo meta debe ser un objeto JSON válido.',
            'args.array' => 'El campo args debe ser un arreglo.',
            'args.max' => 'El campo args no debe tener más de 20 elementos.',
        ];
    }
}
