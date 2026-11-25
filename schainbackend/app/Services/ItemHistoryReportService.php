<?php

namespace App\Services;

use App\Models\GoldConversion;
use App\Models\HeadEmployeeMapping;
use App\Models\Item;
use App\Models\ItemChangeHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Item Change History and Item Conversion Group History (2026-11-25): the
 * records the Stocks page's Item Change and Item Conversion leave behind
 * (item_change_history, gold_conversions + gold_conversion_alloys), in the
 * app and the admin console.
 *
 * Neither table says who did it. Both features only ever work on the
 * signed-in user's own stock, so the user is given_by on the change's OUT
 * stock row; that row also holds the remarks and the From item's purity.
 *
 * Scope ($userIds): a head sees its own records and its mapped employees'
 * (scopeFor); the console sees every user's, or one head's scope.
 *
 * Read-only; nothing here changes a balance.
 */
class ItemHistoryReportService
{
    public const DEFAULT_PAGE_SIZE = 100;

    /** A user's own id plus, for a head, its mapped employees'. */
    public function scopeFor(int $userId): array
    {
        $employees = HeadEmployeeMapping::where('head_id', $userId)->pluck('employee_id')->map(fn ($id) => (int) $id);

        return $employees->prepend($userId)->unique()->values()->all();
    }

    /* ------------------------------------------------------------ item change */

    /**
     * One row per change, newest first, with totals over the whole search.
     */
    public function itemChanges(array $filters, ?array $userIds): array
    {
        [$pageNo, $pageSize] = $this->paging($filters);

        $query = ItemChangeHistory::query()
            ->leftJoin('stock_details as out_stock', 'out_stock.stock_id', '=', 'item_change_history.out_stock_id');
        $this->applyDates($query, 'item_change_history.added_at', $filters);
        $this->applyScope($query, $userIds);
        if (!empty($filters['item_id'])) {
            $itemId = (int) $filters['item_id'];
            $query->where(fn ($q) => $q->where('item_change_history.from_item_id', $itemId)
                ->orWhere('item_change_history.to_item_id', $itemId));
        }

        $sums = (clone $query)
            ->selectRaw('count(*) as cnt, sum(item_change_history.grams) as grams, sum(out_stock.purity) as from_purity, sum(item_change_history.total) as purity')
            ->toBase()
            ->first();
        $total = (int) ($sums->cnt ?? 0);

        $records = $query
            ->select('item_change_history.*')
            ->with(['fromItem', 'toItem', 'outStock.givenBy'])
            ->orderByDesc('item_change_history.added_at')
            ->orderByDesc('item_change_history.change_id')
            ->forPage($pageNo, $pageSize)
            ->get()
            ->map(fn (ItemChangeHistory $change) => $this->changeRow($change))
            ->values();

        return [
            'total_count' => $total,
            'records' => $records,
            'totals' => [
                'grams' => $this->decimal($sums->grams ?? 0),
                'from_purity' => $this->decimal($sums->from_purity ?? 0),
                'purity' => $this->decimal($sums->purity ?? 0),
            ],
            'pagination' => $this->pagination($pageNo, $pageSize, $total),
        ];
    }

    private function changeRow(ItemChangeHistory $change): array
    {
        $out = $change->outStock;

        return [
            'id' => $change->change_id,
            'added_at' => $change->added_at?->format('Y-m-d H:i:s'),
            'user_id' => $out?->given_by,
            'user_name' => $this->userName($out),
            'from_item_id' => $change->from_item_id,
            'from_item_name' => $change->fromItem?->item_name,
            'to_item_id' => $change->to_item_id,
            'to_item_name' => $change->toItem?->item_name,
            'grams' => $change->grams,
            'from_touch' => $change->from_touch,
            'req_touch' => $change->req_touch,
            'from_purity' => $out?->purity,
            'purity' => $change->total,
            'remarks' => $this->ownRemarks($out?->remarks, "Item Change from: {$change->from_item_id}"),
            'item_remarks' => $out?->item_remarks,
            'out_stock_id' => $change->out_stock_id,
            'in_stock_id' => $change->in_stock_id,
        ];
    }

