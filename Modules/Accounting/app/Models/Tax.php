<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $rate
 * @property int|null $input_account_id
 * @property int|null $output_account_id
 * @property bool $is_active
 * @property-read ChartOfAccount|null $inputAccount
 * @property-read ChartOfAccount|null $outputAccount
 */
class Tax extends Model
{
    protected $fillable = [
        'name',
        'code',
        'rate',
        'input_account_id',
        'output_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
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
}
