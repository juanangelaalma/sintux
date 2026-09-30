<?php

namespace Modules\Expense\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $expense_id
 * @property int $position
 * @property int $account_id
 * @property string $account_name
 * @property string|null $description
 * @property int|null $tax_id
 * @property float $tax_rate
 * @property float $amount
 * @property float $amount_before_tax
 * @property float $tax_amount
 * @property array<int, array{tax_id: int, rate: float, amount: float}>|null $tax_breakdown
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExpenseLine extends Model
{
    protected $fillable = [
        'expense_id',
        'position',
        'account_id',
        'account_name',
        'description',
        'tax_id',
        'tax_rate',
        'amount',
        'amount_before_tax',
        'tax_amount',
        'tax_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'tax_rate' => 'decimal:4',
            'amount' => 'decimal:4',
            'amount_before_tax' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'tax_breakdown' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
