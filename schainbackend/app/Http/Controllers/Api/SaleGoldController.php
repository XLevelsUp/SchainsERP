<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\CashToGold;
use App\Models\CashToGoldAmountSource;
use App\Models\CashTxnDetail;
use App\Models\UserDetail;
use App\Models\BankDetail;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleGoldController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CREATE SALE GOLD
    |--------------------------------------------------------------------------
    |
    | POST /api/v1/sale-gold
    |
    */

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [

                /*
                |--------------------------------------------------------------------------
                | GOLD DETAILS
                |--------------------------------------------------------------------------
                */

                'head_id' =>
                    'required|integer',

                'customer_id' =>
                    'nullable|integer',

                'item_id' =>
                    'required|integer',

                'stock_id' =>
                    'nullable|integer',

                'grams' =>
                    'required|numeric|min:0.001',

                'touch' =>
                    'required|numeric|min:0',

                'purity' =>
                    'required|numeric|min:0',

                'per_gram_cash' =>
                    'required|numeric|min:0',

                'total_cash' =>
                    'required|numeric|min:0.01',

                'amount_transfer_to_head' =>
                    'nullable|boolean',

                'remarks' =>
                    'nullable|string|max:5000',

                /*
                |--------------------------------------------------------------------------
                | HEAD CASH OPENING BALANCE
                |--------------------------------------------------------------------------
                */

                'opening_account_balance' =>
                    'required|numeric',

                'opening_user_balance' =>
                    'required|numeric',

                /*
                |--------------------------------------------------------------------------
                | AMOUNT SOURCES
                |--------------------------------------------------------------------------
                */

                'amount_sources' =>
                    'required|array|min:1',

                'amount_sources.*.source_type' => [
                    'required',
                    'in:CASH_ON_HAND,BANK'
                ],

                'amount_sources.*.amount' =>
                    'required|numeric|min:0.01',

                'amount_sources.*.bank_id' =>
                    'nullable|integer',

                'amount_sources.*.bank_name' =>
                    'nullable|string|max:150',

                'amount_sources.*.opening_bank_account_balance' =>
                    'nullable|numeric',

                'amount_sources.*.opening_bank_user_balance' =>
                    'nullable|numeric',

            ]
        );


        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }


        try {

            $result = DB::transaction(
                function () use ($request, $validator) {

                    $data = $validator->validated();


                    /*
                    |--------------------------------------------------------------------------
                    | CHECK TOTAL SOURCE AMOUNT
                    |--------------------------------------------------------------------------
                    */

                    $sourceTotal = 0;

                    foreach (
                        $data['amount_sources']
                        as $source
                    ) {

                        $sourceTotal +=
                            (float) $source['amount'];
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Total Cash and Source Total must match
                    |--------------------------------------------------------------------------
                    */

                    if (
                        round($sourceTotal, 2) !==
                        round((float) $data['total_cash'], 2)
                    ) {

                        throw new \Exception(
                            'Total Cash (' .
                            $data['total_cash'] .
                            ') does not match Amount Sources total (' .
                            $sourceTotal .
                            ').'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | HEAD USER
                    |--------------------------------------------------------------------------
                    */

                    $headUser = UserDetail::where(
                        'user_id',
                        $data['head_id']
                    )
                        ->lockForUpdate()
                        ->first();

                    if (!$headUser) {

                        throw new \Exception(
                            'Head user not found: ' .
                            $data['head_id']
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CUSTOMER
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !empty($data['customer_id'])
                    ) {

                        $customer = UserDetail::where(
                            'user_id',
                            $data['customer_id']
                        )->first();

                        if (!$customer) {

                            throw new \Exception(
                                'Customer not found: ' .
                                $data['customer_id']
                            );
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | CALCULATE GOLD PURITY
                    |--------------------------------------------------------------------------
                    */

                    $totalGrams =
                        (float) $data['grams'];

                    $touch =
                        (float) $data['touch'];

                    $purity =
                        (float) $data['purity'];

                    $perGramCash =
                        (float) $data['per_gram_cash'];

                    $totalCash =
                        (float) $data['total_cash'];


                    /*
                    |--------------------------------------------------------------------------
                    | TAKEN PURITY
                    |--------------------------------------------------------------------------
                    */

                    $takenPurity =
                        $totalGrams *
                        ($purity / 100);


                    /*
                    |--------------------------------------------------------------------------
                    | CREATE CASH TO GOLD
                    |--------------------------------------------------------------------------
                    */

                    $cashToGold =
                        CashToGold::create([

                            'type' =>
                                'SALE_GOLD',

                            'head_id' =>
                                $data['head_id'],

                            'customer_id' =>
                                $data['customer_id'] ?? null,

                            'total_cash' =>
                                $totalCash,

                            'per_gram_cash' =>
                                $perGramCash,

                            'total_grams' =>
                                $totalGrams,

                            'touch' =>
                                $touch,

                            'purity' =>
                                $purity,

                            'item_id' =>
                                $data['item_id'],

                            'stock_id' =>
                                $data['stock_id'] ?? null,

                            'added_at' =>
                                now(),

                            'added_by' =>
                                $data['head_id'],

                            'amnt_transfer_to_head' =>
                                $data[
                                    'amount_transfer_to_head'
                                ] ?? true,

                            'taken_total_cash' =>
                                $totalCash,

                            'taken_total_grams' =>
                                $totalGrams,

                            'taken_purity' =>
                                $takenPurity,

                            'ob_grams' =>
                                null,

                            'ob_purity' =>
                                null,

                            'remarks' =>
                                $data['remarks'] ?? null,
                        ]);


                    /*
                    |--------------------------------------------------------------------------
                    | RUN EACH PAYMENT SOURCE
                    |--------------------------------------------------------------------------
                    */

                    $sourceResults = [];

                    $cashOpeningBalance =
                        (float)
                        $data['opening_account_balance'];

                    $userOpeningBalance =
                        (float)
                        $data['opening_user_balance'];


                    foreach (
                        $data['amount_sources']
                        as $source
                    ) {

                        $sourceType =
                            $source['source_type'];

                        $sourceAmount =
                            (float)
                            $source['amount'];


                        /*
                        |--------------------------------------------------------------------------
                        | CASH ON HAND
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $sourceType ===
                            'CASH_ON_HAND'
                        ) {

                            /*
                            |
                            | SALE_GOLD = cash comes to head
                            |
                            | Opening Account + Amount
                            |
                            */

                            $closingAccountBalance =
                                $cashOpeningBalance +
                                $sourceAmount;

                            $closingUserBalance =
                                $userOpeningBalance -
                                $sourceAmount;


                            /*
                            |--------------------------------------------------------------------------
                            | CREATE CASH TRANSACTION
                            |--------------------------------------------------------------------------
                            */

                            $cashTransaction =
                                CashTxnDetail::create([

                                    'type' =>
                                        'SALE_GOLD',

                                    'given_to' =>
                                        $data['customer_id']
                                        ?? $data['head_id'],

                                    'given_by' =>
                                        $data['head_id'],

                                    'category_id' =>
                                        null,

                                    'amount' =>
                                        $sourceAmount,

                                    'balance' =>
                                        $closingAccountBalance,

                                    'opening_account_balance' =>
                                        $cashOpeningBalance,

                                    'opening_user_balance' =>
                                        $userOpeningBalance,

                                    'opening_bank_account_balance' =>
                                        0,

                                    'opening_bank_user_balance' =>
                                        0,

                                    'closing_account_balance' =>
                                        $closingAccountBalance,

                                    'closing_user_balance' =>
                                        $closingUserBalance,

                                    'closing_bank_account_balance' =>
                                        0,

                                    'closing_bank_user_balance' =>
                                        0,

                                    'souce_type' =>
                                        'CASH_ON_HAND',

                                    'bank_id' =>
                                        null,

                                    'remarks' =>
                                        $data['remarks']
                                        ?? 'Sale Gold',

                                    'added_at' =>
                                        now(),

                                    'added_by' =>
                                        $data['head_id'],

                                    'is_active' =>
                                        true,

                                    'is_hidden' =>
                                        false,

                                    'is_show_to_all' =>
                                        true,

                                    'cash_to_gold_id' =>
                                        $cashToGold
                                            ->cash_to_gold_id,

                                    'amnt_transfer_from_head' =>
                                        $data[
                                            'amount_transfer_to_head'
                                        ] ?? true,

                                    'txn_type' =>
                                        'NORMAL',
                                ]);


                            /*
                            |--------------------------------------------------------------------------
                            | SOURCE TABLE
                            |--------------------------------------------------------------------------
                            */

                            CashToGoldAmountSource::create([

                                'cash_to_gold_id' =>
                                    $cashToGold
                                        ->cash_to_gold_id,

                                'cash_txn_id' =>
                                    $cashTransaction
                                        ->txn_id,

                                'souce_type' =>
                                    'CASH_ON_HAND',

                                'bank_id' =>
                                    null,

                                'amount' =>
                                    $sourceAmount,

                                'added_at' =>
                                    now(),
                            ]);


                            /*
                            |--------------------------------------------------------------------------
                            | USER CASH BALANCE
                            |--------------------------------------------------------------------------
                            */

                            $headUser->update([

                                'rak_cash_balance' =>
                                    $closingAccountBalance
                            ]);


                            /*
                            |--------------------------------------------------------------------------
                            | Move opening to next source
                            |--------------------------------------------------------------------------
                            */

                            $cashOpeningBalance =
                                $closingAccountBalance;

                            $userOpeningBalance =
                                $closingUserBalance;


                            $sourceResults[] = [
                                'source_type' =>
                                    'CASH_ON_HAND',

                                'amount' =>
                                    $sourceAmount,

                                'closing_balance' =>
                                    $closingAccountBalance,
                            ];
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | BANK
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $sourceType === 'BANK'
                        ) {

                            /*
                            |--------------------------------------------------------------------------
                            | Validate Bank Details
                            |--------------------------------------------------------------------------
                            */

                            if (
                                empty($source['bank_id'])
                            ) {

                                throw new \Exception(
                                    'bank_id is required for BANK source.'
                                );
                            }

                            if (
                                empty($source['bank_name'])
                            ) {

                                throw new \Exception(
                                    'bank_name is required for BANK source.'
                                );
                            }


                            $bankId =
                                (int)
                                $source['bank_id'];

                            $bankName =
                                $source['bank_name'];


                            /*
                            |--------------------------------------------------------------------------
                            | Opening Bank Balance
                            |--------------------------------------------------------------------------
                            */

                            $bankOpeningBalance =
                                (float)
                                (
                                    $source[
                                        'opening_bank_account_balance'
                                    ] ?? 0
                                );

                            $bankOpeningUserBalance =
                                (float)
                                (
                                    $source[
                                        'opening_bank_user_balance'
                                    ] ?? 0
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | Find or create bank
                            |--------------------------------------------------------------------------
                            */

                            $bank =
                                BankDetail::where(
                                    'bank_id',
                                    $bankId
                                )
                                ->lockForUpdate()
                                ->first();


                            if (!$bank) {

                                $bank =
                                    BankDetail::create([

                                        'bank_id' =>
                                            $bankId,

                                        'account_name' =>
                                            $bankName,

                                        'ledger_balance' =>
                                            $bankOpeningBalance,

                                        'is_active' =>
                                            true,
                                    ]);

                            } else {

                                $bank->update([
                                    'account_name' =>
                                        $bankName
                                ]);
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | BANK CLOSING BALANCE
                            |--------------------------------------------------------------------------
                            |
                            | Sale Gold money comes INTO the bank.
                            |
                            */

                            $closingBankBalance =
                                $bankOpeningBalance +
                                $sourceAmount;

                            $closingBankUserBalance =
                                $bankOpeningUserBalance -
                                $sourceAmount;


                            /*
                            |--------------------------------------------------------------------------
                            | CREATE CASH TRANSACTION
                            |--------------------------------------------------------------------------
                            */

                            $cashTransaction =
                                CashTxnDetail::create([

                                    'type' =>
                                        'SALE_GOLD',

                                    'given_to' =>
                                        $data['customer_id']
                                        ?? $data['head_id'],

                                    'given_by' =>
                                        $data['head_id'],

                                    'category_id' =>
                                        null,

                                    'amount' =>
                                        $sourceAmount,

                                    'balance' =>
                                        $closingBankBalance,

                                    'opening_account_balance' =>
                                        $cashOpeningBalance,

                                    'opening_user_balance' =>
                                        $userOpeningBalance,

                                    'opening_bank_account_balance' =>
                                        $bankOpeningBalance,

                                    'opening_bank_user_balance' =>
                                        $bankOpeningUserBalance,

                                    'closing_account_balance' =>
                                        $cashOpeningBalance,

                                    'closing_user_balance' =>
                                        $userOpeningBalance,

                                    'closing_bank_account_balance' =>
                                        $closingBankBalance,

                                    'closing_bank_user_balance' =>
                                        $closingBankUserBalance,

                                    'souce_type' =>
                                        'BANK',

                                    'bank_id' =>
                                        $bankId,

                                    'remarks' =>
                                        $data['remarks']
                                        ?? 'Sale Gold',

                                    'added_at' =>
                                        now(),

                                    'added_by' =>
                                        $data['head_id'],

                                    'is_active' =>
                                        true,

                                    'is_hidden' =>
                                        false,

                                    'is_show_to_all' =>
                                        true,

                                    'cash_to_gold_id' =>
                                        $cashToGold
                                            ->cash_to_gold_id,

                                    'amnt_transfer_from_head' =>
                                        $data[
                                            'amount_transfer_to_head'
                                        ] ?? true,

                                    'txn_type' =>
                                        'NORMAL',
                                ]);


                            /*
                            |--------------------------------------------------------------------------
                            | AMOUNT SOURCE
                            |--------------------------------------------------------------------------
                            */

                            CashToGoldAmountSource::create([

                                'cash_to_gold_id' =>
                                    $cashToGold
                                        ->cash_to_gold_id,

                                'cash_txn_id' =>
                                    $cashTransaction
                                        ->txn_id,

                                'souce_type' =>
                                    'BANK',

                                'bank_id' =>
                                    $bankId,

                                'amount' =>
                                    $sourceAmount,

                                'added_at' =>
                                    now(),
                            ]);


                            /*
                            |--------------------------------------------------------------------------
                            | UPDATE BANK BALANCE
                            |--------------------------------------------------------------------------
                            */

                            $bank->update([

                                'ledger_balance' =>
                                    $closingBankBalance
                            ]);


                            /*
                            |--------------------------------------------------------------------------
                            | UPDATE USER CASH BALANCE
                            |--------------------------------------------------------------------------
                            */

                            $headUser->update([

                                'rak_cash_balance' =>
                                    $closingAccountBalance
                            ]);


                            $sourceResults[] = [

                                'source_type' =>
                                    'BANK',

                                'bank_id' =>
                                    $bankId,

                                'bank_name' =>
                                    $bankName,

                                'amount' =>
                                    $sourceAmount,

                                'closing_bank_balance' =>
                                    $closingBankBalance,
                            ];
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | FINAL RESULT
                    |--------------------------------------------------------------------------
                    */

                    $cashToGold->load([
                        'amountSources'
                    ]);


                    return [
                        'cash_to_gold' =>
                            $cashToGold,

                        'source_results' =>
                            $sourceResults,

                        'user' => [
                            'user_id' =>
                                $headUser->user_id,

                            'name' =>
                                $headUser->name,

                            'rak_cash_balance' =>
                                $headUser->rak_cash_balance,
                        ],
                    ];
                }
            );


            return response()->json([

                'success' =>
                    true,

                'message' =>
                    'Sale Gold created successfully',

                'data' =>
                    $result

            ], 201);


        } catch (\Throwable $e) {

            return response()->json([

                'success' =>
                    false,

                'message' =>
                    'Failed to create Sale Gold',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GET ALL SALE GOLD
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        try {

            $sales = CashToGold::with([
                'amountSources'
            ])
                ->where(
                    'type',
                    'SALE_GOLD'
                )
                ->orderByDesc(
                    'cash_to_gold_id'
                )
                ->get();

            return response()->json([

                'success' =>
                    true,

                'message' =>
                    'Sale Gold records retrieved successfully',

                'data' =>
                    $sales

            ], 200);

        } catch (\Throwable $e) {

            return response()->json([

                'success' =>
                    false,

                'message' =>
                    'Failed to retrieve Sale Gold',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GET ONE SALE GOLD
    |--------------------------------------------------------------------------
    */

    public function show($id): JsonResponse
    {
        try {

            $sale =
                CashToGold::with([
                    'amountSources'
                ])
                    ->where(
                        'cash_to_gold_id',
                        $id
                    )
                    ->where(
                        'type',
                        'SALE_GOLD'
                    )
                    ->first();

            if (!$sale) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Sale Gold not found'
                ], 404);
            }


            return response()->json([

                'success' =>
                    true,

                'message' =>
                    'Sale Gold retrieved successfully',

                'data' =>
                    $sale

            ], 200);

        } catch (\Throwable $e) {

            return response()->json([

                'success' =>
                    false,

                'message' =>
                    'Failed to retrieve Sale Gold',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }
}