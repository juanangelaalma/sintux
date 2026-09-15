<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member row of a group tax, ordered by position.
 *
 * @property int $id
 * @property int $tax_group_id
 * @property int $member_tax_id
 * @property int $position
 * @property bool $is_compound
 * @property-read Tax|null $group
 * @property-read Tax|null $member
 */
class TaxGroupMember extends Model
{
    protected $fillable = [
        'tax_group_id',
        'member_tax_id',
        'position',
        'is_compound',
    ];

    protected function casts(): array
    {
        return [
            'tax_group_id' => 'integer',
            'member_tax_id' => 'integer',
            'position' => 'integer',
            'is_compound' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tax, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_group_id');
    }

    /**
     * @return BelongsTo<Tax, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'member_tax_id');
    }
}
