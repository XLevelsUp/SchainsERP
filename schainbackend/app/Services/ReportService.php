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
    public function getConsolidatedReport(array $filters, int $headId): array
    {
        $employeeId = $filters['user_id'] ?? $filters['retailer_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $fromTime = $filters['from_time'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $toTime = $filters['to_time'] ?? null;
        $itemId = $filters['item_id'] ?? null;
        
        $pageSize = $filters['page_size'] ?? 1000;
        $pageNoOut = $filters['page_no_out'] ?? 1;
        $pageNoIn = $filters['page_no_in'] ?? 1;

        $userId = null;
        $retailerId = null;

        if ($employeeId) {
            if (strpos($employeeId, 'retailer_') === 0) {
                $retailerId = (int) str_replace('retailer_', '', $employeeId);
            } elseif (strpos($employeeId, 'user_') === 0) {
                $userId = (int) str_replace('user_', '', $employeeId);
            } else {
                if (isset($filters['retailer_id'])) {
                    $retailerId = (int) $employeeId;
                } else {
                    $userId = (int) $employeeId;
                }
            }
        }

        $query = StockDetails::with(['item', 'givenTo', 'givenBy', 'toItem'])
            ->where('is_hided', 0)
            ->where('is_freezed', 0);

        if ($fromDate) {
            $fDateTime = $fromTime ? "$fromDate $fromTime" : "$fromDate 00:00:00";
            $query->where('added_at', '>=', $fDateTime);
        }
        if ($toDate) {
            $tDateTime = $toTime ? "$toDate $toTime" : "$toDate 23:59:59";
            $query->where('added_at', '<=', $tDateTime);
        }
        if ($itemId) {
            $query->where('item_id', $itemId);
        }

        // Clone base query for OUT and IN
        $outQuery = clone $query;
        $inQuery = clone $query;

        // Apply OUT Conditions
        $outQuery->where(function ($q) use ($userId, $retailerId) {
            if ($retailerId) {
                $q->where(function ($q2) use ($retailerId) {
                    $q2->where('retailer_id', $retailerId)
                       ->orWhere('to_retailer_id', $retailerId);
                });
                $q->where(function ($q3) use ($retailerId) {
                    $q3->where(function ($q4) use ($retailerId) {
                        $q4->where('entry_type', 'EMPTOEMP')
                           ->where('to_retailer_id', $retailerId)
                           ->where('is_receiver_completed', 0);
                    })->orWhere(function ($q4) {
                        $q4->where('entry_type', '!=', 'EMPTOEMP')
                           ->where('is_completed', 0);
                    });
                });
                $q->where('entry_type', '!=', 'EMPTOHEAD');
                $q->where(function ($q3) use ($retailerId) {
                    $q3->where('entry_type', '!=', 'ANOTHERHEADTOEMP')
                       ->orWhere(function ($q4) use ($retailerId) {
                           $q4->where('retailer_id', $retailerId)
                              ->orWhere('to_retailer_id', $retailerId);
                       });
                });
                $q->where(function ($q3) use ($retailerId) {
                    $q3->where('entry_type', '!=', 'HEADTOHEAD')
                       ->orWhere('retailer_id', $retailerId);
                });
            } elseif ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->where('given_to', $userId)
                       ->orWhere('given_by', $userId);
                });
                $q->where(function ($q3) use ($userId) {
                    $q3->where(function ($q4) use ($userId) {
                        $q4->where('entry_type', 'EMPTOEMP')
                           ->where('given_to', $userId)
                           ->where('is_receiver_completed', 0);
                    })->orWhere(function ($q4) {
                        $q4->where('entry_type', '!=', 'EMPTOEMP')
                           ->where('is_completed', 0);
                    });
                });
                $q->where('entry_type', '!=', 'EMPTOHEAD');
                $q->where(function ($q3) use ($userId) {
                    $q3->where('entry_type', '!=', 'ANOTHERHEADTOEMP')
                       ->orWhere(function ($q4) use ($userId) {
                           $q4->where('given_by', $userId)
                              ->orWhere('given_to', $userId);
                       });
                });
                $q->where(function ($q3) use ($userId) {
                    $q3->where('entry_type', '!=', 'HEADTOHEAD')
                       ->orWhere('given_to', $userId);
                });
            }
            
            $outEntryTypes = ['NORMAL', 'CashToGold', 'OUT_CASH_CONVERTER', 'IN_CASH_CONVERTER', 'GOLDCASHCONVERSION'];
            foreach ($outEntryTypes as $type) {
                $q->where(function ($q3) use ($type) {
                    $q3->where('entry_type', '!=', $type)
                       ->orWhere('stock_type', 'OUT');
                });
            }
        });

        // Apply IN Conditions
        $inQuery->where(function ($q) use ($userId, $retailerId) {
            if ($retailerId) {
                $q->where(function ($q2) use ($retailerId) {
                    $q2->where('retailer_id', $retailerId)
                       ->orWhere('to_retailer_id', $retailerId);
                });
                $q->where(function ($q3) use ($retailerId) {
                    $q3->where(function ($q4) use ($retailerId) {
                        $q4->where('entry_type', 'EMPTOEMP')
                           ->where('retailer_id', $retailerId)
                           ->where('is_receiver_completed', 0);
                    })->orWhere(function ($q4) {
                        $q4->where('entry_type', '!=', 'EMPTOEMP')
                           ->where('is_completed', 0);
                    });
                });
                $q->where(function ($q3) use ($retailerId) {
                    $q3->where('entry_type', '!=', 'HEADTOHEAD')
                       ->orWhere('to_retailer_id', $retailerId);
                });
            } elseif ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->where('given_to', $userId)
                       ->orWhere('given_by', $userId);
                });
                $q->where(function ($q3) use ($userId) {
                    $q3->where(function ($q4) use ($userId) {
                        $q4->where('entry_type', 'EMPTOEMP')
                           ->where('given_by', $userId)
                           ->where('is_receiver_completed', 0);
                    })->orWhere(function ($q4) {
                        $q4->where('entry_type', '!=', 'EMPTOEMP')
                           ->where('is_completed', 0);
                    });
                });
                $q->where(function ($q3) use ($userId) {
                    $q3->where('entry_type', '!=', 'HEADTOHEAD')
                       ->orWhere('given_to', $userId);
                });
            }
            
            $inEntryTypes = ['NORMAL', 'CashToGold', 'OUT_CASH_CONVERTER', 'IN_CASH_CONVERTER', 'GOLDCASHCONVERSION'];
            foreach ($inEntryTypes as $type) {
                $q->where(function ($q3) use ($type) {
                    $q3->where('entry_type', '!=', $type)
                       ->orWhere('stock_type', 'IN');
                });
            }
        });

        // Clone queries for totals before pagination
        $outTotalsQuery = clone $outQuery;
        $inTotalsQuery = clone $inQuery;

        $outTotalCount = $outQuery->count();
        $inTotalCount = $inQuery->count();

        $outRecords = $outQuery->orderBy('stock_id', 'desc')
            ->skip(($pageNoOut - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        $inRecords = $inQuery->orderBy('stock_id', 'desc')
            ->skip(($pageNoIn - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        // DB-level Aggregation Function (Memory efficient for large datasets)
        $calculateTotals = function ($query, $isOut) use ($userId, $retailerId) {
            // PostgreSQL requires proper casting or type checking in CASE WHEN.
            // Since we added columns to DB, we can do raw SQL aggregation safely.
            
            $targetId = $retailerId ?: $userId;
            $idColumn = $retailerId ? 'retailer_id' : ($isOut ? 'given_to' : 'given_by');

            $totals = $query->selectRaw("
                SUM(CASE WHEN entry_type != 'GOLDCASHCONVERSION' THEN grams ELSE 0 END) as tot_grams,
                SUM(CASE 
                    WHEN entry_type != 'GOLDCASHCONVERSION' THEN
                        CASE 
                            WHEN to_touch > 0 AND entry_type IN ('EMPTOEMP', 'EMPTOHEAD', 'ANOTHERHEADTOEMP', 'HEADTOHEAD') 
                                 AND ($idColumn = ?) THEN to_purity
                            ELSE purity 
                        END
                    ELSE 0 
                END) as tot_purity,
                SUM(CASE 
                    WHEN entry_type != 'GOLDCASHCONVERSION' THEN
                        CASE 
                            WHEN to_touch > 0 AND entry_type IN ('EMPTOEMP', 'EMPTOHEAD', 'ANOTHERHEADTOEMP', 'HEADTOHEAD') 
                                 AND ($idColumn = ?) THEN to_waste_value
                            ELSE waste_value 
                        END
                    ELSE 0 
                END) as tot_wastage
            ", [$targetId, $targetId])->toBase()->first();

            return [
                'grams' => round($totals->tot_grams ?? 0, 3),
                'purity' => round($totals->tot_purity ?? 0, 3),
                'wastage' => round($totals->tot_wastage ?? 0, 3),
            ];
        };

        return [
            'summary' => [
                'out' => $calculateTotals($outTotalsQuery, true),
                'in' => $calculateTotals($inTotalsQuery, false),
            ],
            'out_details' => [
                'total_count' => $outTotalCount,
                'page_no' => $pageNoOut,
                'page_size' => $pageSize,
                'records' => $outRecords->map(function ($record) {
                    return [
                        'stock_id' => $record->stock_id,
                        'entry_type' => $record->entry_type,
                        'stock_type' => $record->stock_type,
                        'grams' => $record->grams,
                        'touch' => $record->touch,
                        'purity' => $record->purity,
                        'waste_value' => $record->waste_value,
                        'added_at' => $record->added_at,
                        'remarks' => $record->remarks,
                        'item_id' => $record->item_id,
                        'item_name' => $record->item ? $record->item->item_name : null,
                        'given_by_name' => $record->givenBy ? $record->givenBy->name : null,
                        'given_to_name' => $record->givenTo ? $record->givenTo->name : null,
                    ];
                }),
            ],
            'in_details' => [
                'total_count' => $inTotalCount,
                'page_no' => $pageNoIn,
                'page_size' => $pageSize,
                'records' => $inRecords->map(function ($record) {
                    return [
                        'stock_id' => $record->stock_id,
                        'entry_type' => $record->entry_type,
                        'stock_type' => $record->stock_type,
                        'grams' => $record->grams,
                        'touch' => $record->touch,
                        'purity' => $record->purity,
                        'waste_value' => $record->waste_value,
                        'added_at' => $record->added_at,
                        'remarks' => $record->remarks,
                        'item_id' => $record->item_id,
                        'item_name' => $record->item ? $record->item->item_name : null,
                        'given_by_name' => $record->givenBy ? $record->givenBy->name : null,
                        'given_to_name' => $record->givenTo ? $record->givenTo->name : null,
                    ];
                }),
            ]
        ];
    }

    public function getIdWiseStockReport(int $stockId, int $headId): array
    {
        return \Illuminate\Support\Facades\Cache::remember("stock_report_id_wise_{$stockId}", 3600, function () use ($stockId, $headId) {
            $baseStock = StockDetails::with(['item', 'toItem', 'givenBy', 'givenTo', 'bill'])->findOrFail($stockId);
            
            if ($baseStock->stock_type === 'OUT' && $baseStock->stock_in_id) {
                $parentLot = StockDetails::with(['item', 'givenBy', 'givenTo', 'bill'])->findOrFail($baseStock->stock_in_id);
            } else {
                $parentLot = $baseStock;
            }

            $transactions = StockDetails::with(['item', 'toItem', 'givenBy', 'givenTo', 'bill'])
                ->where('stock_in_id', $parentLot->stock_id)
                ->orderBy('added_at', 'asc')
                ->get();
            
            $formattedTransactions = $transactions->map(function($txn) use ($headId) {
                $itemName = $txn->item ? $txn->item->item_name : '';
                if ($txn->type === 'ITEMCHANGE' || $txn->type === 'ITEMCONVERSION') {
                    $toItemName = $txn->toItem ? $txn->toItem->item_name : '';
                    if ($toItemName) {
                        $itemName = $itemName . ' => ' . $toItemName;
                    }
                }
                
                return [
                    'id' => $txn->stock_id,
                    'added_at' => (string)$txn->added_at,
                    'item_name' => $itemName,
                    'stock_type' => $txn->stock_type,
                    'entry_type' => $txn->entry_type,
                    'type' => $txn->type,
                    'grams' => round((float)$txn->grams, 3),
                    'touch' => round((float)$txn->touch, 3),
                    'mtouch' => round((float)$txn->mtouch, 3),
                    'wastage' => round((float)($txn->waste_total ?? $txn->waste_value ?? 0), 3),
                    'purity' => round((float)$txn->purity, 3),
                    'given_by' => $txn->givenBy ? $txn->givenBy->name : '-',
                    'given_to' => $txn->givenTo ? $txn->givenTo->name : '-',
                    'remarks' => $txn->remarks,
                    'item_remarks' => $txn->item_remarks,
                    'bill_id' => $txn->bill_id,
                ];
            })->toArray();

            $totalConsumed = $transactions->sum('grams');
            
            return [
                'stock_summary' => [
                    'queried_id' => $baseStock->stock_id,
                    'queried_type' => $baseStock->stock_type,
                    'item_name' => $baseStock->item->item_name ?? '-',
                ],
                'lot_details' => [
                    'lot_id' => $parentLot->stock_id,
                    'item_name' => $parentLot->item->item_name ?? '-',
                    'added_at' => (string)$parentLot->added_at,
                    'original_grams' => (float)$parentLot->grams,
                    'purity' => (float)$parentLot->purity,
                    'touch' => (float)$parentLot->touch,
                    'current_balance' => (float)$parentLot->balance,
                    'lot_creator' => $parentLot->givenBy->name ?? '-',
                ],
                'quantity_summary' => [
                    'total_received' => (float)$parentLot->grams,
                    'total_consumed' => (float)$totalConsumed,
                    'reconciled_balance' => round((float)($parentLot->grams - $totalConsumed), 3),
                    'actual_balance' => round((float)$parentLot->balance, 3),
                ],
                'transactions' => $formattedTransactions
            ];
        });
    }

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
    /**
     * Get Live Metal Balance for a User
     */
    public function getLiveMetalBalanceReport(array $params, int $targetUserId): array
    {
        $date = $params['date'] ?? null;
        $time = $params['time'] ?? null;
        $pageSize = $params['per_page'] ?? 50;

        $query = StockDetails::with(['givenBy'])
            ->where('given_to', $targetUserId)
            ->where('remarks', '!=', 'CASH_TO_GOLD')
            ->whereIn('entry_type', ['NORMAL', 'EMPTOHEAD', 'HEADTOHEAD'])
            ->where(function ($q) {
                $q->where(function ($sq1) {
                    $sq1->where('entry_type', 'NORMAL')->where('item_id', 2);
                })->orWhere(function ($sq2) {
                    $sq2->where('entry_type', '!=', 'NORMAL')->where('to_item_id', 2);
                });
            })
            ->where(function ($q) {
                $q->where(function ($sq1) {
                    $sq1->whereIn('type', ['ITEMCHANGE', 'ITEMCONVERSION'])
                        ->where('stock_type', '!=', 'OUT');
                })->orWhere(function ($sq2) {
                    $sq2->where(function ($sq3) {
                        $sq3->whereNotIn('type', ['ITEMCHANGE', 'ITEMCONVERSION'])
                            ->orWhereNull('type');
                    })->whereIn('stock_type', ['IN', 'OUT']);
                });
            });

        if ($date && $time) {
            $dateTime = date('Y-m-d H:i:s', strtotime("$date $time"));
            
            // Replicate the legacy subquery exactly
            $query->selectRaw("stock_details.*, IFNULL((SELECT SUM(stock.grams) FROM `stock_details` as stock WHERE stock.stock_in_id = stock_details.stock_id and added_at <= ?), 0) as used_grams", [$dateTime])
                  ->havingRaw('grams - used_grams > 0')
                  ->orderBy('stock_in_id', 'desc');
        } else {
            $query->select('stock_details.*')
                  ->where('balance', '>', 0);
        }

        $paginated = $query->paginate($pageSize);

        $totalGrams = 0;
        $totalPurity = 0;

        $records = $paginated->getCollection()->map(function ($metal) use ($date, $time, &$totalGrams, &$totalPurity) {
            if ($date && $time) {
                // used_grams is populated by the selectRaw subquery
                $balance = round($metal->grams, 3) - round($metal->used_grams ?? 0, 3);
            } else {
                $balance = $metal->balance;
            }
            
            $balance = round($balance, 3);
            $purity = round($balance * $metal->touch / 100, 3);

            $totalGrams += $balance;
            $totalPurity += $purity;

            return [
                'stock_id' => $metal->stock_id,
                'balance' => $balance,
                'touch' => $metal->touch,
                'purity' => $purity,
                'party_name' => $metal->givenBy ? $metal->givenBy->name : '',
                'added_at' => (string)$metal->added_at
            ];
        });

        // The overall totals logic in Yii2 was just the sum of the CURRENT page. 
        // If we want total of all pages, we would have to query without limit.
        // I will replicate the Yii2 logic exactly (sum of current page).
        
        return [
            'summary' => [
                'total_grams' => round($totalGrams, 3),
                'total_purity' => round($totalPurity, 3)
            ],
            'records' => $records,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        ];
    }
}
