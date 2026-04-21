<?php

namespace App\Modules\Sales\Controllers;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->service->paginate($request->only(['search', 'is_active', 'per_page']));
        return response()->json($customers);
    }

    // Flat list for POS dropdowns
    public function all(): JsonResponse
    {
        return response()->json(['data' => $this->service->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:150',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:150',
            'address'       => 'nullable|string',
            'customer_type' => 'nullable|in:retail,wholesale',
            'credit_limit'  => 'nullable|numeric|min:0',
        ]);

        $customer = $this->service->store($request->only([
            'name', 'phone', 'email', 'address', 'customer_type', 'credit_limit',
        ]));

        return response()->json(['data' => $customer], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'name'          => 'sometimes|required|string|max:150',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:150',
            'address'       => 'nullable|string',
            'customer_type' => 'nullable|in:retail,wholesale',
            'credit_limit'  => 'nullable|numeric|min:0',
            'is_active'     => 'nullable|boolean',
        ]);

        $updated = $this->service->update($customer, $request->only([
            'name', 'phone', 'email', 'address', 'customer_type', 'credit_limit', 'is_active',
        ]));

        return response()->json(['data' => $updated]);
    }
}
