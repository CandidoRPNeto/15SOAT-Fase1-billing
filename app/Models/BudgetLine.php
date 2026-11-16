<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $kind
 * @property int $reference_id
 * @property string $description
 * @property int $quantity
 * @property string $unit_price
 */
class BudgetLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['budget_id', 'kind', 'reference_id', 'description', 'quantity', 'unit_price'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }
}
