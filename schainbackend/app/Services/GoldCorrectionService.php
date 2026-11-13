<?php

namespace App\Services;

use App\Http\Requests\StoreCashToGoldRequest;
use App\Http\Requests\StoreGoldToCashRequest;
use App\Http\Requests\StorePurchaseGoldRequest;
use App\Http\Requests\StoreSaleGoldRequest;
use App\Models\CashToGold;
use App\Models\CashToGoldAmountSource;
use App\Models\CashTxnDetail;
use App\Models\StockDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Correcting gold entries (BACKEND_TODO §17 phase 3, agreed 2026-11-12).
 *
 * A gold entry's weight moves gram/purity balances that every later stock
 * entry has frozen into its snapshots, so the original is never rewritten.
 * Instead, in one transaction:
 *
 *   1. Reversal. The original's own service is run again with the money and
 *      weight figures negated. Every balance change those services make is
 *      an add or subtract of those figures, so this undoes the original
 *      exactly: head/customer/retailer cash and RTGS, the bank(s), gram and
 *      purity totals, item totals and a Sale Gold lot's balance. It writes
 *      mirror records with fresh snapshots.
 *   2. Corrected entry. The same service is run with the corrected input,
 *      validated by the same rules as a new entry.
 *   3. Links: original.corrected_by_id, reversal.reversal_of_id and
 *      corrected.corrects_id.
 *
 * The parties, the item and the entry type stay as they were. Both new
 * entries are dated now, so date-filtered reports show the original on its
 * day and the correction on the day it was made.
 */
class GoldCorrectionService
{
    /** cash_to_gold.type → which service wrote it. */
    private const KINDS = [
        'CUSTOMER' => 'cash_to_gold',
        'GOLD_TO_CASH' => 'gold_to_cash',
        'HEAD' => 'purchase',
        'OUT_CASH_CONVERTER' => 'purchase',
        'SALE_GOLD' => 'sale',
        'SALE_GOLD_CASH' => 'sale',
        'IN_CASH_CONVERTER' => 'sale',
    ];

    /** Fields a correction may change, per kind. */
    public const EDITABLE = [
        'cash_to_gold' => ['total_cash', 'per_gram_cash', 'total_grams', 'touch', 'purity', 'amount_sources', 'remarks', 'is_rate_avg'],
        'gold_to_cash' => ['total_cash', 'per_gram_cash', 'total_grams', 'touch', 'total_gold', 'amount_sources', 'remarks', 'is_rate_avg'],
        'purchase' => ['total_cash', 'per_gram_cash', 'total_grams', 'touch', 'purity', 'amount_sources', 'remarks', 'is_rate_avg',
            'taken_total_cash', 'taken_total_grams', 'taken_purity', 'is_live', 'bank_entry_date'],
        'sale' => ['total_cash', 'per_gram_cash', 'total_grams', 'touch', 'purity', 'amount_sources', 'remarks', 'is_rate_avg',
            'taken_total_cash', 'taken_total_grams', 'taken_purity', 'is_live', 'bank_entry_date'],
    ];

    /** Money and weight fields that a reversal negates. */
    private const NEGATED = ['total_cash', 'total_grams', 'purity', 'total_gold', 'taken_total_cash', 'taken_total_grams', 'taken_purity'];

    public static function kindOf(CashToGold $record): ?string
    {
        return self::KINDS[$record->type] ?? null;
    }

    /** Why this entry can't be corrected, or null when it can. */
    public static function notCorrectableReason(CashToGold $record): ?string
    {
        if (self::kindOf($record) === null) {
            return "Gold entries of type {$record->type} can't be corrected.";
        }
        if ($record->reversal_of_id) {
            return "This is the reversal of entry #{$record->reversal_of_id} and can't be corrected.";
        }
        if ($record->corrected_by_id) {
            return "This entry was already corrected by #{$record->corrected_by_id}; correct that one instead.";
        }

        return null;
    }

    /** The input the original entry was saved with, rebuilt from its records. */
    public function originalInput(CashToGold $record): array
    {
        $kind = self::kindOf($record);
        $id = $record->getKey();

        $sources = CashToGoldAmountSource::where('cash_to_gold_id', $id)
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => [
                'source' => $s->souce_type,
                'bank_id' => $s->bank_id,
                'amount' => (string) $s->amount,
            ])
            ->all();

        $input = [
            'head_id' => (int) $record->head_id,
            'customer_id' => (int) $record->customer_id,
            'total_cash' => (string) $record->total_cash,
            'per_gram_cash' => (string) $record->per_gram_cash,
            'total_grams' => (string) $record->total_grams,
            'touch' => (string) $record->touch,
            'remarks' => $record->remarks,
            'retailer_id' => $record->retailer_id,
            'is_rate_avg' => (bool) $record->is_rate_avg,
            'amount_sources' => $sources,
        ];

