<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalTransactionType extends Model
{
    protected $fillable = [
        'module',
        'key',
        'label',
        'criteria_basis',
    ];

    /**
     * @return HasMany<ApprovalRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class, 'transaction_type_id');
    }
}
