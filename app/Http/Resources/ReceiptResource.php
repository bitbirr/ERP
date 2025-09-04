<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'number' => $this->number,
            'status' => $this->status,
            'customer_id' => $this->customer_id,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax_total' => $this->tax_total,
            'discount_total' => $this->discount_total,
            'grand_total' => $this->grand_total,
            'paid_total' => $this->paid_total,
            'payment_method' => $this->payment_method,
            'meta' => $this->meta,
            'posted_at' => $this->posted_at,
            'voided_at' => $this->voided_at,
            'refunded_at' => $this->refunded_at,
            'created_by' => $this->created_by,
            'posted_by' => $this->posted_by,
            'voided_by' => $this->voided_by,
            'refunded_by' => $this->refunded_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'lines' => ReceiptLineResource::collection($this->whenLoaded('lines')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}