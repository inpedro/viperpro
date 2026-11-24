<?php

namespace App\Core\Localization;

use App\Core\Tenancy\Tenancy;
use App\Models\Setting;
use Illuminate\Validation\Rule;

/**
 * Per-tenant registration fields. Replaces the Brazil-specific assumptions (masked BR phone,
 * CPF) with configurable fields:
 *   phone_field / document_field: required | optional | hidden
 *   document_type: cpf (checksum-validated) | any other label (free text, max 50)
 */
final class RegistrationPolicy
{
    public const MODES = ['required', 'optional', 'hidden'];

    public static function config(): array
    {
        $row = null;
        try {
            $row = Tenancy::id() ? Setting::query()->first(['phone_field', 'document_field', 'document_type']) : null;
        } catch (\Throwable) {
            // columns not migrated yet
        }

        $mode = fn (?string $v, string $default) => in_array($v, self::MODES, true) ? $v : $default;

        return [
            'phone_field'    => $mode($row?->phone_field, 'required'),
            'document_field' => $mode($row?->document_field, 'hidden'),
            'document_type'  => $row?->document_type ?: null,
        ];
    }

    /** @return array<string, array> */
    public static function rules(): array
    {
        $c = self::config();

        $rules = [
            'name'      => ['required', 'string', 'max:120'],
            // Unique per tenant: the same person may hold accounts at different brands.
            'email'     => ['required', 'email', 'max:191', Rule::unique('users', 'email')->where('tenant_id', Tenancy::requireId())],
            'password'  => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
            'term_a'    => ['required'],
            'agreement' => ['required'],
        ];

        if ($c['phone_field'] !== 'hidden') {
            $rules['phone'] = [$c['phone_field'] === 'required' ? 'required' : 'nullable', 'string', 'max:32',
                fn ($attr, $value, $fail) => $value === null || $value === '' || self::normalizePhone((string) $value) !== null
                    ?: $fail(trans('validation.regex', ['attribute' => trans('Phone')]))];
        }

        if ($c['document_field'] !== 'hidden') {
            $rules['cpf'] = array_values(array_filter([
                $c['document_field'] === 'required' ? 'required' : 'nullable',
                'string',
                'max:50',
                $c['document_type'] === 'cpf' ? 'cpf' : null,
            ]));
        }

        return $rules;
    }

    /**
     * International phone normalization: keeps a leading "+", strips spaces, dashes, dots and
     * parentheses; 7-15 digits (E.164). Local formats without "+" are kept as digits.
     */
    public static function normalizePhone(string $raw): ?string
    {
        $raw = trim($raw);
        $plus = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D+/', '', $raw);
        if ($plus && str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        }

        if (! preg_match('/^[0-9]{7,15}$/', (string) $digits) || ($plus && str_starts_with($digits, '0'))) {
            return null;
        }

        return ($plus ? '+' : '').$digits;
    }
}
