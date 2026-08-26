<?php

namespace Modules\Approval\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Approval\Application\AddApprovalComment;

class ApprovalCommentController extends Controller
{
    public function __construct(
        private readonly AddApprovalComment $addApprovalComment,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'transaction_type' => ['required', 'string'],
            'transaction_id' => ['required', 'integer'],
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $this->addApprovalComment->execute(
            $request->input('transaction_type'),
            (int) $request->input('transaction_id'),
            $user->id,
            $user->name,
            $request->input('content'),
        );

        return back()->with('success', 'Komentar berhasil ditambahkan.');
    }
}
