<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $color
 */
class PurchaseTag extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    /**
     * @return BelongsToMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class);
    }
}
