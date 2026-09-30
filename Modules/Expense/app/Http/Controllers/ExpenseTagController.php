<?php

namespace Modules\Expense\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Expense\Application\ExpenseTag\CreateExpenseTag;

/**
 * Buat tag biaya dari ketikan langsung di kolom 7 form ("tambah baru").
 * Mengembalikan JSON ringan supaya FE tidak perlu reload halaman.
 */
class ExpenseTagController extends Controller
{
    public function __construct(
        private readonly CreateExpenseTag $createExpenseTag,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        return response()->json($this->createExpenseTag->execute($data['name']), 201);
    }
}
