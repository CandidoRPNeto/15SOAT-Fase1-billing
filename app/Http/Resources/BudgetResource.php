<?php

namespace App\Http\Resources;

use App\Models\Budget;
use App\Models\BudgetLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Budget */
class BudgetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_order_id' => $this->service_order_id,
            'service_order_number' => $this->service_order_number,
            'client_id' => $this->client_id,
            'status' => $this->status->value,
            'total' => (float) $this->total,
            'diagnosis_notes' => $this->diagnosis_notes,
            'void_reason' => $this->void_reason,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'lines' => $this->lines->map(fn (BudgetLine $line) => [
                'kind' => $line->kind,
                'reference_id' => $line->reference_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => (float) $line->unit_price,
            ])->all(),
        ];
    }
}
