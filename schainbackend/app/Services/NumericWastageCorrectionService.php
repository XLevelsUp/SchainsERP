<?php

namespace App\Services;

use App\Http\Requests\NumericWastageInRequest;
use App\Http\Requests\NumericWasteRequest;
use App\Models\CashTxnDetail;
use App\Models\NumericWastage;
use App\Models\NumericWastageIn;
use App\Models\StockDetails;
use App\Models\StockInDetail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Correcting numeric wastage entries (BACKEND_TODO §17, agreed 2026-11-12:
 * heads only, no deletes, correcting entry like the gold entries).
 *
 * One numeric wastage line is one stock row (stock_in_details for "in",
 * stock_details for "out", type NUMERICWASTE), its wastage record, and a
 * cash entry when an amount per piece was given. Its grams and purity are
 * frozen into every later stock entry's snapshots, so the line is never
 * rewritten. Instead, in one transaction:
 *
 *   1. Reversal. The original's own service (StockInService::
 *      createNumericWasteIn / StockOutService::createNumericWaste) runs again
 *      with grams, pieces, wastage weight, purity and amount negated. Every
 *      balance change it makes is an add or subtract of those figures, so
 *      this undoes the original exactly: both parties' grams/purity totals,
 *      item totals and, when there was cash, both parties' cash.
 *   2. Corrected entry. The same service runs with the corrected line,
 *      validated by the same rules as a new entry.
 *   3. Links on the stock rows (migration 2026_11_12_000300):
 *      original.corrected_by_id, reversal.reversal_of_id and
 *      corrected.corrects_id.
 *
 * Entries are identified by their stock row id, the "ID : N" the cash
 * entry's remarks show. The parties and the item can't change. Both new
 * entries are dated now.
 */
class NumericWastageCorrectionService
{
    public const DIRECTIONS = ['in', 'out'];

    /** Fields a correction may change. */
    public const EDITABLE = ['grams', 'touch', 'no_of_pcs', 'amount_pcs', 'waste_total', 'remarks', 'item_remarks'];