        if ($kind === 'gold_to_cash') {
            // GoldToCashService saves the pure gold in `purity` and always uses item 3.
            $input['total_gold'] = (string) $record->purity;

            return $input;
        }

        $input['purity'] = (string) $record->purity;
        $input['item_id'] = (int) $record->item_id;
        $input['amnt_transfer_to_head'] = (bool) $record->amnt_transfer_to_head;

        if ($kind === 'purchase' || $kind === 'sale') {
            $input['type'] = $record->type;
            $input['taken_total_cash'] = $record->taken_total_cash !== null ? (string) $record->taken_total_cash : null;
            $input['taken_total_grams'] = $record->taken_total_grams !== null ? (string) $record->taken_total_grams : null;
            $input['taken_purity'] = $record->taken_purity !== null ? (string) $record->taken_purity : null;
            $input['is_live'] = (bool) $record->is_live;
            $input['bank_entry_date'] = CashTxnDetail::where('cash_to_gold_id', $id)
                ->whereNotNull('bank_entry_date')
                ->value('bank_entry_date');
            if (!$input['amnt_transfer_to_head']) {
                $input['amount_sources'] = [];
            }
        }

        if ($kind === 'sale' && $record->stock_id) {
            $input['stock_in_id'] = StockDetails::where('stock_id', $record->stock_id)->value('stock_in_id');
        }

        return $input;
    }

    /**
     * @return array{original: CashToGold, reversal: CashToGold, corrected: CashToGold}
     */
    public function correct(int $id, array $changes, int $actorId): array
    {
        return DB::transaction(function () use ($id, $changes, $actorId) {
            $record = CashToGold::where('cash_to_gold_id', $id)->lockForUpdate()->firstOrFail();

            if ($reason = self::notCorrectableReason($record)) {
                throw new InvalidArgumentException($reason);
            }

            $kind = self::kindOf($record);
            $input = $this->originalInput($record);
            $corrected = array_merge($input, array_intersect_key($changes, array_flip(self::EDITABLE[$kind])));
            unset($corrected['added_at']);

            // Same rules as a new entry of this kind (throws a 422 ValidationException).
            Validator::make($corrected, $this->rulesFor($kind))->validate();

            $service = $this->serviceFor($kind);

            // 1. Reversal: the original with its money and weight negated.
            $reversalInput = $this->negate($input);
            unset($reversalInput['added_at']);
            $reversal = $service->store($reversalInput, $actorId, ['reversal' => true]);
            $reversal->reversal_of_id = $id;
            $reversal->remarks = trim("Reversal of #{$id}. " . ($input['remarks'] ?? ''));
            $reversal->save();

            // 2. The corrected entry.
            $new = $service->store($corrected, $actorId);
            $new->corrects_id = $id;
            $new->save();

            // 3. Mark the original.
            $record->corrected_by_id = $new->getKey();
            $record->save();

            SystemLogService::log(
                'CASH',
                'GOLD_CORRECTION',
                $actorId,
                $new->getKey(),
                'cash_to_gold',
                "Corrected gold entry #{$id} ({$record->type}): reversal #{$reversal->getKey()}, corrected entry #{$new->getKey()}",
                $changes,
                ['original_id' => $id, 'reversal_id' => $reversal->getKey(), 'corrected_id' => $new->getKey(), 'before' => $input, 'after' => $corrected]
            );

            return ['original' => $record->fresh(), 'reversal' => $reversal->fresh(), 'corrected' => $new->fresh()];
        });
    }

    private function negate(array $input): array
    {
        foreach (self::NEGATED as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $input[$field] = bcsub('0', (string) $input[$field], 4);
            }
        }
        foreach ($input['amount_sources'] ?? [] as $i => $source) {
            $input['amount_sources'][$i]['amount'] = bcsub('0', (string) $source['amount'], 4);
        }

        return $input;
    }

    private function serviceFor(string $kind)
    {
        return match ($kind) {
            'cash_to_gold' => app(CashToGoldService::class),
            'gold_to_cash' => app(GoldToCashService::class),
            'purchase' => app(PurchaseGoldService::class),
            'sale' => app(SaleGoldService::class),
        };
    }

    private function rulesFor(string $kind): array
    {
        $request = match ($kind) {
            'cash_to_gold' => new StoreCashToGoldRequest(),
            'gold_to_cash' => new StoreGoldToCashRequest(),
            'purchase' => new StorePurchaseGoldRequest(),
            'sale' => new StoreSaleGoldRequest(),
        };

        return array_diff_key($request->rules(), array_flip(['images', 'images.*']));
    }
}
