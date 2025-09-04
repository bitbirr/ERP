<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax_total' => $this->tax_total,
            'discount_total' => $this->discount_total,
            'grand_total' => $this->grand_total,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'cancelled_by' => $this->cancelled_by,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'lines' => OrderLineResource::collection($this->whenLoaded('lines')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'approver' => new UserResource($this->whenLoaded('approver')),
        ];
    }
}