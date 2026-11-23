<?php

namespace App\Rules;

use App\Support\HeadUsers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The chosen user must hold the HEAD role (agreed 2026-11-23). One instance
 * serves a whole request: the head ids are read once, on first use.
 */
class HeadUser implements ValidationRule
{
    /** @var array<int, bool>|null */
    private ?array $headIds = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->headIds ??= HeadUsers::ids()->flip()->map(fn () => true)->all();

        if (!is_numeric($value) || !isset($this->headIds[(int) $value])) {
            $fail(str_starts_with($attribute, 'head_user_mappings')
                ? 'Only HEAD users can be listed as head users.'
                : 'Only HEAD users can be chosen as heads.');
        }
    }
}
