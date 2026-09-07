<?php

namespace Modules\Purchasing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Purchasing\Application\PurchaseTag\CreatePurchaseTag;

class PurchaseTagController extends Controller
{
    public function __construct(
        private readonly CreatePurchaseTag $createPurchaseTag,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $tag = $this->createPurchaseTag->execute(trim($validated['name']));

        return response()->json($tag, 201);
    }
}
