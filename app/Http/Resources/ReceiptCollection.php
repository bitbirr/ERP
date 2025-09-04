<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReceiptCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => ReceiptResource::collection($this->collection),
            'meta' => [
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
            ],
            'links' => [
                'first' => $this->resource->url(1),
                'last' => $this->resource->url($this->resource->lastPage()),
                'prev' => $this->resource->previousPageUrl(),
                'next' => $this->resource->nextPageUrl(),
            ],
        ];
    }
}