<?php

namespace App\Services;

use App\Models\StockDetails;
use App\Models\UserDetail;
use App\Models\RetailerUser; 
use App\Models\UsersItemsMapping;
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

    /**
     * Fetch Head Stocks Summary
     */
    public function getHeadStocks(array $filters, int $headId): array
    {
        $fromDate = $filters['head_txn_from_date'] ?? null;
        $fromTime = $filters['head_txn_from_time'] ?? null;

        $itemsList = [];
        $totalGrams = 0;
        $totalPurity = 0;

        // Fetch User Cash Balance
        $user = UserDetail::where('user_id', $headId)->first();
        $cashBalance = $user ? ($user->rak_cash_balance + $user->rak_rtgs_balance) : 0;

        // Fetch Active Orders Weight (Hardcoded to 0 because order_details table does not exist yet)
        $activeOrdersWeight = 0;

        // Check if Date/Time filter is applied
        if (($fromDate && $fromDate != date('Y-m-d')) || ($fromDate == date('Y-m-d') && $fromTime)) {
            $dateTime = $fromDate . ' ' . ($fromTime ?? '00:00:00');
            
            // Time-Travel Logic
            $mappings = UsersItemsMapping::with('item')->where('user_id', $headId)->get()->groupBy('item_id');
            
            foreach ($mappings as $itemId => $mapGroup) {
                $item = $mapGroup->first()->item;
                $grams = 0;
                $purity = 0;

                $headStock = StockDetails::where(function ($q) use ($headId) {
                        $q->where('given_by', $headId)->orWhere('given_to', $headId);
                    })
                    ->whereNotNull('given_by_item_grams_op')
                    ->where('added_at', '<=', $dateTime)
                    ->where('item_id', $itemId)
                    ->orderBy('stock_id', 'desc')
                    ->first();

                if ($headStock) {
                    if ($headStock->given_by == $headId) {
                        $grams = $headStock->given_by_item_grams_op - $headStock->grams;
                        $purity = $headStock->given_by_item_purity_op - $headStock->purity;
                    } else {
                        $grams = $headStock->given_to_item_grams_op + $headStock->grams;
                        $purity = $headStock->given_to_item_purity_op + $headStock->purity;
                    }
                }

                $percentage = $grams > 0 ? ($purity / $grams) * 100 : 0;

                $itemsList[] = [
                    'item_id' => $itemId,
                    'item_name' => $item ? $item->item_name : '',
                    'grams' => round((float)$grams, 3),
                    'percentage' => round((float)$percentage, 3),
                    'purity' => round((float)$purity, 3),
                ];
                $totalGrams += $grams;
                $totalPurity += $purity;
            }
        } else {
            // Live Logic
            $mappings = UsersItemsMapping::with('item')->where('user_id', $headId)->get()->groupBy('item_id');
            foreach ($mappings as $itemId => $mapGroup) {
                $map = $mapGroup->first();
                $item = $map->item;
                $grams = $map->item_grams_total;
                $purity = $map->item_purity_total;

                $percentage = $grams > 0 ? ($purity / $grams) * 100 : 0;

                $itemsList[] = [
                    'item_id' => $itemId,
                    'item_name' => $item ? $item->item_name : '',
                    'grams' => round((float)$grams, 3),
                    'percentage' => round((float)$percentage, 3),
                    'purity' => round((float)$purity, 3),
                ];
                $totalGrams += $grams;
                $totalPurity += $purity;
            }
        }

        return [
            'items' => $itemsList,
            'totals' => [
                'grams' => round((float)$totalGrams, 3),
                'purity' => round((float)$totalPurity, 3),
            ],
            'cash_balance' => round((float)$cashBalance, 2),
            'active_orders' => round((float)$activeOrdersWeight, 3),
        ];
    }

    /**
     * Fetch Paginated Transaction History
     */
    public function getStockHistory(array $filters, int $headId): array
    {
        $employeeId = $filters['employee_id'] ?? null;
        $itemId = $filters['item_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $type = $filters['type'] ?? null;
        $perPage = $filters['page_size'] ?? 10;
        
        $query = StockDetails::with(['item', 'givenBy', 'givenTo', 'addedBy'])
            ->where('is_completed', 0)
            ->where('is_freezed', 0)
            ->where(function($q) use ($headId) {
                $q->where('given_by', $headId)->orWhere('given_to', $headId);
            });
            
        if ($employeeId) {
            $query->where(function($q) use ($employeeId) {
                $q->where('given_by', $employeeId)->orWhere('given_to', $employeeId);
            });
        }
        
        if ($itemId) {
            $query->where('item_id', $itemId);
        }
        
        if ($type) {
            if ($type == 'OUT') {
                $query->where('given_by', $headId);
            } elseif ($type == 'IN') {
                $query->where('given_to', $headId);
            }
        }
        
        if ($fromDate) {
            $query->whereDate('added_at', '>=', date('Y-m-d', strtotime($fromDate)));
        }
        
        if ($toDate) {
            $query->whereDate('added_at', '<=', date('Y-m-d', strtotime($toDate)));
        }

        // Calculate Totals before Pagination
        $totalsQuery = clone $query;
        $totalGrams = $totalsQuery->sum('grams');
        $totalPurity = $totalsQuery->sum('purity');
        $totalPcs = $totalsQuery->sum('no_of_pcs');

        $paginated = $query->orderBy('stock_id', 'desc')->paginate($perPage);

        // Format the output specifically for the frontend Transaction History table
        $paginated->getCollection()->transform(function ($txn) use ($headId) {
            $stockType = '';
            $userName = '';
            $userId = null;
            $remarks = $txn->remarks;
            
            if ($txn->given_by == $headId) {
                $stockType = 'OUT';
                $userName = $txn->givenTo ? $txn->givenTo->name : $txn->remarks;
                $userId = $txn->given_to;
            } elseif ($txn->given_to == $headId) {
                $stockType = 'IN';
                $userName = $txn->givenBy ? $txn->givenBy->name : $txn->remarks;
                $userId = $txn->given_by;
            }

            // Clean up remarks if they were used for the User column (e.g., MELTING)
            if ($userName === $remarks && !$txn->givenTo && !$txn->givenBy) {
                $remarks = '';
            }

            $itemName = $txn->item ? $txn->item->item_name : '';
            if ($txn->type === 'ITEMCHANGE' || $txn->type === 'ITEMCONVERSION') {
                $toItemName = $txn->toItem ? $txn->toItem->item_name : '';
                if ($toItemName) {
                    $itemName = $itemName . ' => ' . $toItemName;
                }
                
                $fromUser = $txn->givenBy ? $txn->givenBy->name : '';
                $toUser = $txn->givenTo ? $txn->givenTo->name : '';
                // Append conversion remarks if it's a direct transfer
                $remarks = $remarks ?: "From : {$fromUser} => To : {$toUser}";
            }

            return [
                'id' => $txn->stock_id,
                'item_name' => $itemName,
                'stock_type' => $stockType,
                'grams' => round((float)$txn->grams, 3),
                'pcs' => (int)$txn->no_of_pcs,
                'touch' => round((float)$txn->touch, 3),
                'wastage' => round((float)($txn->waste_total ?? $txn->waste_value ?? 0), 3),
                'purity' => round((float)$txn->purity, 3),
                'user_id' => $userId,
                'user' => $userName,
                'remarks' => $remarks
            ];
        });

        return [
            'totals' => [
                'grams' => round((float)$totalGrams, 3),
                'purity' => round((float)$totalPurity, 3),
                'pcs' => (int)$totalPcs
            ],
            'transactions' => $paginated
        ];
    }
}
