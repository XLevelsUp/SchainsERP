<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashTransactionReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Format Name (Sender => Recipient)
        $senderName = $this->givenByUser->name ?? '-';
        if (($this->givenByUser->category_name ?? '') === 'RETAILERS' && !empty($this->givenByUser->user_name)) {
            $senderName .= '(' . $this->givenByUser->user_name . ')';
        }

        $recipientName = $this->givenToUser->name ?? '-';
        if (($this->givenToUser->category_name ?? '') === 'RETAILERS' && !empty($this->givenToUser->user_name)) {
            $recipientName .= '(' . $this->givenToUser->user_name . ')';
        }

        $name = $senderName . ' => ' . $recipientName;

        // Source Type mapping
        $sourceType = $this->payment_method ?? 'CASH';
        if ($this->payment_method === 'BANK' && $this->bank) {
            $sourceType .= ' (' . $this->bank->account_name . ')';
        }

        // Remarks logic (Hide status)
        $remarks = $this->remarks;
        if ($this->is_hide) {
            $remarks = "(HIDE) " . $remarks;
        }

        // Dynamic Type formatting (Live vs Journal for Gold)
        $typeLabel = $this->type;
        if ($this->type === 'SALE_GOLD' && $this->cashToGold) {
            $typeLabel = $this->cashToGold->is_live ? 'SALE GOLD LIVE' : 'SALE GOLD JOURNAL';
        } elseif ($this->type === 'PURCHASE_GOLD' && $this->cashToGold) {
            $typeLabel = $this->cashToGold->is_live ? 'PURCHASE GOLD LIVE' : 'PURCHASE GOLD JOURNAL';
        }

        // Balance Determination
        // In the new schema, we store explicit opening/closing for both sender and recipient.
        // For the report, we typically show the Recipient's opening/closing for IN transactions, 
        // and Sender's for OUT transactions, representing the Cash Account.
        $openingBalance = 0;
        $closingBalance = 0;

        if (in_array($this->type, ['INCOME', 'SALE_GOLD', 'CASH_TO_GOLD', 'AUTO_ENTRY'])) {
            // Money is coming IN to recipient
            $openingBalance = $this->recipient_opening_cash + $this->recipient_opening_rtgs;
            $closingBalance = $this->recipient_closing_cash + $this->recipient_closing_rtgs;
        } else {
            // Money is going OUT from sender (EXPENSE, PURCHASE_GOLD, GOLD_TO_CASH)
            $openingBalance = $this->sender_opening_cash + $this->sender_opening_rtgs;
            $closingBalance = $this->sender_closing_cash + $this->sender_closing_rtgs;
        }

        return [
            'id'              => $this->txn_id,
            'date'            => $this->created_at ? $this->created_at->format('d-M-Y H:i A') : '-',
            'bank_entry_date' => $this->bank_entry_date ? date('d-M-Y', strtotime($this->bank_entry_date)) : '-',
            'name'            => $name,
            'category_name'   => $this->category->category_name ?? '-',
            'type_label'      => $typeLabel,
            'source_type'     => $sourceType,
            'opening_balance' => round($openingBalance, 2),
            'amount'          => round($this->amount, 2),
            'closing_balance' => round($closingBalance, 2),
            'remarks'         => $remarks
        ];
    }
}
