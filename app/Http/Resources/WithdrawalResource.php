<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'withdraw_no' => $this->withdraw_no,
            'payment_method' => $this->payment_method,
            'payout_account' => $this->payout_account,
            'coin_amount' => $this->coin_amount,
            'usd_amount' => $this->usd_amount,
            'stripe_transfer_id' => $this->stripe_transfer_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
