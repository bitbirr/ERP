<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'type' => $this->type,
            'value' => $this->value,
            'is_primary' => $this->is_primary,
            'label' => $this->label,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}