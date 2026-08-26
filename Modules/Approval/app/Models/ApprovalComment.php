<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalComment extends Model
{
    protected $fillable = [
        'transaction_type',
        'transaction_id',
        'user_id',
        'user_name',
        'content',
    ];
}
