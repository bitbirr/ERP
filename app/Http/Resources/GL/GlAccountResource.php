<?php

namespace App\Http\Resources\GL;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GlAccountResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'normal_balance' => $this->normal_balance,
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent->id,
                    'code' => $this->parent->code,
                    'name' => $this->parent->name,
                ];
            }),
            'level' => $this->level,
            'is_postable' => $this->is_postable,
            'status' => $this->status,
            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                    'code' => $this->branch->code,
                ];
            }),
            'full_code' => $this->getFullCode(),
            'children_count' => $this->whenLoaded('children', function () {
                return $this->children->count();
            }),
            'has_children' => $this->whenLoaded('children', function () {
                return $this->children->isNotEmpty();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
