<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $rate
 * @property string $type single|group
 * @property bool $is_withholding
 * @property bool $dpp_multiplier
 * @property int|null $input_account_id
 * @property int|null $output_account_id
 * @property bool $is_active
 * @property-read ChartOfAccount|null $inputAccount
 * @property-read ChartOfAccount|null $outputAccount
 * @property-read Collection<int, TaxGroupMember> $groupMembers
 */
class Tax extends Model
{
    public const TYPE_SINGLE = 'single';

    public const TYPE_GROUP = 'group';

    protected $fillable = [
        'name',
        'code',
        'rate',
        'type',
        'is_withholding',
        'dpp_multiplier',
        'input_account_id',
        'output_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_withholding' => 'boolean',
            'dpp_multiplier' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Account used to post purchase/input tax.
     *
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function inputAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'input_account_id');
    }

    /**
     * Account used to post sales/output tax.
     *
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function outputAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'output_account_id');
    }

    /**
     * Members when this tax is a group.
     *
     * @return HasMany<TaxGroupMember, $this>
     */
    public function groupMembers(): HasMany
    {
        return $this->hasMany(TaxGroupMember::class, 'tax_group_id');
    }
}
