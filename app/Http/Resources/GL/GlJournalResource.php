<?php

namespace App\Http\Resources\GL;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GlJournalResource extends JsonResource
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
            'journal_no' => $this->journal_no,
            'journal_date' => $this->journal_date,
            'currency' => $this->currency,
            'fx_rate' => $this->fx_rate,
            'source' => $this->source,
            'reference' => $this->reference,
            'memo' => $this->memo,
            'status' => $this->status,
            'posted_at' => $this->posted_at,
            'posted_by' => $this->whenLoaded('postedBy', function () {
                return [
                    'id' => $this->postedBy->id,
                    'name' => $this->postedBy->name,
                ];
            }),
            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                    'code' => $this->branch->code,
                ];
            }),
            'external_ref' => $this->external_ref,
            'lines' => $this->whenLoaded('lines', function () {
                return $this->lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'line_no' => $line->line_no,
                        'account' => $line->whenLoaded('account', function () use ($line) {
                            return [
                                'id' => $line->account->id,
                                'code' => $line->account->code,
                                'name' => $line->account->name,
                                'type' => $line->account->type,
                            ];
                        }),
                        'debit' => $line->debit,
                        'credit' => $line->credit,
                        'memo' => $line->memo,
                    ];
                });
            }),
            'totals' => [
                'debit' => $this->getTotalDebit(),
                'credit' => $this->getTotalCredit(),
                'lines_count' => $this->getLineCount(),
            ],
            'can_post' => $this->canBePosted(),
            'can_edit' => $this->canBeEdited(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
