<?php

namespace App\Http\Controllers\Api;

use App\Application\Services\PayrollService;
use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayrollController extends Controller
{
    protected PayrollService $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    /**
     * 급여 목록 조회 (월별/대리점별)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payroll::with('dealerProfile');

        // 필터링
        if ($request->has('year_month')) {
            $query->where('year_month', $request->year_month);
        }

        if ($request->has('dealer_code')) {
            $query->where('dealer_code', $request->dealer_code);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // 정렬
        $sortField = $request->get('sort', 'year_month');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder)
            ->orderBy('employee_name', 'asc');

        $limit = min($request->get('limit', 50), 100);
        $payrolls = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $payrolls->items(),
            'meta' => [
                'pagination' => [
                    'page' => $payrolls->currentPage(),
                    'limit' => $payrolls->perPage(),
                    'total' => $payrolls->total(),
                    'pages' => $payrolls->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * 급여 등록 (엑셀 수기입력 방식)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'dealer_code' => 'required|string|max:20',
            'employee_id' => 'required|string|max:20',
            'employee_name' => 'required|string|max:50',
            'position' => 'nullable|string|max:30',
            'base_salary' => 'required|numeric|min:0',
            'bonus_amount' => 'nullable|numeric|min:0',
            'deduction_amount' => 'nullable|numeric|min:0',
            'memo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Service를 통한 인센티브 자동 계산
        $incentive = $this->payrollService->calculateIncentive(
            $data['year_month'],
            $data['dealer_code'],
            $data['employee_id'],
            $data['position'] ?? null
        );
        $data['incentive_amount'] = $incentive;

        // Service를 통한 총 급여 계산
        $data['total_salary'] = $data['base_salary'] + $incentive + ($data['bonus_amount'] ?? 0) - ($data['deduction_amount'] ?? 0);

        try {
            $payroll = Payroll::create($data);

            return response()->json([
                'success' => true,
                'data' => $payroll->load('dealerProfile'),
                'message' => '급여가 등록되었습니다.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '급여 등록 중 오류: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * 급여 수정 (엑셀 인라인 편집 방식)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $payroll = Payroll::find($id);

        if (! $payroll) {
            return response()->json([
                'success' => false,
                'message' => '급여 내역을 찾을 수 없습니다.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'base_salary' => 'sometimes|numeric|min:0',
            'bonus_amount' => 'sometimes|numeric|min:0',
            'deduction_amount' => 'sometimes|numeric|min:0',
            'position' => 'sometimes|string|max:30',
            'memo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Service를 통한 인센티브 재계산
        $incentive = $this->payrollService->calculateIncentive(
            $payroll->year_month,
            $payroll->dealer_code,
            $payroll->employee_id,
            $payroll->position
        );
        $data['incentive_amount'] = $incentive;

        // Service를 통한 총 급여 재계산
        $baseSalary = $data['base_salary'] ?? $payroll->base_salary;
        $bonusAmount = $data['bonus_amount'] ?? $payroll->bonus_amount;
        $deductionAmount = $data['deduction_amount'] ?? $payroll->deduction_amount;

        $data['total_salary'] = $baseSalary + $incentive + $bonusAmount - $deductionAmount;

        $payroll->update($data);

        return response()->json([
            'success' => true,
            'data' => $payroll->load('dealerProfile'),
            'message' => '급여가 수정되었습니다.',
        ]);
    }

    /**
     * 지급 상태 토글 (엑셀 체크박스 방식)
     */
    public function togglePaymentStatus(Request $request, string $id): JsonResponse
    {
        $payroll = Payroll::find($id);

        if (! $payroll) {
            return response()->json([
                'success' => false,
                'message' => '급여 내역을 찾을 수 없습니다.',
            ], 404);
        }

        $newStatus = $payroll->payment_status === 'paid' ? 'pending' : 'paid';
        $paymentDate = $newStatus === 'paid' ? ($request->payment_date ?? now()->toDateString()) : null;

        $payroll->update([
            'payment_status' => $newStatus,
            'payment_date' => $paymentDate,
        ]);

        return response()->json([
            'success' => true,
            'data' => $payroll,
            'message' => $newStatus === 'paid' ? '지급 완료 처리되었습니다.' : '미지급으로 변경되었습니다.',
        ]);
    }

    /**
     * 월별 급여 요약 (Service Layer 사용)
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        $yearMonth = $request->get('year_month', now()->format('Y-m'));
        $dealerCode = $request->get('dealer_code');

        try {
            $summary = $this->payrollService->processMonthlyPayroll($yearMonth, $dealerCode);

            return response()->json([
                'success' => true,
                'data' => $summary,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '급여 요약 처리 중 오류: '.$e->getMessage(),
            ], 500);
        }
    }
}