    /** The numeric wastage stock row, or null. */
    public static function stockRow(string $direction, int $id, bool $lock = false): ?Model
    {
        $query = $direction === 'in'
            ? StockInDetail::where('stock_in_detail_id', $id)
            : StockDetails::where('stock_id', $id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->where('type', 'NUMERICWASTE')->first();
    }

    /** The wastage record written with that stock row. */
    public static function wastageRecord(string $direction, Model $stock): ?Model
    {
        return $direction === 'in'
            ? NumericWastageIn::where('stock_in_detail_id', $stock->getKey())->first()
            : NumericWastage::where('stock_id', $stock->getKey())->first();
    }

    /**
     * The numeric wastage entry a cash entry belongs to, as
     * ['direction' => 'in'|'out', 'id' => stock row id], or null.
     */
    public static function forCashTxn(CashTxnDetail $txn): ?array
    {
        if (!str_starts_with((string) $txn->remarks, 'NUMERIC_WASTAGE')) {
            return null;
        }

        $inId = NumericWastageIn::where('cash_txn_id', $txn->txn_id)->value('stock_in_detail_id');
        if ($inId) {
            return ['direction' => 'in', 'id' => (int) $inId];
        }

        $outId = NumericWastage::where('cash_txn_id', $txn->txn_id)->value('stock_id');
        if ($outId) {
            return ['direction' => 'out', 'id' => (int) $outId];
        }

        return null;
    }

    /** Why this entry can't be corrected, or null when it can. */
    public static function notCorrectableReason(Model $stock, ?Model $record): ?string
    {
        if (!$record) {
            return 'This numeric wastage entry has no wastage record, so it can\'t be corrected.';
        }
        if ($stock->reversal_of_id) {
            return "This is the reversal of entry #{$stock->reversal_of_id} and can't be corrected.";
        }
        if ($stock->corrected_by_id) {
            return "This entry was already corrected by #{$stock->corrected_by_id}; correct that one instead.";
        }

        return null;
    }

    /** The line the original entry was saved with, rebuilt from its records. */
    public function originalInput(Model $stock, Model $record): array
    {
        return [
            'given_by' => (int) $stock->given_by,
            'given_to' => (int) $stock->given_to,
            'item_id' => (int) $stock->item_id,
            'grams' => (string) $stock->grams,
            'touch' => (string) $stock->touch,
            // The wastage record keeps the pieces exactly. Trimmed ("2", not
            // "2.0000") because stock_in_details stores them in an integer
            // column, which PostgreSQL won't fill from "2.0000".
            'no_of_pcs' => $this->trimNumber((string) $record->no_of_pcs),
            // The services save the amount per piece in mtouch.
            'amount_pcs' => (string) ($stock->mtouch ?? 0),
            'waste_total' => (string) $record->wastage_value,
            'waste_id' => $stock->waste_id,
            'remarks' => $stock->remarks,
            'item_remarks' => $stock->item_remarks,
        ];
    }

    /**
     * @return array{direction: string, original: Model, reversal: Model, corrected: Model}
     */
    public function correct(string $direction, int $id, array $changes, int $actorId): array
    {
        if (!in_array($direction, self::DIRECTIONS, true)) {
            throw new InvalidArgumentException('Unknown numeric wastage direction.');
        }

        return DB::transaction(function () use ($direction, $id, $changes, $actorId) {
            $stock = self::stockRow($direction, $id, true);
            if (!$stock) {
                throw (new ModelNotFoundException())->setModel($direction === 'in' ? StockInDetail::class : StockDetails::class, [$id]);
            }

            $record = self::wastageRecord($direction, $stock);
            if ($reason = self::notCorrectableReason($stock, $record)) {
                throw new InvalidArgumentException($reason);
            }

            $input = $this->originalInput($stock, $record);
            $corrected = array_merge($input, array_intersect_key($changes, array_flip(self::EDITABLE)));

            // Same rules as a new line of this direction (throws a 422 ValidationException).
            Validator::make($corrected, $this->rulesFor($direction))->validate();

            $originalCash = $record->cash_txn_id
                ? CashTxnDetail::where('txn_id', $record->cash_txn_id)->first()
                : null;

            // 1. Reversal: the original line with every figure that moved a
            //    balance negated, and the derived ones passed explicitly so
            //    they cancel what was saved rather than being recomputed.
            $reversalLine = [
                'item_id' => $input['item_id'],
                'grams' => $this->negate($input['grams']),
                'touch' => $input['touch'],
                'no_of_pcs' => $this->trimNumber($this->negate($input['no_of_pcs'])),
                // No cash entry on the original → none on the reversal.
                'amount_pcs' => $originalCash ? $input['amount_pcs'] : 0,
                'waste_total' => $input['waste_total'],
                'waste_id' => $input['waste_id'],
                'waste_value' => $this->negate((string) $record->wastage_total),
                'purity' => $this->negate((string) $stock->purity),
                'amount' => $this->negate((string) $record->amount),
                'remarks' => trim("Reversal of #{$id}. " . ($input['remarks'] ?? '')),
                'item_remarks' => $input['item_remarks'],
            ];
            $reversal = $this->run($direction, $input, $reversalLine, $actorId);
            $reversal['stock']->reversal_of_id = $id;
            $reversal['stock']->save();

            // The reversal must take back exactly the cash the original moved.
            if ($originalCash) {
                $reversedCash = $reversal['cash'] ? (string) $reversal['cash']->amount : '0';
                if (bccomp(bcadd((string) $originalCash->amount, $reversedCash, 2), '0', 2) !== 0) {
                    throw new RuntimeException("Reversal cash {$reversedCash} doesn't cancel the original {$originalCash->amount}.");
                }
            }
            $this->tagCashRemarks($reversal['cash'], "reversal of #{$id}");

            // 2. The corrected line.
            $correctedLine = array_diff_key($corrected, array_flip(['given_by', 'given_to']));
            $new = $this->run($direction, $input, $correctedLine, $actorId);
            $new['stock']->corrects_id = $id;
            $new['stock']->save();
            $this->tagCashRemarks($new['cash'], "corrects #{$id}");

            // 3. Mark the original.
            $stock->corrected_by_id = $new['stock']->getKey();
            $stock->save();

            $table = $direction === 'in' ? 'stock_in_details' : 'stock_details';
            SystemLogService::log(
                'STOCK',
                'NUMERIC_WASTAGE_CORRECTION',
                $actorId,
                $new['stock']->getKey(),
                $table,
                "Corrected numeric wastage {$direction} #{$id}: reversal #{$reversal['stock']->getKey()}, corrected entry #{$new['stock']->getKey()}",
                $changes,
                [
                    'direction' => $direction,
                    'original_id' => $id,
                    'reversal_id' => $reversal['stock']->getKey(),
                    'corrected_id' => $new['stock']->getKey(),
                    'before' => $input,
                    'after' => $corrected,
                ]
            );

            return [
                'direction' => $direction,
                'original' => $stock->fresh(),
                'reversal' => $reversal['stock']->fresh(),
                'corrected' => $new['stock']->fresh(),
            ];
        });
    }

    /**
     * Run the direction's own service for one line between the original
     * parties.
     *
     * @return array{stock: Model, cash: ?CashTxnDetail}
     */
    private function run(string $direction, array $parties, array $line, int $actorId): array
    {
        $data = [
            'given_by' => $parties['given_by'],
            'given_to' => $parties['given_to'],
            'items' => [$line],
        ];

        $records = $direction === 'in'
            ? app(StockInService::class)->createNumericWasteIn($data, $actorId)
            : app(StockOutService::class)->createNumericWaste($data, $actorId);

        $record = $records[0];
        $stock = $direction === 'in'
            ? StockInDetail::where('stock_in_detail_id', $record->stock_in_detail_id)->firstOrFail()
            : StockDetails::where('stock_id', $record->stock_id)->firstOrFail();
        $cash = $record->cash_txn_id ? CashTxnDetail::where('txn_id', $record->cash_txn_id)->first() : null;

        return ['stock' => $stock, 'cash' => $cash];
    }

    /** Say on the cash entry which correction it belongs to; the NUMERIC_WASTAGE prefix stays. */
    private function tagCashRemarks(?CashTxnDetail $cash, string $note): void
    {
        if ($cash) {
            $cash->remarks = trim("{$cash->remarks} - {$note}");
            $cash->save();
        }
    }

    private function negate(string $value): string
    {
        return bcsub('0', $value === '' ? '0' : $value, 4);
    }

    /** "2.5000" → "2.5", "-2.0000" → "-2". */
    private function trimNumber(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value;
        }
        $value = rtrim(rtrim($value, '0'), '.');

        return $value === '' || $value === '-' ? '0' : $value;
    }

    /**
     * The new-entry rules for one line: the request's `items.*` rules
     * flattened, plus the parties.
     */
    private function rulesFor(string $direction): array
    {
        $rules = ($direction === 'in' ? new NumericWastageInRequest() : new NumericWasteRequest())->rules();

        $flat = [];
        foreach ($rules as $key => $rule) {
            if (str_starts_with($key, 'items.*.')) {
                $flat[substr($key, strlen('items.*.'))] = $rule;
            } elseif (in_array($key, ['given_by', 'given_to'], true)) {
                $flat[$key] = $rule;
            }
        }
        unset($flat['added_at']);

        return $flat;
    }
}
