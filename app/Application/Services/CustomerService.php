<?php

namespace App\Application\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    /**
     * Auto-link prospect customer to sale when sale is created
     * 개통표 입력 시 전화번호로 기존 가망고객을 찾아서 자동으로 개통고객으로 전환
     */
    public function autoLinkProspectToSale(Sale $sale): ?Customer
    {
        if (!$sale->phone_number || !$sale->store_id) {
            return null;
        }

        // 해당 매장에서 같은 전화번호를 가진 가망고객 찾기
        $prospect = Customer::where('store_id', $sale->store_id)
            ->where('phone_number', $sale->phone_number)
            ->where('customer_type', 'prospect')
            ->first();

        if (!$prospect) {
            return null;
        }

        // 가망고객을 개통고객으로 전환
        $prospect->linkToSale($sale);

        Log::info('Prospect customer linked to sale', [
            'customer_id' => $prospect->id,
            'sale_id' => $sale->id,
            'phone_number' => $sale->phone_number,
        ]);

        return $prospect;
    }

    /**
     * Get customer lifetime value (total sales amount for this customer)
     */
    public function getCustomerLifetimeValue(int $customerId): float
    {
        $customer = Customer::findOrFail($customerId);
        return $customer->getLifetimeValue();
    }

    /**
     * Get customers list with filters and RBAC
     */
    public function getCustomers(User $user, array $filters = [])
    {
        $query = Customer::with(['store', 'branch', 'activatedSale']);

        // RBAC 필터링
        if ($user->isStore()) {
            $query->where('store_id', $user->store_id);
        } elseif ($user->isBranch()) {
            $query->where('branch_id', $user->branch_id);
        }
        // 본사는 전체 조회 가능

        // 고객 유형 필터
        if (isset($filters['customer_type'])) {
            $query->where('customer_type', $filters['customer_type']);
        }

        // 상태 필터
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 검색 (이름 또는 전화번호)
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'ILIKE', "%{$search}%")
                  ->orWhere('phone_number', 'LIKE', "%{$search}%");
            });
        }

        // 기간 필터
        if (isset($filters['start_date'])) {
            $query->where('first_visit_date', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->where('first_visit_date', '<=', $filters['end_date']);
        }

        // 정렬
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // 페이지네이션
        $perPage = $filters['per_page'] ?? 30;
        return $query->paginate($perPage);
    }

    /**
     * Create a new prospect customer
     */
    public function createProspect(array $data, User $user): Customer
    {
        // 매장 ID 설정 (RBAC)
        if ($user->isStore()) {
            $data['store_id'] = $user->store_id;
            $data['branch_id'] = $user->branch_id;
        }

        // 기본값 설정
        $data['customer_type'] = 'prospect';
        $data['status'] = 'active';
        $data['created_by'] = $user->id;

        return Customer::create($data);
    }

    /**
     * Update customer
     */
    public function updateCustomer(int $customerId, array $data): Customer
    {
        $customer = Customer::findOrFail($customerId);

        // 개통고객은 일부 필드만 수정 가능
        if ($customer->customer_type === 'activated') {
            $allowedFields = ['notes', 'last_contact_date'];
            $data = array_intersect_key($data, array_flip($allowedFields));
        }

        $customer->update($data);

        return $customer->fresh();
    }

    /**
     * Delete customer
     */
    public function deleteCustomer(int $customerId): bool
    {
        $customer = Customer::findOrFail($customerId);

        // 개통고객은 삭제 불가 (데이터 무결성)
        if ($customer->customer_type === 'activated') {
            throw new \Exception('Cannot delete activated customer. Customer is linked to a sale.');
        }

        return $customer->delete();
    }

    /**
     * Get customer statistics
     */
    public function getStatistics(User $user): array
    {
        $query = Customer::query();

        // RBAC 필터링
        if ($user->isStore()) {
            $query->where('store_id', $user->store_id);
        } elseif ($user->isBranch()) {
            $query->where('branch_id', $user->branch_id);
        }

        return [
            'total_customers' => (clone $query)->count(),
            'prospects' => (clone $query)->prospects()->count(),
            'activated' => (clone $query)->activated()->count(),
            'conversion_rate' => $this->calculateConversionRate($query),
            'recent_prospects' => (clone $query)->prospects()
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
            'this_month_conversions' => (clone $query)->activated()
                ->where('updated_at', '>=', now()->startOfMonth())
                ->count(),
        ];
    }

    /**
     * Calculate conversion rate (prospect → activated)
     */
    private function calculateConversionRate($query): float
    {
        $total = (clone $query)->count();

        if ($total === 0) {
            return 0;
        }

        $activated = (clone $query)->activated()->count();

        return round(($activated / $total) * 100, 2);
    }
}
