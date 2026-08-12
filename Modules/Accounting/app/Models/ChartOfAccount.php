<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property int $account_category_id
 * @property int|null $default_tax_id
 * @property string|null $seed_key
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_header
 * @property-read AccountCategory $category
 * @property-read Tax|null $defaultTax
 */
class ChartOfAccount extends Model
{
    use SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'parent_id',
        'account_category_id',
        'default_tax_id',
        'seed_key',
        'code',
        'name',
        'description',
        'is_header',
    ];

    protected function casts(): array
    {
        return [
            'is_header' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AccountCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id');
    }

    /**
     * Default tax recommendation when this account is selected.
     *
     * @return BelongsTo<Tax, $this>
     */
    public function defaultTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'default_tax_id');
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ChartOfAccount, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
