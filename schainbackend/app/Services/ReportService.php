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
        $fromDate = !empty($filters['from_date']) ? $filters['from_date'] : null;
        $fromTime = !empty($filters['from_time']) ? $filters['from_time'] : null;
        $toDate = !empty($filters['to_date']) ? $filters['to_date'] : null;
        $toTime = !empty($filters['to_time']) ? $filters['to_time'] : null;

        if (!$toDate && !empty($filters['closing_date'])) {
            $toDate = $filters['closing_date'];
            $toTime = !empty($filters['closing_time']) ? $filters['closing_time'] : null;
        }
        $itemIds = $filters['item_id'] ?? null;
        if ($itemIds && !is_array($itemIds)) {
            $itemIds = [$itemIds];
        }
        
        $pageSize = $filters['page_size'] ?? 1000;
        $pageNoOut = $filters['page_no_out'] ?? 1;
        $pageNoIn = $filters['page_no_in'] ?? 1;

        $userId = null;
        $retailerId = null;

        $type4 = $filters['type4'] ?? null;
        if ($type4 === 'HEAD') {
            $userId = (int) ($filters['head_user_id'] ?? $headId);
        } elseif ($type4 === 'EMPLOYEE') {
            $userId = (int) ($filters['employee_id'] ?? $filters['user_id'] ?? null);
        } elseif ($type4 === 'CUSTOMER' || $type4 === 'RETAILER') {
            $retailerId = (int) ($filters['customer_id'] ?? $filters['retailer_id'] ?? null);
        } else if ($employeeId) {
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

        $mappedEmployeeIds = \App\Models\HeadEmployeeMapping::where('head_id', $headId)->pluck('employee_id')->toArray();
        $allMappedIds = array_unique(array_merge([$headId], $mappedEmployeeIds));

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
        if (!empty($itemIds)) {
            $query->whereIn('item_id', $itemIds);
        }

        // Clone base query for OUT and IN
        $outQuery = clone $query;
        $inQuery = clone $query;

        // Apply OUT Conditions
        $outQuery->where(function ($q) use ($userId, $retailerId, $allMappedIds) {
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
                           ->where(function ($q5) {
                               $q5->where('is_completed', 0)
                                  ->orWhereIn('type', ['ITEMCHANGE', 'ITEMCONVERSION']);
                           });
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
                           ->where(function ($q5) {
                               $q5->where('is_completed', 0)
                                  ->orWhereIn('type', ['ITEMCHANGE', 'ITEMCONVERSION']);
                           });
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
            } else {
                $q->where(function ($q2) use ($allMappedIds) {
                    $q2->whereIn('given_to', $allMappedIds)
                       ->orWhereIn('given_by', $allMappedIds);
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
        $inQuery->where(function ($q) use ($userId, $retailerId, $allMappedIds) {
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
                           ->where(function ($q5) {
                               $q5->where('is_completed', 0)
                                  ->orWhereIn('type', ['ITEMCHANGE', 'ITEMCONVERSION']);
                           });
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
                           ->where(function ($q5) {
                               $q5->where('is_completed', 0)
                                  ->orWhereIn('type', ['ITEMCHANGE', 'ITEMCONVERSION']);
                           });
                    });
                });
                $q->where(function ($q3) use ($userId) {
                    $q3->where('entry_type', '!=', 'HEADTOHEAD')
                       ->orWhere('given_to', $userId);
                });
            } else {
                $q->where(function ($q2) use ($allMappedIds) {
                    $q2->whereIn('given_to', $allMappedIds)
                       ->orWhereIn('given_by', $allMappedIds);
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

        if (isset($filters['is_export']) && $filters['is_export']) {
            $outRecords = $outQuery->orderBy('stock_id', 'desc')->get();
            $inRecords = $inQuery->orderBy('stock_id', 'desc')->get();
        } else {
            $outRecords = $outQuery->orderBy('stock_id', 'desc')
                ->skip(($pageNoOut - 1) * $pageSize)
                ->take($pageSize)
                ->get();
    
            $inRecords = $inQuery->orderBy('stock_id', 'desc')
                ->skip(($pageNoIn - 1) * $pageSize)
                ->take($pageSize)
                ->get();
        }

        // DB-level Aggregation Function (Memory efficient for large datasets)
        $calculateTotals = function ($query, $isOut) use ($userId, $retailerId, $headId) {
            // PostgreSQL requires proper casting or type checking in CASE WHEN.
            // Since we added columns to DB, we can do raw SQL aggregation safely.
            
            $targetId = $retailerId ?: ($userId ?: $headId);
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
                    $itemName = $record->item ? $record->item->item_name : null;
                    if (in_array($record->type, ['ITEMCHANGE', 'ITEMCONVERSION']) && $record->toItem) {
                        $itemName = "{$itemName} => {$record->toItem->item_name}";
                    }
                    return [
                        'stock_id' => $record->stock_id,
                        'entry_type' => $record->entry_type,
                        'stock_type' => $record->stock_type,
                        'type' => $record->type,
                        'grams' => $record->grams,
                        'touch' => $record->touch,
                        'purity' => $record->purity,
                        'waste_value' => $record->waste_value,
                        'added_at' => $record->added_at,
                        'remarks' => $record->remarks,
                        'item_id' => $record->item_id,
                        'item_name' => $itemName,
                        'to_item_id' => $record->to_item_id,
                        'to_item_name' => $record->toItem ? $record->toItem->item_name : null,
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
                    $itemName = $record->item ? $record->item->item_name : null;
                    if (in_array($record->type, ['ITEMCHANGE', 'ITEMCONVERSION']) && $record->toItem) {
                        $itemName = "{$itemName} <= {$record->toItem->item_name}";
                    }
                    return [
                        'stock_id' => $record->stock_id,
                        'entry_type' => $record->entry_type,
                        'stock_type' => $record->stock_type,
                        'type' => $record->type,
                        'grams' => $record->grams,
                        'touch' => $record->touch,
                        'purity' => $record->purity,
                        'waste_value' => $record->waste_value,
                        'added_at' => $record->added_at,
                        'remarks' => $record->remarks,
                        'item_id' => $record->item_id,
                        'item_name' => $itemName,
                        'to_item_id' => $record->to_item_id,
                        'to_item_name' => $record->toItem ? $record->toItem->item_name : null,
                        'given_by_name' => $record->givenBy ? $record->givenBy->name : null,
                        'given_to_name' => $record->givenTo ? $record->givenTo->name : null,
                    ];
                }),
            ]
        ];
    }

    public function getIdWiseStockReport(int $stockId, int $headId): array
    {
        $baseStock = StockDetails::with(['item', 'toItem', 'givenBy', 'givenTo', 'bill'])->findOrFail($stockId);
        
        if ($baseStock->stock_type === 'OUT' && $baseStock->stock_in_id) {
            $parentLot = StockDetails::with(['item', 'givenBy', 'givenTo', 'bill'])->findOrFail($baseStock->stock_in_id);
        } else {
            $parentLot = $baseStock;
        }

        $transactions = StockDetails::with(['item', 'toItem', 'givenBy', 'givenTo', 'bill'])
            ->where(function ($q) use ($parentLot) {
                $q->where('stock_id', $parentLot->stock_id)
                  ->orWhere('stock_in_id', $parentLot->stock_id);
            })
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

        $mappedEmployeeIds = \App\Models\HeadEmployeeMapping::where('head_id', $headId)->pluck('employee_id')->toArray();
        $allMappedIds = array_unique(array_merge([$headId], $mappedEmployeeIds));

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
            // General HEAD perspective filter: scope to Head and mapped users
            $query->where(function (Builder $q) use ($allMappedIds) {
                $q->whereIn('given_by', $allMappedIds)->orWhereIn('given_to', $allMappedIds);
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
        if (isset($filters['is_export']) && $filters['is_export']) {
            $records = $query->orderBy('stock_id', 'desc')->get();
        } else {
            $records = $query->orderBy('stock_id', 'desc')
                ->skip(($pageNo - 1) * $pageSize)
                ->take($pageSize)
                ->get();
        }

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
                } elseif ($retailerId) {
                    $side = ($stock->retailer_id == $retailerId) ? 'retailer' : 'to_retailer';
                } else {
                    // Default HEAD view
                    $side = ($stock->given_by == $headId) ? 'given_by_details' : 'given_to_details';
                }

                if (!isset($snapshot[$side])) {
                    $side = isset($snapshot['given_by_details']) ? 'given_by_details' : (isset($snapshot['given_to_details']) ? 'given_to_details' : array_key_first($snapshot));
                }

                if ($retailerId && isset($snapshot[$side]['ob']['tot_ob_grams'])) {
                    $obGrams = $snapshot[$side]['ob']['tot_ob_grams'] ?? 0;
                    $cbGrams = $snapshot[$side]['cb']['tot_cb_grams'] ?? 0;
                    $obPurity = $snapshot[$side]['ob']['tot_ob_purity'] ?? 0;
                    $cbPurity = $snapshot[$side]['cb']['tot_cb_purity'] ?? 0;
                } else {
                    // Check whether this record corresponds to to_item_details in snapshot
                    $useToItem = false;
                    if (isset($snapshot[$side]['ob']['to_item_details']['item_id']) && 
                        $snapshot[$side]['ob']['to_item_details']['item_id'] == $stock->item_id) {
                        $useToItem = true;
                    } elseif (in_array($stock->type, ['ITEMCHANGE', 'ITEMCONVERSION']) && $stock->stock_type === 'IN') {
                        $useToItem = true;
                    }

                    $itemKey = ($useToItem && isset($snapshot[$side]['ob']['to_item_details'])) ? 'to_item_details' : 'item_details';

                    $obGrams = $snapshot[$side]['ob'][$itemKey]['ob_grams'] ?? 0;
                    $cbGrams = $snapshot[$side]['cb'][$itemKey]['cb_grams'] ?? 0;
                    $obPurity = $snapshot[$side]['ob'][$itemKey]['ob_purity'] ?? 0;
                    $cbPurity = $snapshot[$side]['cb'][$itemKey]['cb_purity'] ?? 0;
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
                'type' => $stock->type,
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
                    // Item Change / Conversion write two rows on the user's
                    // own stock (given_by = given_to). Both carry the From
                    // item's OB in given_by_*_op and the To item's OB in
                    // given_to_*_op, so the IN row (the To item) has to read
                    // given_to_*_op. It used to fall into the given_by branch
                    // and show the From item's balance minus the grams.
                    $selfIn = in_array($headStock->type, ['ITEMCHANGE', 'ITEMCONVERSION'], true)
                        && $headStock->given_by == $headStock->given_to
                        && $headStock->stock_type === 'IN';

                    if ($headStock->given_by == $headId && !$selfIn) {
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
     * In or Out for one history row: from the first perspective user who
     * gave or received it, else its saved stock_type. See getStockHistory().
     */
    private function stockHistoryDirection($txn, array $perspectiveIds): string
    {
        if ($txn->type !== 'ITEMCHANGE' && $txn->type !== 'ITEMCONVERSION') {
            foreach ($perspectiveIds as $id) {
                if ($txn->given_by == $id && $txn->given_to != $id) {
                    return 'OUT';
                }
                if ($txn->given_to == $id && $txn->given_by != $id) {
                    return 'IN';
                }
            }
        }

        return $txn->stock_type ?: 'OUT';
    }

    /** The same rule as stockHistoryDirection(), as SQL: [expression, bindings]. */
    private function stockHistoryDirectionSql(array $perspectiveIds): array
    {
        $sql = "CASE WHEN type IN ('ITEMCHANGE', 'ITEMCONVERSION') THEN COALESCE(stock_type, 'OUT')";
        $bindings = [];
        foreach ($perspectiveIds as $id) {
            $sql .= " WHEN given_by = ? AND COALESCE(given_to, 0) <> ? THEN 'OUT'"
                . " WHEN given_to = ? AND COALESCE(given_by, 0) <> ? THEN 'IN'";
            array_push($bindings, (int) $id, (int) $id, (int) $id, (int) $id);
        }

        return [$sql . " ELSE COALESCE(stock_type, 'OUT') END", $bindings];
    }

    /**
     * Fetch Paginated Transaction History.
     *
     * @param int|null $viewerId the logged-in user; rows are labelled In/Out from their side
     */
    public function getStockHistory(array $filters, int $headId, ?int $viewerId = null): array
    {
        $employeeId = $filters['employee_id'] ?? null;
        $itemId = $filters['item_id'] ?? null;
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $type = $filters['type'] ?? null;
        $perPage = $filters['page_size'] ?? 10;
        
        $mappedEmployeeIds = \App\Models\HeadEmployeeMapping::where('head_id', $headId)->pluck('employee_id')->toArray();
        $allMappedIds = array_unique(array_merge([$headId], $mappedEmployeeIds));

        $query = StockDetails::with(['item', 'toItem', 'givenBy', 'givenTo', 'addedBy'])
            ->where('is_freezed', 0);
            
        if ($employeeId) {
            $query->where(function($q) use ($employeeId) {
                $q->where('given_by', $employeeId)->orWhere('given_to', $employeeId);
            });
        } else {
            $query->where(function($q) use ($allMappedIds) {
                $q->whereIn('given_by', $allMappedIds)->orWhereIn('given_to', $allMappedIds);
            });
        }
        
        if ($itemId) {
            $query->where(function($q) use ($itemId) {
                $q->where('item_id', $itemId)
                  ->orWhere('to_item_id', $itemId);
            });
        }
        
        /*
        |--------------------------------------------------------------------------
        | Direction (In / Out) of each row: one rule for the label and the filter
        |--------------------------------------------------------------------------
        | A row is labelled from the side of the person viewing it ($viewerId,
        | the logged-in user), so a head's Stock Out shows as Out: OUT when
        | they gave the gold, IN when they received it. Rows they aren't a
        | party to (say, the picked employee's transfer to another employee)
        | fall back to the picked user's side ($employeeId, else head_id).
        | Rows neither of them gave or received, and item changes /
        | conversions, keep their saved stock_type.
        |
        | Until 2026-11-13 a picked user switched every row to that user's
        | side. The Stock screen always sends the picked user, so a head's
        | Stock Out to Ravi showed as In (Ravi received it), while the In/Out
        | filter (BACKEND_TODO §23) went by the saved stock_type. The two now
        | share stockHistoryDirection() / stockHistoryDirectionSql(), so every
        | row shows under exactly one of In and Out, with the label it has
        | in the list.
        */
        $perspectiveIds = array_values(array_unique(array_filter(
            [$viewerId, $employeeId ?: $headId],
            fn ($id) => $id !== null && $id !== ''
        )));

        if ($type === 'IN' || $type === 'OUT') {
            [$directionSql, $bindings] = $this->stockHistoryDirectionSql($perspectiveIds);
            $query->whereRaw("({$directionSql}) = ?", [...$bindings, $type]);
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
        $paginated->getCollection()->transform(function ($txn) use ($perspectiveIds) {
            $stockType = $this->stockHistoryDirection($txn, $perspectiveIds);
            $remarks = $txn->remarks;

            // The counterparty: who received it on an Out row, who gave it on
            // an In row, from the side the row is labelled from.
            if ($txn->type === 'ITEMCHANGE' || $txn->type === 'ITEMCONVERSION') {
                $user = $txn->givenBy ?: $txn->givenTo;
            } else {
                $user = ($stockType === 'OUT') ? ($txn->givenTo ?: $txn->givenBy) : ($txn->givenBy ?: $txn->givenTo);
            }
            $userName = $user ? $user->name : $txn->remarks;
            $userId = $user ? $user->user_id : null;

            // Clean up remarks if they were used for the User column (e.g., MELTING)
            if ($userName === $remarks && !$txn->givenTo && !$txn->givenBy) {
                $remarks = '';
            }

            $itemName = $txn->item ? $txn->item->item_name : '';
            if ($txn->type === 'ITEMCHANGE' || $txn->type === 'ITEMCONVERSION') {
                $toItemName = $txn->toItem ? $txn->toItem->item_name : '';
                if ($toItemName) {
                    if ($stockType === 'OUT') {
                        $itemName = $itemName . ' (OUT => ' . $toItemName . ')';
                    } else {
                        $itemName = $itemName . ' (IN <= ' . $toItemName . ')';
                    }
                }
                
                $fromUser = $txn->givenBy ? $txn->givenBy->name : '';
                $toUser = $txn->givenTo ? $txn->givenTo->name : '';
                $remarks = $remarks ?: "From : {$fromUser} => To : {$toUser}";
            }

            return [
                'id' => $txn->stock_id,
                'item_name' => $itemName,
                'stock_type' => $stockType,
                'type' => $txn->type,
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
        
        $itemId = $params['item_id'] ?? null;
        if (!$itemId) {
            $metalItemIds = \App\Models\SystemSetting::get('live_metal_report_items', []);
            if (empty($metalItemIds)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'item_id' => 'No items mapped for Live Metal Report. Please configure live_metal_report_items in settings or provide an item_id.'
                ]);
            }
            $itemId = $metalItemIds[0];
        }

        $query = StockDetails::with(['givenBy'])
            ->where('given_to', $targetUserId)
            ->where('remarks', '!=', 'CASH_TO_GOLD')
            ->whereIn('entry_type', ['NORMAL', 'EMPTOHEAD', 'HEADTOHEAD'])
            ->where(function ($q) use ($itemId) {
                $q->where(function ($sq1) use ($itemId) {
                    $sq1->where('entry_type', 'NORMAL')->where('item_id', $itemId);
                })->orWhere(function ($sq2) use ($itemId) {
                    $sq2->where('entry_type', '!=', 'NORMAL')->where('to_item_id', $itemId);
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
            
            // Replicate the legacy subquery exactly (PostgreSQL safe)
            $query->selectRaw("stock_details.*, COALESCE((SELECT SUM(stock.grams) FROM stock_details as stock WHERE stock.stock_in_id = stock_details.stock_id and added_at <= ?), 0) as used_grams", [$dateTime])
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

    /**
     * One Day Action: the day's stock movements and cash transactions, as two
     * sections with their own totals and paging (2026-11-25, legacy screen's
     * "Stock Report" and "Cash Management Report").
     *
     * $headId scopes both sections to entries the head is a party to; null
     * is the admin console's all-heads view. `section` (stock | cash) returns
     * only that section, for its Load More. Filters: OneDayActionFilters.
     */
    public function getOneDayActionReport(array $filters, ?int $headId): array
    {
        $fromDate = ($filters['from_date'] ?? null) ?: date('Y-m-d');
        $toDate = ($filters['to_date'] ?? null) ?: $fromDate;
        $pageNo = max(1, (int) ($filters['page_no'] ?? 1));
        $pageSize = max(1, (int) ($filters['page_size'] ?? 500));
        $section = $filters['section'] ?? null;

        $result = [];
        if ($section !== 'cash') {
            $result['stock'] = $this->paginateSection(
                $this->oneDayStockQuery($filters, $headId, $fromDate, $toDate),
                $pageNo, $pageSize, fn (StockDetails $stock) => $this->oneDayStockRow($stock),
            );
        }
        if ($section !== 'stock') {
            $result['cash'] = $this->paginateSection(
                $this->oneDayCashQuery($filters, $headId, $fromDate, $toDate),
                $pageNo, $pageSize, fn (\App\Models\CashTxnDetail $cash) => $this->oneDayCashRow($cash),
            );
        }

        return $result;
    }

    /** Cash types that bring cash to the head (Receive group); the rest send it out. */
    private const CASH_IN_TYPES = ['INCOME', 'SALE_GOLD', 'CASH_TO_GOLD', 'AUTO_ENTRY', 'IN_CASH_CONVERTER'];
    private const CASH_OUT_TYPES = ['EXPENSE', 'PURCHASE_GOLD', 'GOLD_TO_CASH', 'OUT_CASH_CONVERTER'];

    private function oneDayStockQuery(array $filters, ?int $headId, string $fromDate, string $toDate): Builder
    {
        $query = StockDetails::with(['item', 'givenBy', 'givenTo'])
            ->whereIn('entry_type', [
                'NORMAL', 'SALES', 'HEADTOHEAD',
                'OUT_CASH_CONVERTER', 'IN_CASH_CONVERTER',
                'GOLDCASHCONVERSION', 'CashToGold',
            ])
            ->whereDate('added_at', '>=', $fromDate)
            ->whereDate('added_at', '<=', $toDate);

        if (!empty($filters['stocktype'])) {
            $query->where('entry_type', $filters['stocktype']);
        }
        if (!empty($filters['type'])) {
            $query->where('stock_type', $filters['type']);
        }
        if (!empty($filters['retailer_id'])) {
            $query->where('retailer_id', (int) $filters['retailer_id']);
        }
        if (!empty($filters['item_id'])) {
            $itemId = (int) $filters['item_id'];
            $query->where(fn ($q) => $q->where('item_id', $itemId)->orWhere('to_item_id', $itemId));
        }

        $this->applyOneDayParties($query, $filters, $headId, 'given_by', 'given_to');

        return $query->orderByDesc('added_at')->orderByDesc('stock_id');
    }

    private function oneDayCashQuery(array $filters, ?int $headId, string $fromDate, string $toDate): Builder
    {
        $query = \App\Models\CashTxnDetail::with(['givenByUser', 'givenToUser', 'category', 'bank'])
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate);

        // Stock Type on cash: IN = the Receive group, OUT = the Pay group.
        if (($filters['type'] ?? null) === 'IN') {
            $query->whereIn('type', self::CASH_IN_TYPES);
        } elseif (($filters['type'] ?? null) === 'OUT') {
            $query->whereIn('type', self::CASH_OUT_TYPES);
        }

        $this->applyOneDayParties($query, $filters, $headId, 'sender_id', 'recipient_id');

        return $query->orderByDesc('created_at')->orderByDesc('txn_id');
    }

    /** User (entered by), Given By, Given To, Role and the head scope; same for both sections. */
    private function applyOneDayParties(Builder $query, array $filters, ?int $headId, string $from, string $to): void
    {
        if (!empty($filters['employee_id'])) {
            $query->where('added_by', (int) $filters['employee_id']);
        }
        if (!empty($filters['given_by'])) {
            $query->where($from, (int) $filters['given_by']);
        }
        if (!empty($filters['given_to'])) {
            $query->where($to, (int) $filters['given_to']);
        }
        if (!empty($filters['given_by_given_to'])) {
            $party = (int) $filters['given_by_given_to'];
            $query->where(fn ($q) => $q->where($from, $party)->orWhere($to, $party));
        }
        // Role: either party has it.
        if (!empty($filters['role_id'])) {
            $withRole = UserDetail::query()->select('user_id')->where('role_id', (int) $filters['role_id']);
            $query->where(fn ($q) => $q->whereIn($from, $withRole)->orWhereIn($to, clone $withRole));
        }
        if ($headId !== null) {
            $query->where(fn ($q) => $q->where($from, $headId)->orWhere($to, $headId));
        }
    }

    private function paginateSection(Builder $query, int $pageNo, int $pageSize, callable $map): array
    {
        $total = (clone $query)->count();
        $records = $query->forPage($pageNo, $pageSize)->get()->map($map)->values();

        return [
            'total_count' => $total,
            'records' => $records,
            'pagination' => [
                'current_page' => $pageNo,
                'per_page' => $pageSize,
                'total_items' => $total,
                'total_pages' => (int) ceil($total / $pageSize),
            ],
        ];
    }

    private function oneDayStockRow(StockDetails $stock): array
    {
        return [
            'id' => $stock->stock_id,
            'entry_type' => $stock->entry_type,
            'stock_type' => $stock->stock_type,
            'given_by' => $stock->givenBy?->name ?? '-',
            'given_to' => $stock->givenTo?->name ?? '-',
            'item_name' => $stock->item?->item_name ?? '-',
            'grams' => $stock->grams,
            'touch' => $stock->touch,
            'purity' => $stock->purity,
            'waste_total' => $stock->waste_total,
            'waste_value' => $stock->waste_value,
            'remarks' => $stock->remarks,
            'added_by' => $stock->added_by,
            'added_at' => $stock->added_at ? $stock->added_at->format('Y-m-d H:i:s') : '-',
            'is_hided' => $stock->is_hided,
        ];
    }

    private function oneDayCashRow(\App\Models\CashTxnDetail $cash): array
    {
        return [
            'id' => $cash->txn_id,
            'type' => $cash->type,
            'flow' => in_array($cash->type, self::CASH_IN_TYPES, true) ? 'IN' : 'OUT',
            'given_by' => $cash->givenByUser?->name ?? '-',
            'given_to' => $cash->givenToUser?->name ?? '-',
            'category_name' => $cash->category?->category_name,
            'amount' => $cash->amount,
            'payment_method' => $cash->payment_method,
            'bank_name' => $cash->bank?->account_name,
            'remarks' => $cash->remarks,
            'added_by' => $cash->added_by,
            'added_at' => $cash->created_at ? $cash->created_at->format('Y-m-d H:i:s') : '-',
        ];
    }

    public function getDaywiseGrandReport(array $filters, int $headId): array
    {
        $fromDate = !empty($filters['from_date']) ? $filters['from_date'] : null;
        $toDate = !empty($filters['to_date']) ? $filters['to_date'] : null;
        
        $pageSize = $filters['page_size'] ?? 15;
        $pageNo = $filters['page'] ?? $filters['page_no'] ?? 1;

        $userId = null;
        $retailerId = null;

        $type4 = $filters['type4'] ?? null;
        if ($type4 === 'HEAD') {
            $userId = (int) ($filters['head_user_id'] ?? $headId);
        } elseif ($type4 === 'EMPLOYEE') {
            $userId = (int) ($filters['employee_id'] ?? $filters['user_id'] ?? null);
        } elseif ($type4 === 'CUSTOMER' || $type4 === 'RETAILER') {
            $retailerId = (int) ($filters['customer_id'] ?? $filters['retailer_id'] ?? null);
        }

        $query = StockDetails::where('is_hided', 0)
            ->where('is_freezed', 0);

        if ($fromDate) {
            $query->where('added_at', '>=', "$fromDate 00:00:00");
        }
        if ($toDate) {
            $query->where('added_at', '<=', "$toDate 23:59:59");
        }

        if ($retailerId) {
            $query->where(function($q) use ($retailerId) {
                $q->where('retailer_id', $retailerId)
                  ->orWhere('to_retailer_id', $retailerId);
            });
            $targetId = $retailerId;
            $inCol = 'to_retailer_id';
            $outCol = 'retailer_id';
        } else {
            if (!$userId) $userId = $headId;
            $query->where(function($q) use ($userId) {
                $q->where('given_to', $userId)
                  ->orWhere('given_by', $userId);
            });
            $targetId = $userId;
            $inCol = 'given_to';
            $outCol = 'given_by';
        }

        // Group by Date and calculate grand totals
        $dailyAggregates = $query->selectRaw("
                DATE(added_at) as txn_date,
                SUM(CASE WHEN $inCol = ? THEN grams ELSE 0 END) as in_grams,
                SUM(CASE WHEN $inCol = ? THEN purity ELSE 0 END) as in_purity,
                SUM(CASE WHEN $outCol = ? THEN grams ELSE 0 END) as out_grams,
                SUM(CASE WHEN $outCol = ? THEN purity ELSE 0 END) as out_purity
            ", [$targetId, $targetId, $targetId, $targetId])
            ->groupByRaw("DATE(added_at)")
            ->orderByRaw("DATE(added_at) DESC")
            ->paginate($pageSize, ['*'], 'page', $pageNo);

        $formattedRecords = $dailyAggregates->getCollection()->map(function ($day) {
            return [
                'date' => $day->txn_date,
                'in_grams' => round($day->in_grams ?? 0, 3),
                'in_purity' => round($day->in_purity ?? 0, 3),
                'out_grams' => round($day->out_grams ?? 0, 3),
                'out_purity' => round($day->out_purity ?? 0, 3),
            ];
        });

        return [
            'total_count' => $dailyAggregates->total(),
            'page_no' => (int) $pageNo,
            'page_size' => (int) $pageSize,
            'records' => $formattedRecords,
            'pagination' => [
                'current_page' => $dailyAggregates->currentPage(),
                'per_page' => $dailyAggregates->perPage(),
                'total_items' => $dailyAggregates->total(),
                'total_pages' => $dailyAggregates->lastPage(),
            ]
        ];
    }
}
