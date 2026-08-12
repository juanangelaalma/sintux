<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $account_type_id
 * @property string $code
 * @property string|null $prefix
 * @property string $name
 * @property int|null $sort_order
 * @property-read AccountType $accountType
 */
class AccountCategory extends Model
{
    protected $table = 'coa_account_categories';

    protected $fillable = [
        'account_type_id',
        'code',
        'prefix',
        'name',
        'sort_order',
    ];

    /**
     * @return BelongsTo<AccountType, $this>
     */
    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    /**
     * @return HasMany<ChartOfAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'account_category_id');
    }
}
