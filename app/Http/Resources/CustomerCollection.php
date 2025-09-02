<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'type' => $customer->type,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'tax_id' => $customer->tax_id,
                    'is_active' => $customer->is_active,
                    'primary_contact' => $customer->primaryContact() ? [
                        'type' => $customer->primaryContact()->type,
                        'value' => $customer->primaryContact()->value,
                    ] : null,
                    'primary_address' => $customer->primaryAddress() ? [
                        'region' => $customer->primaryAddress()->region,
                        'zone' => $customer->primaryAddress()->zone,
                        'woreda' => $customer->primaryAddress()->woreda,
                        'kebele' => $customer->primaryAddress()->kebele,
                        'full_address' => $customer->primaryAddress()->full_address,
                    ] : null,
                    'tags' => $customer->whenLoaded('tags', function () use ($customer) {
                        return $customer->tags->map(function ($tag) {
                            return [
                                'name' => $tag->name,
                                'color' => $tag->display_color,
                            ];
                        });
                    }),
                    'segments' => $customer->whenLoaded('segments', function () use ($customer) {
                        return $customer->segments->pluck('name');
                    }),
                    'created_at' => $customer->created_at,
                    'updated_at' => $customer->updated_at,
                ];
            }),
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $this->currentPage(),
                'from' => $this->firstItem(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'to' => $this->lastItem(),
                'total' => $this->total(),
            ],
        ];
    }
}