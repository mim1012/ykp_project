<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\CustomerService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    protected CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

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

            return $this->jsonPaginated($customers);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to get customers list');
        }
    }

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
                return $this->jsonError('Only store users can create customers', 403);
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

            return $this->jsonSuccess($customer->load(['store', 'branch']), 'Prospect customer created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to create customer');
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $customer = Customer::with(['store', 'branch', 'activatedSale'])->findOrFail($id);

            // 권한 체크
            if ($user->isStore() && $customer->store_id !== $user->store_id) {
                return $this->jsonError('Unauthorized', 403);
            }

            if ($user->isBranch() && $customer->branch_id !== $user->branch_id) {
                return $this->jsonError('Unauthorized', 403);
            }

            return $this->jsonSuccess($customer);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to get customer detail');
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            $user = Auth::user();

            // 권한 체크 - RBAC 적용
            if ($user->isStore() && $customer->store_id !== $user->store_id) {
                return $this->jsonError('Unauthorized', 403);
            }

            if ($user->isBranch() && $customer->branch_id !== $user->branch_id) {
                return $this->jsonError('Unauthorized', 403);
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

            return $this->jsonSuccess($updatedCustomer->load(['store', 'branch', 'activatedSale']), 'Customer updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to update customer');
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($id);
            $user = Auth::user();

            // 권한 체크 - RBAC 적용
            if ($user->isStore() && $customer->store_id !== $user->store_id) {
                return $this->jsonError('Unauthorized', 403);
            }

            if ($user->isBranch() && $customer->branch_id !== $user->branch_id) {
                return $this->jsonError('Unauthorized', 403);
            }

            $this->customerService->deleteCustomer($id);

            return $this->jsonSuccess(null, 'Customer deleted successfully');
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 400);
        }
    }

    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            $stats = $this->customerService->getStatistics($user);

            return $this->jsonSuccess($stats);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to get customer statistics');
        }
    }
}
