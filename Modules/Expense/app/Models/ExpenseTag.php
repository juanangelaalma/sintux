<?php

namespace Modules\Expense\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExpenseTag extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    /**
     * @return BelongsToMany<Expense, $this>
     */
    public function expenses(): BelongsToMany
    {
        return $this->belongsToMany(Expense::class, 'expense_expense_tag');
    }
}
