<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'type' => $this->type,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'tax_id' => $this->tax_id,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'contacts' => $this->whenLoaded('contacts', function () {
                return $this->contacts->map(function ($contact) {
                    return [
                        'id' => $contact->id,
                        'type' => $contact->type,
                        'value' => $contact->value,
                        'label' => $contact->label,
                        'is_primary' => $contact->is_primary,
                    ];
                });
            }),
            'addresses' => $this->whenLoaded('addresses', function () {
                return $this->addresses->map(function ($address) {
                    return [
                        'id' => $address->id,
                        'type' => $address->type,
                        'region' => $address->region,
                        'zone' => $address->zone,
                        'woreda' => $address->woreda,
                        'kebele' => $address->kebele,
                        'city' => $address->city,
                        'street_address' => $address->street_address,
                        'postal_code' => $address->postal_code,
                        'latitude' => $address->latitude,
                        'longitude' => $address->longitude,
                        'is_primary' => $address->is_primary,
                        'full_address' => $address->full_address,
                    ];
                });
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->display_color,
                    ];
                });
            }),
            'segments' => $this->whenLoaded('segments', function () {
                return $this->segments->map(function ($segment) {
                    return [
                        'id' => $segment->id,
                        'name' => $segment->name,
                        'description' => $segment->description,
                    ];
                });
            }),
            'notes' => $this->whenLoaded('notes', function () {
                return $this->notes->map(function ($note) {
                    return [
                        'id' => $note->id,
                        'content' => $note->content,
                        'type' => $note->type,
                        'is_pinned' => $note->is_pinned,
                        'created_by' => $note->whenLoaded('creator', function () {
                            return [
                                'id' => $note->creator->id,
                                'name' => $note->creator->name,
                            ];
                        }),
                        'created_at' => $note->created_at,
                    ];
                });
            }),
            'interactions' => $this->whenLoaded('interactions', function () {
                return $this->interactions->map(function ($interaction) {
                    return [
                        'id' => $interaction->id,
                        'type' => $interaction->type,
                        'direction' => $interaction->direction,
                        'description' => $interaction->description,
                        'occurred_at' => $interaction->occurred_at,
                        'type_display' => $interaction->type_display,
                        'direction_display' => $interaction->direction_display,
                        'created_by' => $interaction->whenLoaded('creator', function () {
                            return [
                                'id' => $interaction->creator->id,
                                'name' => $interaction->creator->name,
                            ];
                        }),
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}