<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string|null $prefix
 * @property string $name
 * @property string $normal_balance
 * @property string $financial_statement
 * @property int|null $sort_order
 */
class AccountType extends Model
{
    protected $table = 'coa_account_types';

    protected $fillable = [
        'code',
        'prefix',
        'name',
        'normal_balance',
        'financial_statement',
        'sort_order',
    ];

    /**
     * @return HasMany<AccountCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(AccountCategory::class, 'account_type_id');
    }
}