    /* -------------------------------------------------------- item conversion */

    /**
     * Grouped by item pair (source → target), the pairs in Orders No order.
     * The groups are paged; each carries all its conversions (newest first)
     * and its totals.
     */
    public function conversionGroups(array $filters, ?array $userIds): array
    {
        [$pageNo, $pageSize] = $this->paging($filters);

        $base = GoldConversion::query()
            ->leftJoin('stock_details as out_stock', 'out_stock.stock_id', '=', 'gold_conversions.out_stock_id');
        $this->applyDates($base, 'gold_conversions.added_at', $filters);
        $this->applyScope($base, $userIds);
        if (!empty($filters['item_id'])) {
            $itemId = (int) $filters['item_id'];
            $base->where(fn ($q) => $q->where('gold_conversions.source_item_id', $itemId)
                ->orWhere('gold_conversions.target_item_id', $itemId));
        }

        // The pairs, with their counts.
        $pairs = (clone $base)
            ->selectRaw('gold_conversions.source_item_id, gold_conversions.target_item_id, count(*) as cnt')
            ->groupBy('gold_conversions.source_item_id', 'gold_conversions.target_item_id')
            ->toBase()
            ->get();

        $items = Item::whereIn('item_id', $pairs->pluck('source_item_id')->merge($pairs->pluck('target_item_id'))->unique())
            ->get()
            ->keyBy('item_id');
        $rank = fn ($itemId) => [
            $items[$itemId]->order_no ?? PHP_INT_MAX,
            $items[$itemId]->item_name ?? '',
        ];
        $pairs = $pairs->sort(fn ($a, $b) => [$rank($a->source_item_id), $rank($a->target_item_id)]
            <=> [$rank($b->source_item_id), $rank($b->target_item_id)])->values();

        $totalGroups = $pairs->count();
        $totalConversions = (int) $pairs->sum('cnt');
        $pagePairs = $pairs->forPage($pageNo, $pageSize)->values();

        $records = $pagePairs->isEmpty() ? collect() : (clone $base)
            ->select('gold_conversions.*')
            ->where(function ($q) use ($pagePairs) {
                foreach ($pagePairs as $pair) {
                    $q->orWhere(fn ($p) => $p->where('gold_conversions.source_item_id', $pair->source_item_id)
                        ->where('gold_conversions.target_item_id', $pair->target_item_id));
                }
            })
            ->with(['outStock.givenBy', 'inStock', 'alloys.alloyItem'])
            ->orderByDesc('gold_conversions.added_at')
            ->orderByDesc('gold_conversions.id')
            ->get()
            ->groupBy(fn (GoldConversion $c) => $c->source_item_id . '-' . $c->target_item_id);

        $groups = $pagePairs->map(function ($pair) use ($items, $records) {
            $key = $pair->source_item_id . '-' . $pair->target_item_id;
            $rows = $records->get($key, collect())->map(fn (GoldConversion $c) => $this->conversionRow($c, $items))->values();

            return [
                'key' => $key,
                'source_item_id' => (int) $pair->source_item_id,
                'source_item_name' => $items[$pair->source_item_id]->item_name ?? null,
                'target_item_id' => (int) $pair->target_item_id,
                'target_item_name' => $items[$pair->target_item_id]->item_name ?? null,
                'count' => (int) $pair->cnt,
                'totals' => [
                    'source_grams' => $this->sum($rows, 'source_grams'),
                    'source_purity' => $this->sum($rows, 'source_purity'),
                    'converted_grams' => $this->sum($rows, 'converted_grams'),
                    'converted_purity' => $this->sum($rows, 'converted_purity'),
                    'alloy_grams' => $this->sum($rows, 'alloy_grams'),
                ],
                'records' => $rows,
            ];
        })->values();

        return [
            'total_count' => $totalGroups,
            'total_conversions' => $totalConversions,
            'groups' => $groups,
            'pagination' => $this->pagination($pageNo, $pageSize, $totalGroups),
        ];
    }

