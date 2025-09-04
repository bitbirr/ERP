<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'uom' => $this->uom,
            'qty' => $this->qty,
            'price' => $this->price,
            'discount' => $this->discount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}