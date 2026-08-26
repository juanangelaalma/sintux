<?php

namespace Modules\Approval\Application;

use Modules\Approval\Models\ApprovalComment;

class AddApprovalComment
{
    public function execute(string $transactionType, int $transactionId, int $userId, string $userName, string $content): ApprovalComment
    {
        return ApprovalComment::create([
            'transaction_type' => $transactionType,
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'user_name' => $userName,
            'content' => $content,
        ]);
    }
}