    private function conversionRow(GoldConversion $conversion, Collection $items): array
    {
        $out = $conversion->outStock;
        $alloys = $conversion->alloys->map(fn ($alloy) => [
            'alloy_item_id' => $alloy->alloy_item_id,
            'alloy_item_name' => $alloy->alloyItem?->item_name,
            'alloy_percentage' => $this->decimal($alloy->alloy_percentage),
            'alloy_grams' => $this->decimal($alloy->alloy_grams),
        ])->values();

        return [
            'id' => $conversion->id,
            'added_at' => $conversion->added_at?->format('Y-m-d H:i:s'),
            'user_id' => $out?->given_by,
            'user_name' => $this->userName($out),
            'source_item_id' => $conversion->source_item_id,
            'source_item_name' => $items[$conversion->source_item_id]->item_name ?? null,
            'target_item_id' => $conversion->target_item_id,
            'target_item_name' => $items[$conversion->target_item_id]->item_name ?? null,
            'source_grams' => $conversion->source_grams,
            'source_touch' => $conversion->source_touch,
            'source_purity' => $out?->purity,
            'target_touch' => $conversion->target_touch,
            'converted_grams' => $conversion->converted_grams,
            'converted_purity' => $conversion->inStock?->purity,
            'alloys' => $alloys,
            'alloy_grams' => $alloys->reduce(fn ($sum, $a) => bcadd($sum, (string) $a['alloy_grams'], 4), '0.0000'),
            'remarks' => $this->ownRemarks($out?->remarks, "Item Conversion from: {$conversion->source_item_id}"),
            'item_remarks' => $out?->item_remarks,
            'bill_id' => $conversion->billing_entry_id,
            'out_stock_id' => $conversion->out_stock_id,
            'in_stock_id' => $conversion->in_stock_id,
        ];
    }

    /* ---------------------------------------------------------------- shared */

    /** From / To Date on the record's own date; today by default, one day without To Date. */
    private function applyDates(Builder $query, string $column, array $filters): void
    {
        $from = ($filters['from_date'] ?? null) ?: date('Y-m-d');
        $to = ($filters['to_date'] ?? null) ?: $from;
        $query->whereDate($column, '>=', $from)->whereDate($column, '<=', $to);
    }

    private function applyScope(Builder $query, ?array $userIds): void
    {
        if ($userIds !== null) {
            $query->whereIn('out_stock.given_by', $userIds);
        }
    }

    private function paging(array $filters): array
    {
        return [
            max(1, (int) ($filters['page_no'] ?? 1)),
            max(1, (int) ($filters['page_size'] ?? self::DEFAULT_PAGE_SIZE)),
        ];
    }

    private function pagination(int $pageNo, int $pageSize, int $total): array
    {
        return [
            'current_page' => $pageNo,
            'per_page' => $pageSize,
            'total_items' => $total,
            'total_pages' => (int) ceil($total / $pageSize),
        ];
    }

    private function userName($stock): ?string
    {
        $user = $stock?->givenBy;

        return $user ? ($user->name ?: $user->user_name) : null;
    }

    /** The remarks the user typed; the services' automatic "… from: <item id>" isn't one. */
    private function ownRemarks(?string $remarks, string $automatic): ?string
    {
        return ($remarks === null || $remarks === '' || $remarks === $automatic) ? null : $remarks;
    }

    /** Sums a decimal field of the rows with bcmath (scale 4). */
    private function sum(Collection $rows, string $field): string
    {
        return $rows->reduce(fn ($sum, $row) => bcadd($sum, (string) ($row[$field] ?? '0'), 4), '0.0000');
    }

    /** A database sum or decimal as a 4-place string (SQLite returns floats). */
    private function decimal($value): string
    {
        return is_string($value) && is_numeric($value)
            ? bcadd($value, '0', 4)
            : number_format((float) $value, 4, '.', '');
    }
}
