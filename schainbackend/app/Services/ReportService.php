<?php

namespace App\Services;

use App\Models\StockDetails;
use App\Models\UserDetail;
use App\Models\RetailerUser; // Wait, let's verify retailer user model name
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Compile Items OB & CB report records based on filters.
     */
    public function getItemsObcbReport(array $filters, int $headId): array
    {
        $employeeId = $filters['employee_id'] ?? null;
        $itemId = $filters['item_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $fromTime = $filters['from_time'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $toTime = $filters['to_time'] ?? null;
        $stockType = $filters['type'] ?? null;
        $pageSize = $filters['page_size'] ?? 50;
        $pageNo = $filters['page_no'] ?? 1;

        $userId = null;
        $retailerId = null;

        if ($employeeId) {
            if (strpos($employeeId, 'retailer_') === 0) {
                $retailerId = (int) str_replace('retailer_', '', $employeeId);
            } elseif (strpos($employeeId, 'user_') === 0) {
                $userId = (int) str_replace('user_', '', $employeeId);
            } else {
                $userId = (int) $employeeId;
            }
        }

        $query = StockDetails::with(['item', 'toItem', 'givenBy', 'givenTo', 'bill'])
            ->where('is_freezed', false);

        // 1. User-wise / Retailer-wise filter
        if ($userId) {
            $query->where(function (Builder $q) use ($userId) {
                $q->where('given_by', $userId)->orWhere('given_to', $userId);
            });
        } elseif ($retailerId) {
            $query->where(function (Builder $q) use ($retailerId) {
                $q->where('retailer_id', $retailerId)->orWhere('to_retailer_id', $retailerId);
            });
        } else {
            // General HEAD perspective filter
            $query->where(function (Builder $q) use ($headId) {
                $q->where('given_by', $headId)->orWhere('given_to', $headId);
            })->where('remarks', '!=', 'CASH_TO_GOLD');
        }

        // 2. Item-specific filter (handling HEADTOHEAD mapping conversions)
        if ($itemId) {
            $query->where(function (Builder $q) use ($itemId, $headId) {
                $q->where(function (Builder $sq) use ($itemId, $headId) {
                    $sq->where('entry_type', 'HEADTOHEAD')
                        ->where(function (Builder $ssq) use ($itemId, $headId) {
                            $ssq->where(fn($f) => $f->where('given_by', $headId)->where('item_id', $itemId))
                                ->orWhere(fn($f) => $f->where('given_by', '!=', $headId)->where('to_item_id', $itemId));
                        });
                })->orWhere(function (Builder $sq) use ($itemId) {
                    $sq->where('entry_type', '!=', 'HEADTOHEAD')
                        ->where('item_id', $itemId);
                });
            });
        }

        // 3. Date & Time filters
        if ($fromDate) {
            $fDateTime = $fromTime ? "$fromDate $fromTime" : "$fromDate 00:00:00";
            $query->where('added_at', '>=', $fDateTime);
        }
        if ($toDate) {
            $tDateTime = $toTime ? "$toDate $toTime" : "$toDate 23:59:59";
            $query->where('added_at', '<=', $tDateTime);
        }

        // 4. Stock Type filter (IN/OUT perspective conversion for HEAD user)
        if ($stockType) {
            $opp = ($stockType === 'IN') ? 'OUT' : 'IN';
            $query->where(function (Builder $q) use ($stockType, $opp, $headId) {
                $q->where(function (Builder $sq) use ($opp, $headId) {
                    $sq->whereIn('entry_type', ['HEADTOHEAD', 'EMPTOHEAD'])
                        ->where('given_to', $headId)
                        ->where('stock_type', $opp);
                })->orWhere(function (Builder $sq) use ($stockType, $headId) {
                    $sq->where(function (Builder $ssq) use ($headId) {
                        $ssq->whereNotIn('entry_type', ['HEADTOHEAD', 'EMPTOHEAD'])
                            ->orWhere('given_to', '!=', $headId);
                    })->where('stock_type', $stockType);
                });
            });
        }

        $totalCount = $query->count();

        // 5. Pagination & Fetch
        $records = $query->orderBy('stock_id', 'desc')
            ->skip(($pageNo - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        // 6. Map balances chronologically using JSON snapshots
        $formattedRecords = $records->map(function ($stock) use ($userId, $retailerId, $headId) {
            $isAutoEntryToTouch = false;

            if (in_array($stock->entry_type, ['EMPTOEMP', 'EMPTOHEAD', 'ANOTHERHEADTOEMP', 'HEADTOHEAD'])) {
                if ($stock->type === 'HEADTOHEAD' && ($stock->given_to == $headId || ($userId && $stock->given_by == $userId))) {
                    $isAutoEntryToTouch = true;
                }
                if ($stock->type === 'EMPTOHEAD' && (!$userId || $stock->given_by != $userId)) {
                    $isAutoEntryToTouch = true;
                }
                if ($stock->type === 'ANOTHERHEADTOEMP' && $userId && $stock->given_to == $userId) {
                    $isAutoEntryToTouch = true;
                }
            }

            $purity = $stock->purity;
            $touch = $stock->touch;
            $wasteValue = $stock->waste_value;

            if ($isAutoEntryToTouch) {
                $purity = $stock->to_purity ?? $purity;
                $touch = $stock->to_touch ?? $touch;
                $wasteValue = $stock->to_waste_value ?? $wasteValue;
            }

            // Resolve perspective stock type
            $displayStockType = $stock->stock_type;
            if (in_array($stock->entry_type, ['HEADTOHEAD', 'EMPTOHEAD']) && $stock->given_to == $headId && $stock->stock_type === 'OUT') {
                $displayStockType = 'IN';
            }

            // Resolve OB/CB balances
            $obGrams = 0;
            $cbGrams = 0;
            $obPurity = 0;
            $cbPurity = 0;

            $snapshot = $stock->obcb_details;
            if (is_array($snapshot)) {
                if ($userId) {
                    $side = ($stock->given_by == $userId) ? 'given_by_details' : 'given_to_details';
                    $obGrams = $snapshot[$side]['ob']['item_details']['ob_grams'] ?? 0;
                    $cbGrams = $snapshot[$side]['cb']['item_details']['cb_grams'] ?? 0;
                    $obPurity = $snapshot[$side]['ob']['item_details']['ob_purity'] ?? 0;
                    $cbPurity = $snapshot[$side]['cb']['item_details']['cb_purity'] ?? 0;
                } elseif ($retailerId) {
                    $side = ($stock->retailer_id == $retailerId) ? 'retailer' : 'to_retailer';
                    $obGrams = $snapshot[$side]['ob']['tot_ob_grams'] ?? 0;
                    $cbGrams = $snapshot[$side]['cb']['tot_cb_grams'] ?? 0;
                    $obPurity = $snapshot[$side]['ob']['tot_ob_purity'] ?? 0;
                    $cbPurity = $snapshot[$side]['cb']['tot_cb_purity'] ?? 0;
                } else {
                    // Default HEAD view
                    $side = ($stock->given_by == $headId) ? 'given_by_details' : 'given_to_details';
                    $obGrams = $snapshot[$side]['ob']['item_details']['ob_grams'] ?? 0;
                    $cbGrams = $snapshot[$side]['cb']['item_details']['cb_grams'] ?? 0;
                    $obPurity = $snapshot[$side]['ob']['item_details']['ob_purity'] ?? 0;
                    $cbPurity = $snapshot[$side]['cb']['item_details']['cb_purity'] ?? 0;
                }
            } else {
                // Fallback to columns if no snapshot exists
                if ($stock->stock_type === 'OUT') {
                    $obGrams = $stock->given_by_item_grams_op;
                    $obPurity = $stock->given_by_item_purity_op;
                    $cbGrams = $this->sub($obGrams, $stock->grams);
                    $cbPurity = $this->sub($obPurity, $purity);
                } else {
                    $obGrams = $stock->given_by_item_grams_op;
                    $obPurity = $stock->given_to_item_purity_op;
                    $cbGrams = $this->add($stock->given_to_item_grams_op, $stock->grams);
                    $cbPurity = $this->add($stock->given_to_item_purity_op, $purity);
                }
            }

            return [
                'stock_id' => $stock->stock_id,
                'given_by_name' => $stock->givenBy->name ?? '-',
                'given_to_name' => $stock->givenTo->name ?? '-',
                'stock_type' => $displayStockType,
                'entry_type' => $stock->entry_type,
                'item_name' => $stock->item->item_name ?? '-',
                'to_item_name' => $stock->toItem->item_name ?? null,
                'grams' => (float) $stock->grams,
                'touch' => (float) $touch,
                'purity' => (float) $purity,
                'ob_grams' => (float) $obGrams,
                'cb_grams' => (float) $cbGrams,
                'ob_purity' => (float) $obPurity,
                'cb_purity' => (float) $cbPurity,
                'remarks' => $stock->remarks,
                'added_at' => $stock->added_at->toDateTimeString(),
            ];
        });

        return [
            'total_count' => $totalCount,
            'page_no' => (int) $pageNo,
            'page_size' => (int) $pageSize,
            'records' => $formattedRecords,
        ];
    }

    private function add($a, $b)
    {
        return bcadd((string)$a, (string)$b, 4);
    }

    private function sub($a, $b)
    {
        return bcsub((string)$a, (string)$b, 4);
    }
}
