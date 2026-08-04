<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\CashTxnDetail;
use App\Models\CashTxnImage;
use App\Models\UserDetail;
use App\Models\BankDetail;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CashTxnDetailController extends Controller
{
    /**
     * ============================================================
     * GET ALL CASH TRANSACTIONS
     * ============================================================
     */
    public function index(): JsonResponse
    {
        try {

            $transactions = CashTxnDetail::with([
                'images',
                'bank',
                'givenByUser',
                'givenToUser'
            ])
                ->orderByDesc('txn_id')
                ->get();

            foreach ($transactions as $transaction) {

                $transaction->image_full_url =
                    $transaction->image_url
                        ? asset(
                            'storage/' . $transaction->image_url
                        )
                        : null;

                foreach ($transaction->images as $image) {

                    $image->image_full_url =
                        asset(
                            'storage/' . $image->image_url
                        );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Cash transactions retrieved successfully',
                'data' => $transactions
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cash transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * CREATE CASH TRANSACTION
     * ============================================================
     *
     * POST
     * /api/v1/cash-txn-details
     *
     * Supports:
     * - Cash transaction
     * - Bank transaction
     * - Automatic closing balance
     * - User rak_cash_balance update
     * - Bank current_balance update
     * - Multiple images
     */
    public function store(Request $request): JsonResponse
    {
        $this->convertNullStringsToNull($request);

        $validator = $this->storeValidator($request);

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $result = DB::transaction(function () use (
                $request,
                $validator
            ) {

                $data = $validator->validated();

                unset($data['images']);

                /*
                |--------------------------------------------------------------------------
                | Opening balances
                |--------------------------------------------------------------------------
                */

                $openingAccountBalance =
                    (float) $data['opening_account_balance'];

                $openingUserBalance =
                    (float) $data['opening_user_balance'];

                $openingBankAccountBalance =
                    (float) (
                        $data['opening_bank_account_balance'] ?? 0
                    );

                $openingBankUserBalance =
                    (float) (
                        $data['opening_bank_user_balance'] ?? 0
                    );

                $amount =
                    (float) $data['amount'];


                /*
                |--------------------------------------------------------------------------
                | Calculate closing balances
                |--------------------------------------------------------------------------
                */

                [
                    $closingAccountBalance,
                    $closingUserBalance,
                    $closingBankAccountBalance,
                    $closingBankUserBalance
                ] = $this->calculateBalances(
                    $data['type'],
                    $data['souce_type'],
                    $amount,
                    $openingAccountBalance,
                    $openingUserBalance,
                    $openingBankAccountBalance,
                    $openingBankUserBalance
                );


                /*
                |--------------------------------------------------------------------------
                | Validate given_by user
                |--------------------------------------------------------------------------
                */

                $givenByUser = UserDetail::where(
                    'user_id',
                    $data['given_by']
                )
                    ->lockForUpdate()
                    ->first();

                if (!$givenByUser) {

                    throw new \Exception(
                        'Given by user not found: ' .
                        $data['given_by']
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Validate given_to user
                |--------------------------------------------------------------------------
                */

                $givenToUser = UserDetail::where(
                    'user_id',
                    $data['given_to']
                )
                    ->first();

                if (!$givenToUser) {

                    throw new \Exception(
                        'Given to user not found: ' .
                        $data['given_to']
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | CREATE / UPDATE BANK
                |--------------------------------------------------------------------------
                | When souce_type = BANK, bank_id, bank_name and
                | opening_bank_account_balance come from the request.
                | If the bank does not exist, create it automatically.
                |--------------------------------------------------------------------------
                */

                $bank = null;

                if ($data['souce_type'] === 'BANK') {

                    $bank = BankDetail::where(
                        'bank_id',
                        $data['bank_id']
                    )
                        ->lockForUpdate()
                        ->first();

                    if (!$bank) {

                        $bank = BankDetail::create([
                            'bank_id' =>
                                $data['bank_id'],

                            'bank_name' =>
                                $data['bank_name'],

                            'current_balance' =>
                                $openingBankAccountBalance,

                            'is_active' =>
                                true,

                            'added_at' =>
                                now(),
                        ]);

                    } else {

                        $bank->update([
                            'bank_name' =>
                                $data['bank_name'],
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Create cash transaction
                |--------------------------------------------------------------------------
                */

                $cashTransaction = CashTxnDetail::create([

                    'type' =>
                        $data['type'],

                    'given_to' =>
                        $data['given_to'],

                    'given_by' =>
                        $data['given_by'],

                    'category_id' =>
                        $data['category_id'] ?? null,

                    'amount' =>
                        $amount,

                    /*
                    |--------------------------------------------------------------------------
                    | Balance
                    |--------------------------------------------------------------------------
                    */

                    'balance' =>
                        $closingAccountBalance,

                    /*
                    |--------------------------------------------------------------------------
                    | Opening
                    |--------------------------------------------------------------------------
                    */

                    'opening_account_balance' =>
                        $openingAccountBalance,

                    'opening_user_balance' =>
                        $openingUserBalance,

                    'opening_bank_account_balance' =>
                        $openingBankAccountBalance,

                    'opening_bank_user_balance' =>
                        $openingBankUserBalance,

                    /*
                    |--------------------------------------------------------------------------
                    | Closing
                    |--------------------------------------------------------------------------
                    */

                    'closing_account_balance' =>
                        $closingAccountBalance,

                    'closing_user_balance' =>
                        $closingUserBalance,

                    'closing_bank_account_balance' =>
                        $closingBankAccountBalance,

                    'closing_bank_user_balance' =>
                        $closingBankUserBalance,

                    /*
                    |--------------------------------------------------------------------------
                    | Source
                    |--------------------------------------------------------------------------
                    */

                    'souce_type' =>
                        $data['souce_type'],

                    'bank_id' =>
                        $data['bank_id'] ?? null,

                    /*
                    |--------------------------------------------------------------------------
                    | Other fields
                    |--------------------------------------------------------------------------
                    */

                    'remarks' =>
                        $data['remarks'] ?? null,

                    'remainder' =>
                        $data['remainder'] ?? null,

                    'remainder_at' =>
                        $data['remainder_at'] ?? null,

                    'added_by' =>
                        $data['added_by'],

                    'is_active' =>
                        $data['is_active'] ?? true,

                    'is_hidden' =>
                        $data['is_hidden'] ?? false,

                    'is_show_to_all' =>
                        $data['is_show_to_all'] ?? false,

                    'amount_transfer_id' =>
                        $data['amount_transfer_id'] ?? null,

                    'cash_to_gold_id' =>
                        $data['cash_to_gold_id'] ?? null,

                    'stock_id' =>
                        $data['stock_id'] ?? null,

                    'amnt_transfer_from_head' =>
                        $data['amnt_transfer_from_head'] ?? true,

                    'internal_type' =>
                        $data['internal_type'] ?? null,

                    'retailer_id' =>
                        $data['retailer_id'] ?? null,

                    'retailer_ob_cash_balance' =>
                        $data['retailer_ob_cash_balance'] ?? null,

                    'retailer_ob_rtgs_balance' =>
                        $data['retailer_ob_rtgs_balance'] ?? null,

                    'txn_type' =>
                        $data['txn_type'] ?? 'NORMAL',

                    'bank_entry_date' =>
                        $data['bank_entry_date'] ?? null,

                    'machine_vendor_id' =>
                        $data['machine_vendor_id'] ?? null,

                    'machine_vendor_ob_cash_balance' =>
                        $data['machine_vendor_ob_cash_balance'] ?? null,

                    'machine_vendor_ob_rtgs_balance' =>
                        $data['machine_vendor_ob_rtgs_balance'] ?? null,

                    'is_bill_cash' =>
                        $data['is_bill_cash'] ?? null,

                    'is_payment_cash' =>
                        $data['is_payment_cash'] ?? null,

                    'is_customer_affect' =>
                        $data['is_customer_affect'] ?? null,

                    'is_need_receipt' =>
                        $data['is_need_receipt'] ?? null,

                    'bill_payment_cash_type' =>
                        $data['bill_payment_cash_type'] ?? null,

                    'partial_amount' =>
                        $data['partial_amount'] ?? null,

                    'actual_amount' =>
                        $data['actual_amount'] ?? null,

                    'receipt_cash_txn_id' =>
                        $data['receipt_cash_txn_id'] ?? null,

                    'given_by_arithmetic_operation' =>
                        $data['given_by_arithmetic_operation'] ?? null,

                    'given_to_arithmetic_operation' =>
                        $data['given_to_arithmetic_operation'] ?? null,

                    'cash_loan_type' =>
                        $data['cash_loan_type'] ?? null,

                    'per_gram_cash' =>
                        $data['per_gram_cash'] ?? null,

                    'over_all_bill_id' =>
                        $data['over_all_bill_id'] ?? null,

                    'estimate_retailer_bill_id' =>
                        $data['estimate_retailer_bill_id'] ?? null,

                    'estimate_metal_bill_id' =>
                        $data['estimate_metal_bill_id'] ?? null,

                    'is_admin_head_entry' =>
                        $data['is_admin_head_entry'] ?? false,

                    'admin_head_txn_id' =>
                        $data['admin_head_txn_id'] ?? null,

                    'added_at' =>
                        now(),
                ]);


                /*
                |--------------------------------------------------------------------------
                | UPDATE USER CASH BALANCE
                |--------------------------------------------------------------------------
                |
                | Latest requirement:
                | rak_cash_balance = closing_account_balance
                |
                */

                $givenByUser->update([
                    'rak_cash_balance' =>
                        $closingAccountBalance
                ]);


                /*
                |--------------------------------------------------------------------------
                | UPDATE BANK BALANCE
                |--------------------------------------------------------------------------
                */

                if (
                    $data['souce_type'] === 'BANK'
                ) {

                    $bank->update([
                        'current_balance' =>
                            $closingBankAccountBalance
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Upload images
                |--------------------------------------------------------------------------
                */

                $firstImagePath = null;

                if ($request->hasFile('images')) {

                    foreach (
                        $request->file('images')
                        as $image
                    ) {

                        if (!$image->isValid()) {
                            continue;
                        }

                        $path = $image->store(
                            'cash_txn_images',
                            'public'
                        );

                        if ($firstImagePath === null) {
                            $firstImagePath = $path;
                        }

                        CashTxnImage::create([
                            'txn_id' =>
                                $cashTransaction->txn_id,

                            'image_url' =>
                                $path,

                            'added_at' =>
                                now(),
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Save first image path to cash_txn_details
                |--------------------------------------------------------------------------
                */

                if ($firstImagePath !== null) {

                    $cashTransaction->update([
                        'image_url' =>
                            $firstImagePath
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Reload
                |--------------------------------------------------------------------------
                */

                $cashTransaction->refresh();

                $cashTransaction->load([
                    'images',
                    'bank',
                    'givenByUser',
                    'givenToUser'
                ]);

                return $cashTransaction;
            });


            $this->addImageUrls($result);

            return response()->json([
                'success' => true,
                'message' =>
                    'Cash transaction and balances created successfully',
                'data' => $result
            ], 201);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to create cash transaction',
                'error' =>
                    $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * GET SINGLE TRANSACTION
     * ============================================================
     */
    public function show($id): JsonResponse
    {
        try {

            $transaction = CashTxnDetail::with([
                'images',
                'bank',
                'givenByUser',
                'givenToUser'
            ])
                ->where('txn_id', $id)
                ->first();

            if (!$transaction) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Cash transaction not found'
                ], 404);
            }

            $this->addImageUrls($transaction);

            return response()->json([
                'success' => true,
                'message' =>
                    'Cash transaction retrieved successfully',
                'data' => $transaction
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to retrieve cash transaction',
                'error' =>
                    $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * UPDATE CASH TRANSACTION
     * ============================================================
     */
    public function update(
        Request $request,
        $id
    ): JsonResponse {

        $this->convertNullStringsToNull($request);

        try {

            $transaction = CashTxnDetail::where(
                'txn_id',
                $id
            )
                ->first();

            if (!$transaction) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Cash transaction not found'
                ], 404);
            }


            $validator = $this->updateValidator(
                $request,
                $id
            );

            if ($validator->fails()) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Validation failed',
                    'errors' =>
                        $validator->errors()
                ], 422);
            }


            $result = DB::transaction(
                function () use (
                    $request,
                    $transaction,
                    $validator
                ) {

                    $data =
                        $validator->validated();

                    unset($data['images']);


                    /*
                    |--------------------------------------------------------------------------
                    | OLD VALUES
                    |--------------------------------------------------------------------------
                    */

                    $oldGivenBy =
                        $transaction->given_by;

                    $oldSourceType =
                        $transaction->souce_type;

                    $oldBankId =
                        $transaction->bank_id;

                    $oldOpeningAccountBalance =
                        (float)
                        $transaction->opening_account_balance;

                    $oldOpeningBankBalance =
                        (float)
                        $transaction->opening_bank_account_balance;


                    /*
                    |--------------------------------------------------------------------------
                    | Lock old user
                    |--------------------------------------------------------------------------
                    */

                    $oldUser = UserDetail::where(
                        'user_id',
                        $oldGivenBy
                    )
                        ->lockForUpdate()
                        ->first();


                    /*
                    |--------------------------------------------------------------------------
                    | Lock old bank
                    |--------------------------------------------------------------------------
                    */

                    $oldBank = null;

                    if (
                        $oldSourceType === 'BANK' &&
                        $oldBankId
                    ) {

                        $oldBank = BankDetail::where(
                            'bank_id',
                            $oldBankId
                        )
                            ->lockForUpdate()
                            ->first();
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | NEW VALUES
                    |--------------------------------------------------------------------------
                    */

                    $type =
                        $data['type']
                        ?? $transaction->type;

                    $givenBy =
                        $data['given_by']
                        ?? $transaction->given_by;

                    $sourceType =
                        $data['souce_type']
                        ?? $transaction->souce_type;

                    $bankId =
                        array_key_exists(
                            'bank_id',
                            $data
                        )
                            ? $data['bank_id']
                            : $transaction->bank_id;

                    $amount =
                        array_key_exists(
                            'amount',
                            $data
                        )
                            ? (float) $data['amount']
                            : (float) $transaction->amount;

                    $openingAccountBalance =
                        array_key_exists(
                            'opening_account_balance',
                            $data
                        )
                            ? (float)
                                $data[
                                    'opening_account_balance'
                                ]
                            : (float)
                                $transaction
                                    ->opening_account_balance;

                    $openingUserBalance =
                        array_key_exists(
                            'opening_user_balance',
                            $data
                        )
                            ? (float)
                                $data[
                                    'opening_user_balance'
                                ]
                            : (float)
                                $transaction
                                    ->opening_user_balance;

                    $openingBankAccountBalance =
                        array_key_exists(
                            'opening_bank_account_balance',
                            $data
                        )
                            ? (float)
                                $data[
                                    'opening_bank_account_balance'
                                ]
                            : (float)
                                $transaction
                                    ->opening_bank_account_balance;

                    $openingBankUserBalance =
                        array_key_exists(
                            'opening_bank_user_balance',
                            $data
                        )
                            ? (float)
                                $data[
                                    'opening_bank_user_balance'
                                ]
                            : (float)
                                $transaction
                                    ->opening_bank_user_balance;


                    /*
                    |--------------------------------------------------------------------------
                    | Calculate new closing balances
                    |--------------------------------------------------------------------------
                    */

                    [
                        $closingAccountBalance,
                        $closingUserBalance,
                        $closingBankAccountBalance,
                        $closingBankUserBalance
                    ] = $this->calculateBalances(
                        $type,
                        $sourceType,
                        $amount,
                        $openingAccountBalance,
                        $openingUserBalance,
                        $openingBankAccountBalance,
                        $openingBankUserBalance
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Validate new given_by user
                    |--------------------------------------------------------------------------
                    */

                    $newUser = UserDetail::where(
                        'user_id',
                        $givenBy
                    )
                        ->lockForUpdate()
                        ->first();

                    if (!$newUser) {

                        throw new \Exception(
                            'Given by user not found: ' .
                            $givenBy
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Validate new bank
                    |--------------------------------------------------------------------------
                    */

                    $newBank = null;

                    if ($sourceType === 'BANK') {

                        $bankName =
                            $data['bank_name']
                            ?? $transaction->bank?->bank_name
                            ?? 'Unknown Bank';

                        $newBank = BankDetail::where(
                            'bank_id',
                            $bankId
                        )
                            ->lockForUpdate()
                            ->first();

                        if (!$newBank) {

                            $newBank = BankDetail::create([
                                'bank_id' =>
                                    $bankId,

                                'bank_name' =>
                                    $bankName,

                                'current_balance' =>
                                    $openingBankAccountBalance,

                                'is_active' =>
                                    true,

                                'added_at' =>
                                    now(),
                            ]);

                        } else {

                            $newBank->update([
                                'bank_name' =>
                                    $bankName,
                            ]);
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Restore old user balance
                    |--------------------------------------------------------------------------
                    |
                    | Put old user's balance back to the transaction's
                    | opening account balance before applying new values.
                    |
                    */

                    if ($oldUser) {

                        $oldUser->update([
                            'rak_cash_balance' =>
                                $oldOpeningAccountBalance
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Restore old bank balance
                    |--------------------------------------------------------------------------
                    */

                    if ($oldBank) {

                        $oldBank->update([
                            'current_balance' =>
                                $oldOpeningBankBalance
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Update transaction
                    |--------------------------------------------------------------------------
                    */

                    $updateData = $data;

                    // bank_name belongs to bank_details, not cash_txn_details.
                    unset($updateData['bank_name']);

                    $updateData['type'] =
                        $type;

                    $updateData['given_by'] =
                        $givenBy;

                    $updateData['amount'] =
                        $amount;

                    $updateData[
                        'opening_account_balance'
                    ] =
                        $openingAccountBalance;

                    $updateData[
                        'opening_user_balance'
                    ] =
                        $openingUserBalance;

                    $updateData[
                        'opening_bank_account_balance'
                    ] =
                        $openingBankAccountBalance;

                    $updateData[
                        'opening_bank_user_balance'
                    ] =
                        $openingBankUserBalance;

                    $updateData[
                        'closing_account_balance'
                    ] =
                        $closingAccountBalance;

                    $updateData[
                        'closing_user_balance'
                    ] =
                        $closingUserBalance;

                    $updateData[
                        'closing_bank_account_balance'
                    ] =
                        $closingBankAccountBalance;

                    $updateData[
                        'closing_bank_user_balance'
                    ] =
                        $closingBankUserBalance;

                    $updateData[
                        'balance'
                    ] =
                        $closingAccountBalance;

                    $transaction->update(
                        $updateData
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Update new user rak_cash_balance
                    |--------------------------------------------------------------------------
                    */

                    $newUser->update([
                        'rak_cash_balance' =>
                            $closingAccountBalance
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Update new bank balance
                    |--------------------------------------------------------------------------
                    */

                    if ($newBank) {

                        $newBank->update([
                            'current_balance' =>
                                $closingBankAccountBalance
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Add new images
                    |--------------------------------------------------------------------------
                    */

                    if ($request->hasFile('images')) {

                        foreach (
                            $request->file('images')
                            as $image
                        ) {

                            if (!$image->isValid()) {
                                continue;
                            }

                            $path = $image->store(
                                'cash_txn_images',
                                'public'
                            );

                            CashTxnImage::create([
                                'txn_id' =>
                                    $transaction->txn_id,

                                'image_url' =>
                                    $path,

                                'added_at' =>
                                    now(),
                            ]);
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Main image
                    |--------------------------------------------------------------------------
                    */

                    $transaction->refresh();

                    if (
                        empty(
                            $transaction->image_url
                        )
                    ) {

                        $firstImage =
                            $transaction
                                ->images()
                                ->orderBy(
                                    'image_id',
                                    'asc'
                                )
                                ->first();

                        if ($firstImage) {

                            $transaction->update([
                                'image_url' =>
                                    $firstImage->image_url
                            ]);
                        }
                    }


                    $transaction->refresh();

                    $transaction->load([
                        'images',
                        'bank',
                        'givenByUser',
                        'givenToUser'
                    ]);

                    return $transaction;
                }
            );


            $this->addImageUrls($result);

            return response()->json([
                'success' => true,
                'message' =>
                    'Cash transaction updated successfully',
                'data' => $result
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to update cash transaction',
                'error' =>
                    $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * ADD IMAGES TO EXISTING TRANSACTION
     * ============================================================
     *
     * POST
     * /api/v1/cash-txn-details/{id}/images
     */
    public function addImages(
        Request $request,
        $id
    ): JsonResponse {

        $validator = Validator::make(
            $request->all(),
            [
                'images' => [
                    'required',
                    'array',
                    'min:1'
                ],

                'images.*' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120'
                ],
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Image validation failed',
                'errors' =>
                    $validator->errors()
            ], 422);
        }

        try {

            $transaction =
                CashTxnDetail::where(
                    'txn_id',
                    $id
                )->first();

            if (!$transaction) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Cash transaction not found'
                ], 404);
            }


            DB::transaction(function () use (
                $request,
                $transaction
            ) {

                foreach (
                    $request->file('images')
                    as $image
                ) {

                    if (!$image->isValid()) {
                        continue;
                    }

                    $path = $image->store(
                        'cash_txn_images',
                        'public'
                    );

                    CashTxnImage::create([
                        'txn_id' =>
                            $transaction->txn_id,

                        'image_url' =>
                            $path,

                        'added_at' =>
                            now(),
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | If main image is empty, use first uploaded image
                    |--------------------------------------------------------------------------
                    */

                    if (
                        empty(
                            $transaction->image_url
                        )
                    ) {

                        $transaction->update([
                            'image_url' =>
                                $path
                        ]);
                    }
                }
            });


            $transaction->refresh();

            $transaction->load([
                'images',
                'bank',
                'givenByUser',
                'givenToUser'
            ]);

            $this->addImageUrls($transaction);

            return response()->json([
                'success' => true,
                'message' =>
                    'Images added successfully',
                'data' => $transaction
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to add images',
                'error' =>
                    $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * DELETE CASH TRANSACTION
     * ============================================================
     */
    public function destroy($id): JsonResponse
    {
        try {

            $transaction =
                CashTxnDetail::with('images')
                    ->where(
                        'txn_id',
                        $id
                    )
                    ->first();

            if (!$transaction) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Cash transaction not found'
                ], 404);
            }


            DB::transaction(function () use (
                $transaction
            ) {

                /*
                |--------------------------------------------------------------------------
                | Restore user opening balance
                |--------------------------------------------------------------------------
                */

                $user = UserDetail::where(
                    'user_id',
                    $transaction->given_by
                )
                    ->lockForUpdate()
                    ->first();

                if ($user) {

                    $user->update([
                        'rak_cash_balance' =>
                            $transaction
                                ->opening_account_balance
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Restore bank opening balance
                |--------------------------------------------------------------------------
                */

                if (
                    $transaction->souce_type === 'BANK' &&
                    $transaction->bank_id
                ) {

                    $bank = BankDetail::where(
                        'bank_id',
                        $transaction->bank_id
                    )
                        ->lockForUpdate()
                        ->first();

                    if ($bank) {

                        $bank->update([
                            'current_balance' =>
                                $transaction
                                    ->opening_bank_account_balance
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Delete physical images
                |--------------------------------------------------------------------------
                */

                foreach (
                    $transaction->images
                    as $image
                ) {

                    if (
                        $image->image_url &&
                        Storage::disk('public')
                            ->exists(
                                $image->image_url
                            )
                    ) {

                        Storage::disk('public')
                            ->delete(
                                $image->image_url
                            );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Delete image records
                |--------------------------------------------------------------------------
                */

                $transaction->images()->delete();


                /*
                |--------------------------------------------------------------------------
                | Delete transaction
                |--------------------------------------------------------------------------
                */

                $transaction->delete();
            });


            return response()->json([
                'success' => true,
                'message' =>
                    'Cash transaction and related data deleted successfully'
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to delete cash transaction',
                'error' =>
                    $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * DELETE ONE IMAGE
     * ============================================================
     *
     * DELETE
     * /api/v1/cash-txn-images/{imageId}
     */
    public function deleteImage(
        $imageId
    ): JsonResponse {

        try {

            $image =
                CashTxnImage::find($imageId);

            if (!$image) {

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Image not found'
                ], 404);
            }


            $transaction =
                CashTxnDetail::where(
                    'txn_id',
                    $image->txn_id
                )->first();


            /*
            |--------------------------------------------------------------------------
            | Delete file
            |--------------------------------------------------------------------------
            */

            if (
                $image->image_url &&
                Storage::disk('public')
                    ->exists(
                        $image->image_url
                    )
            ) {

                Storage::disk('public')
                    ->delete(
                        $image->image_url
                    );
            }


            /*
            |--------------------------------------------------------------------------
            | Delete image record
            |--------------------------------------------------------------------------
            */

            $image->delete();


            /*
            |--------------------------------------------------------------------------
            | Set another image as main image
            |--------------------------------------------------------------------------
            */

            if ($transaction) {

                $nextImage =
                    CashTxnImage::where(
                        'txn_id',
                        $transaction->txn_id
                    )
                        ->orderBy(
                            'image_id',
                            'asc'
                        )
                        ->first();

                $transaction->update([
                    'image_url' =>
                        $nextImage
                            ? $nextImage->image_url
                            : null
                ]);
            }


            return response()->json([
                'success' => true,
                'message' =>
                    'Image deleted successfully'
            ], 200);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Failed to delete image',
                'error' =>
                    $e->getMessage()
            ], 500);
        }
    }


    /**
     * ============================================================
     * BALANCE CALCULATION
     * ============================================================
     */
    private function calculateBalances(
        string $type,
        string $sourceType,
        float $amount,
        float $openingAccountBalance,
        float $openingUserBalance,
        float $openingBankAccountBalance,
        float $openingBankUserBalance
    ): array {

        $closingAccountBalance =
            $openingAccountBalance;

        $closingUserBalance =
            $openingUserBalance;

        $closingBankAccountBalance =
            $openingBankAccountBalance;

        $closingBankUserBalance =
            $openingBankUserBalance;


        /*
        |--------------------------------------------------------------------------
        | CASH
        |--------------------------------------------------------------------------
        */

        if ($type === 'INCOME') {

            $closingAccountBalance =
                $openingAccountBalance +
                $amount;

            $closingUserBalance =
                $openingUserBalance -
                $amount;
        }

        elseif ($type === 'EXPENSE') {

            $closingAccountBalance =
                $openingAccountBalance -
                $amount;

            $closingUserBalance =
                $openingUserBalance +
                $amount;
        }


        /*
        |--------------------------------------------------------------------------
        | BANK
        |--------------------------------------------------------------------------
        */

        if ($sourceType === 'BANK') {

            if ($type === 'INCOME') {

                $closingBankAccountBalance =
                    $openingBankAccountBalance +
                    $amount;

                $closingBankUserBalance =
                    $openingBankUserBalance -
                    $amount;
            }

            elseif ($type === 'EXPENSE') {

                $closingBankAccountBalance =
                    $openingBankAccountBalance -
                    $amount;

                $closingBankUserBalance =
                    $openingBankUserBalance +
                    $amount;
            }
        }


        return [
            $closingAccountBalance,
            $closingUserBalance,
            $closingBankAccountBalance,
            $closingBankUserBalance
        ];
    }


    /**
     * ============================================================
     * STORE VALIDATOR
     * ============================================================
     */
    private function storeValidator(
        Request $request
    ): \Illuminate\Contracts\Validation\Validator {

        return Validator::make(
            $request->all(),
            [

                'type' => [
                    'required',
                    'in:INCOME,EXPENSE,AUTO_ENTRY,CASH_TO_GOLD,PURCHASE_GOLD,SALE_GOLD,AMOUNT_TRANSFER,GOLD_TO_CASH,INTERNAL_TRANSFER,IN_CASH_CONVERTER,OUT_CASH_CONVERTER,CashToGold,CASH_LOAN'
                ],

                'given_to' =>
                    'required|integer',

                'given_by' =>
                    'required|integer',

                'category_id' =>
                    'nullable|integer',

                'amount' =>
                    'required|numeric|min:0',

                'opening_account_balance' =>
                    'required|numeric',

                'opening_user_balance' =>
                    'required|numeric',

                'opening_bank_account_balance' =>
                    'nullable|numeric',

                'opening_bank_user_balance' =>
                    'nullable|numeric',

                'souce_type' => [
                    'required',
                    'in:CASH_ON_HAND,BANK'
                ],

               'bank_id' => [
    'nullable',
    'integer',
    'required_if:souce_type,BANK',
],

'bank_name' => [
    'nullable',
    'string',
    'max:150',
    'required_if:souce_type,BANK',
],

'opening_bank_account_balance' => [
    'nullable',
    'numeric',
    'required_if:souce_type,BANK',
],

                'remarks' =>
                    'nullable|string|max:5000',

                'remainder' =>
                    'nullable|string|max:1500',

                'remainder_at' =>
                    'nullable|date',

                'added_by' =>
                    'required|integer',

                'is_active' =>
                    'nullable|boolean',

                'is_hidden' =>
                    'nullable|boolean',

                'is_show_to_all' =>
                    'nullable|boolean',

                'amount_transfer_id' =>
                    'nullable|integer',

                'cash_to_gold_id' =>
                    'nullable|integer',

                'stock_id' =>
                    'nullable|integer',

                'amnt_transfer_from_head' =>
                    'nullable|boolean',

                'internal_type' =>
                    'nullable|string',

                'retailer_id' =>
                    'nullable|integer',

                'retailer_ob_cash_balance' =>
                    'nullable|numeric',

                'retailer_ob_rtgs_balance' =>
                    'nullable|numeric',

                'txn_type' => [
                    'nullable',
                    'in:NORMAL,ATTENDANCE'
                ],

                'bank_entry_date' =>
                    'nullable|date',

                'machine_vendor_id' =>
                    'nullable|integer',

                'machine_vendor_ob_cash_balance' =>
                    'nullable|numeric',

                'machine_vendor_ob_rtgs_balance' =>
                    'nullable|numeric',

                'is_bill_cash' =>
                    'nullable|boolean',

                'is_payment_cash' =>
                    'nullable|boolean',

                'is_customer_affect' =>
                    'nullable|boolean',

                'is_need_receipt' =>
                    'nullable|boolean',

                'bill_payment_cash_type' => [
                    'nullable',
                    'in:ON_SPOT_NILL,ON_ACCOUNTABLE,PARTIAL_PAYMENT'
                ],

                'partial_amount' =>
                    'nullable|numeric',

                'actual_amount' =>
                    'nullable|numeric',

                'receipt_cash_txn_id' =>
                    'nullable|integer',

                'given_by_arithmetic_operation' => [
                    'nullable',
                    'in:+,-'
                ],

                'given_to_arithmetic_operation' => [
                    'nullable',
                    'in:+,-'
                ],

                'cash_loan_type' => [
                    'nullable',
                    'in:cash_loan_taken,cash_loan_given,interest_payment,interest_receipt'
                ],

                'per_gram_cash' =>
                    'nullable|numeric',

                'over_all_bill_id' =>
                    'nullable|integer',

                'estimate_retailer_bill_id' =>
                    'nullable|integer',

                'estimate_metal_bill_id' =>
                    'nullable|integer',

                'is_admin_head_entry' =>
                    'nullable|boolean',

                'admin_head_txn_id' =>
                    'nullable|integer',

                'images' =>
                    'nullable|array',

                'images.*' => [
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120'
                ],
            ]
        );
    }


    /**
     * ============================================================
     * UPDATE VALIDATOR
     * ============================================================
     */
    private function updateValidator(
        Request $request,
        $id
    ): \Illuminate\Contracts\Validation\Validator {

        return Validator::make(
            $request->all(),
            [

                'type' => [
                    'sometimes',
                    'in:INCOME,EXPENSE,AUTO_ENTRY,CASH_TO_GOLD,PURCHASE_GOLD,SALE_GOLD,AMOUNT_TRANSFER,GOLD_TO_CASH,INTERNAL_TRANSFER,IN_CASH_CONVERTER,OUT_CASH_CONVERTER,CashToGold,CASH_LOAN'
                ],

                'given_to' =>
                    'sometimes|integer',

                'given_by' =>
                    'sometimes|integer',

                'category_id' =>
                    'nullable|integer',

                'amount' =>
                    'sometimes|numeric|min:0',

                'opening_account_balance' =>
                    'sometimes|numeric',

                'opening_user_balance' =>
                    'sometimes|numeric',

                'opening_bank_account_balance' =>
                    'nullable|numeric',

                'opening_bank_user_balance' =>
                    'nullable|numeric',

                'souce_type' => [
                    'sometimes',
                    'in:CASH_ON_HAND,BANK'
                ],

                'bank_id' => [
                    'nullable',
                    'integer',
                    'required_if:souce_type,BANK',
                ],

                'bank_name' => [
                    'nullable',
                    'string',
                    'max:150',
                    'required_if:souce_type,BANK',
                ],

                'opening_bank_account_balance' => [
                    'nullable',
                    'numeric',
                    'required_if:souce_type,BANK',
                ],

                'remarks' =>
                    'nullable|string|max:5000',

                'remainder' =>
                    'nullable|string|max:1500',

                'remainder_at' =>
                    'nullable|date',

                'added_by' =>
                    'sometimes|integer',

                'is_active' =>
                    'sometimes|boolean',

                'is_hidden' =>
                    'sometimes|boolean',

                'is_show_to_all' =>
                    'sometimes|boolean',

                'amount_transfer_id' =>
                    'nullable|integer',

                'cash_to_gold_id' =>
                    'nullable|integer',

                'stock_id' =>
                    'nullable|integer',

                'amnt_transfer_from_head' =>
                    'sometimes|boolean',

                'internal_type' =>
                    'nullable|string',

                'retailer_id' =>
                    'nullable|integer',

                'retailer_ob_cash_balance' =>
                    'nullable|numeric',

                'retailer_ob_rtgs_balance' =>
                    'nullable|numeric',

                'txn_type' =>
                    'sometimes|in:NORMAL,ATTENDANCE',

                'bank_entry_date' =>
                    'nullable|date',

                'machine_vendor_id' =>
                    'nullable|integer',

                'machine_vendor_ob_cash_balance' =>
                    'nullable|numeric',

                'machine_vendor_ob_rtgs_balance' =>
                    'nullable|numeric',

                'is_bill_cash' =>
                    'nullable|boolean',

                'is_payment_cash' =>
                    'nullable|boolean',

                'is_customer_affect' =>
                    'nullable|boolean',

                'is_need_receipt' =>
                    'nullable|boolean',

                'bill_payment_cash_type' =>
                    'nullable|in:ON_SPOT_NILL,ON_ACCOUNTABLE,PARTIAL_PAYMENT',

                'partial_amount' =>
                    'nullable|numeric',

                'actual_amount' =>
                    'nullable|numeric',

                'receipt_cash_txn_id' =>
                    'nullable|integer',

                'given_by_arithmetic_operation' =>
                    'nullable|in:+,-',

                'given_to_arithmetic_operation' =>
                    'nullable|in:+,-',

                'cash_loan_type' =>
                    'nullable|in:cash_loan_taken,cash_loan_given,interest_payment,interest_receipt',

                'per_gram_cash' =>
                    'nullable|numeric',

                'over_all_bill_id' =>
                    'nullable|integer',

                'estimate_retailer_bill_id' =>
                    'nullable|integer',

                'estimate_metal_bill_id' =>
                    'nullable|integer',

                'is_admin_head_entry' =>
                    'nullable|boolean',

                'admin_head_txn_id' =>
                    'nullable|integer',

                'images' =>
                    'nullable|array',

                'images.*' => [
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120'
                ],
            ]
        );
    }


    /**
     * ============================================================
     * CONVERT NULL STRINGS
     * ============================================================
     */
    private function convertNullStringsToNull(
        Request $request
    ): void {

        $fields = [
            'category_id',
            'bank_id',
            'balance',
            'opening_bank_account_balance',
            'opening_bank_user_balance',
            'remarks',
            'remainder',
            'remainder_at',
            'amount_transfer_id',
            'cash_to_gold_id',
            'stock_id',
            'internal_type',
            'retailer_id',
            'retailer_ob_cash_balance',
            'retailer_ob_rtgs_balance',
            'bank_entry_date',
            'machine_vendor_id',
            'machine_vendor_ob_cash_balance',
            'machine_vendor_ob_rtgs_balance',
            'is_bill_cash',
            'is_payment_cash',
            'is_customer_affect',
            'is_need_receipt',
            'bill_payment_cash_type',
            'partial_amount',
            'actual_amount',
            'receipt_cash_txn_id',
            'given_by_arithmetic_operation',
            'given_to_arithmetic_operation',
            'cash_loan_type',
            'per_gram_cash',
            'over_all_bill_id',
            'estimate_retailer_bill_id',
            'estimate_metal_bill_id',
            'admin_head_txn_id',
        ];

        foreach ($fields as $field) {

            if (!$request->has($field)) {
                continue;
            }

            $value = $request->input($field);

            if (
                $value === '' ||
                $value === 'null' ||
                $value === 'NULL'
            ) {

                $request->merge([
                    $field => null
                ]);
            }
        }
    }


    /**
     * ============================================================
     * IMAGE URL HELPER
     * ============================================================
     */
    private function addImageUrls(
        CashTxnDetail $transaction
    ): void {

        $transaction->image_full_url =
            $transaction->image_url
                ? asset(
                    'storage/' .
                    $transaction->image_url
                )
                : null;

        foreach (
            $transaction->images
            as $image
        ) {

            $image->image_full_url =
                asset(
                    'storage/' .
                    $image->image_url
                );
        }
    }
}