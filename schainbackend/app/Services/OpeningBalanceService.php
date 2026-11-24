<?php

namespace App\Services;

use App\Models\OpeningBalanceUpdate;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use App\Support\UserFormProfiles;
use Illuminate\Validation\ValidationException;

/**
 * Opening balance updates (agreed 2026-11-24): an admin adds a value to one
 * of a user's balances, from the user form's Grams / Purity (per item) and
 * GRAMS / PURITY / CASH / RTGS buttons.
 *
 *  - closing = previous + value; a negative value reduces it.
 *  - An item's grams / purity also moves the user's grams / purity grand
 *    total by the same value, the way every transaction keeps them in step.
 *    The grand total buttons change only the grand total.
 *  - Each change writes an opening_balance_updates row (two for an item:
 *    the item and the grand total it moved).
 *
 * The caller runs this inside DB::transaction with the user row locked
 * (lockForUpdate). Arithmetic is bcmath, scale 4.
 */
class OpeningBalanceService
{
    public const FIELDS = ['ITEM_GRAMS', 'ITEM_PURITY', 'GRAMS', 'PURITY', 'CASH', 'RTGS'];

    private const USER_COLUMNS = [
        'GRAMS' => 'grams_grand_total',
        'PURITY' => 'purity_grand_total',
        'CASH' => 'rak_cash_balance',
        'RTGS' => 'rak_rtgs_balance',
    ];

    private const ITEM_COLUMNS = [
        'ITEM_GRAMS' => ['item_grams_total', 'GRAMS'],
        'ITEM_PURITY' => ['item_purity_total', 'PURITY'],
    ];

    /** Validation rules for one entry, under $prefix (e.g. "opening_balances.*."). */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}field" => 'required|string|in:' . implode(',', self::FIELDS),
            "{$prefix}item_id" => "required_if:{$prefix}field,ITEM_GRAMS,ITEM_PURITY|nullable|integer|exists:items,item_id",
            "{$prefix}value" => ['required', 'regex:/^-?\d{1,12}(\.\d{1,4})?$/', 'not_regex:/^-?0+(\.0+)?$/'],
            "{$prefix}remarks" => 'nullable|string|max:500',
        ];
    }

    public static function messages(string $prefix = ''): array
    {
        return [
            "{$prefix}value.regex" => 'Enter a number with up to 4 decimals (negative to reduce).',
            "{$prefix}value.not_regex" => 'Enter a value other than 0.',
            "{$prefix}item_id.required_if" => 'Pick the item to update.',
        ];
    }

    /** The form section a field belongs to: item rows or the balances block. */
    public static function sectionFor(string $field): string
    {
        return isset(self::ITEM_COLUMNS[$field]) ? 'items' : 'balances';
    }

    /** 422 unless the user's role profile has the field's section. */
    public static function assertAllowed(array $profile, string $field, string $errorKey): void
    {
        if (!in_array(self::sectionFor($field), $profile['sections'], true)) {
            throw ValidationException::withMessages([$errorKey => ['This role has no such balance on its form.']]);
        }
    }

    /**
     * Applies one entry to the (locked) user and records it.
     *
     * @param  array{field: string, item_id?: int|null, value: string, remarks?: string|null}  $entry
     */
    public function apply(UserDetail $user, array $entry, ?int $adminId, string $errorKey = 'value'): OpeningBalanceUpdate
    {
        $field = $entry['field'];
        $value = $this->decimal($entry['value']);
        $remarks = isset($entry['remarks']) ? trim((string) $entry['remarks']) ?: null : null;

        if (isset(self::ITEM_COLUMNS[$field])) {
            [$column, $grandField] = self::ITEM_COLUMNS[$field];
            $itemId = (int) $entry['item_id'];

            $mapping = UsersItemsMapping::where('user_id', $user->user_id)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();
            if (!$mapping) {
                throw ValidationException::withMessages([$errorKey => ["Add this item to the user's items first."]]);
            }

            $previous = $this->decimal($mapping->{$column});
            $closing = bcadd($previous, $value, 4);
            $mapping->{$column} = $closing;
            $mapping->last_txn_date = now();
            $mapping->save();

            $row = $this->record($user, $itemId, $field, $remarks, $previous, $value, $closing, $adminId);

            // The grand total follows the item.
            $item = $mapping->item()->value('item_name') ?? "#{$itemId}";
            $kind = $field === 'ITEM_GRAMS' ? 'grams' : 'purity';
            $this->applyToUser($user, $grandField, $value, "Follows the {$item} {$kind} update", $adminId);

            return $row;
        }

        return $this->applyToUser($user, $field, $value, $remarks, $adminId);
    }

    /** The user's balances after an update, for the form. */
    public static function balancesOf(UserDetail $user, ?int $itemId = null): array
    {
        $user->refresh();
        $mapping = $itemId
            ? UsersItemsMapping::where('user_id', $user->user_id)->where('item_id', $itemId)->first()
            : null;

        return [
            'grams_grand_total' => (string) ($user->grams_grand_total ?? 0),
            'purity_grand_total' => (string) ($user->purity_grand_total ?? 0),
            'rak_cash_balance' => (string) ($user->rak_cash_balance ?? 0),
            'rak_rtgs_balance' => (string) ($user->rak_rtgs_balance ?? 0),
            'item' => $mapping ? [
                'item_id' => (int) $mapping->item_id,
                'item_grams_total' => (string) $mapping->item_grams_total,
                'item_purity_total' => (string) $mapping->item_purity_total,
            ] : null,
        ];
    }

    private function applyToUser(UserDetail $user, string $field, string $value, ?string $remarks, ?int $adminId): OpeningBalanceUpdate
    {
        $column = self::USER_COLUMNS[$field];
        $previous = $this->decimal($user->{$column});
        $closing = bcadd($previous, $value, 4);
        $user->{$column} = $closing;
        $user->save();

        return $this->record($user, null, $field, $remarks, $previous, $value, $closing, $adminId);
    }

    private function record(UserDetail $user, ?int $itemId, string $field, ?string $remarks, string $previous, string $value, string $closing, ?int $adminId): OpeningBalanceUpdate
    {
        return OpeningBalanceUpdate::create([
            'user_id' => $user->user_id,
            'item_id' => $itemId,
            'field' => $field,
            'remarks' => $remarks,
            'previous_balance' => $previous,
            'value_update' => $value,
            'closing_balance' => $closing,
            'admin_id' => $adminId,
        ]);
    }

    /** A stored figure (string, float or null) as a scale-4 decimal string. */
    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.0000';
        }
        if (is_float($value) || is_int($value)) {
            return number_format((float) $value, 4, '.', '');
        }
        $text = trim((string) $value);
        if (!is_numeric($text)) {
            return '0.0000';
        }

        // bcmath can't read exponent notation (e.g. "1.0E-5" from a double).
        return stripos($text, 'e') !== false
            ? number_format((float) $text, 4, '.', '')
            : bcadd($text, '0', 4);
    }
}
