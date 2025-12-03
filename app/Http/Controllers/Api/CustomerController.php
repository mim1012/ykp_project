<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\CustomerService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    protected CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Get customers list
     * GET /api/customers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $filters = [
                'customer_type' => $request->input('customer_type'),
                'status' => $request->input('status'),
                'search' => $request->input('search'),
                'store_id' => $request->input('store_id'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'sort_by' => $request->input('sort_by', 'created_at'),
                'sort_order' => $request->input('sort_order', 'desc'),
                'per_page' => $request->input('per_page', 30),
            ];

            $customers = $this->customerService->getCustomers($user, $filters);

            return response()->json([
                'success' => true,
                'data' => $customers->items(),
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get customers list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get customers list',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new prospect customer
     * POST /api/customers
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phone_number' => 'required|string|max:20',
                'customer_name' => 'required|string|max:100',
                'birth_date' => 'nullable|date',
                'current_device' => 'nullable|string|max:255',
                'first_visit_date' => 'nullable|date',
                'notes' => 'nullable|string',
            ]);

            $user = Auth::user();

            // 매장 사용자만 생성 가능
            if (!$user->isStore()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only store users can create customers',
                ], 403);
            }

            // 중복 체크
            $existing = Customer::where('store_id', $user->store_id)
                ->where('phone_number', $validated['phone_number'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer with this phone number already exists',
                    'data' => $existing,
                ], 409);
            }

            $customer = $this->customerService->createProspect($validated, $user);

            return response()->json([
                'success' => true,
                'message' => 'Prospect customer created successfully',
                'data' => $customer->load(['store', 'branch']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create customer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer detail
     * GET /api/customers/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $customer = Customer::with(['store', 'branch', 'activatedSale'])->findOrFail($id);

            // 권한 체크
            if ($user->isStore() && $customer->store_id !== $user->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if ($user->isBranch() && $customer->branch_id !== $user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $customer,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get customer detail', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get customer detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update customer
     * PUT /api/customers/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            $user = Auth::user();

            // 권한 체크 - RBAC 적용
            if ($user->isStore() && $customer->store_id !== $user->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if ($user->isBranch() && $customer->branch_id !== $user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $validated = $request->validate([
                'customer_name' => 'sometimes|required|string|max:100',
                'phone_number' => 'sometimes|required|string|max:20',
                'birth_date' => 'nullable|date',
                'current_device' => 'nullable|string|max:255',
                'first_visit_date' => 'nullable|date',
                'last_contact_date' => 'nullable|date',
                'notes' => 'nullable|string',
                'status' => 'sometimes|in:active,inactive,converted',
            ]);

            $updatedCustomer = $this->customerService->updateCustomer($id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $updatedCustomer->load(['store', 'branch', 'activatedSale']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update customer', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete customer
     * DELETE /api/customers/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            $user = Auth::user();

            // 권한 체크 - RBAC 적용
            if ($user->isStore() && $customer->store_id !== $user->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if ($user->isBranch() && $customer->branch_id !== $user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $this->customerService->deleteCustomer($id);

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get customer statistics
     * GET /api/customers/statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            $stats = $this->customerService->getStatistics($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get customer statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
