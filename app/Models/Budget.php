<?php

namespace App\Models;

use App\Domain\Billing\BudgetStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $service_order_id
 * @property string $service_order_number
 * @property int $client_id
 * @property BudgetStatus $status
 * @property string $total
 * @property string|null $diagnosis_notes
 * @property string $saga_id
 * @property string $correlation_id
 * @property string|null $void_reason
 * @property Carbon|null $decided_at
 */
class Budget extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'service_order_id', 'service_order_number', 'client_id', 'status', 'total',
        'diagnosis_notes', 'saga_id', 'correlation_id', 'void_reason', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['status' => BudgetStatus::class, 'total' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
