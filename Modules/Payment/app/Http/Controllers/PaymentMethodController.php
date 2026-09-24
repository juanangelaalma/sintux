<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Payment\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:payment_methods,name'],
        ]);

        $method = PaymentMethod::create([
            'name' => $data['name'],
            'code' => str($data['name'])->slug()->substr(0, 40)->value(),
        ]);

        return response()->json($method, 201);
    }

    public function update(Request $request, int $method): JsonResponse
    {
        // Sengaja bukan route-model-binding: binding global berjalan
        // sebelum middleware tenant, jadi model akan queried di koneksi
        // central. Resolve manual di dalam controller (tenancy sudah aktif).
        $model = PaymentMethod::findOrFail($method);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:payment_methods,name,'.$model->id],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model->update($data);

        return response()->json($model);
    }
}
