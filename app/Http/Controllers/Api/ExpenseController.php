<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Expense::with(['store', 'createdBy']);

            if ($user->isStore()) {
                $query->where('store_id', $user->store_id);
            } elseif ($user->isBranch()) {
                $query->whereHas('store', fn ($q) => $q->where('branch_id', $user->branch_id));
            }

            if ($request->has('start_date')) {
                $query->where('expense_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('expense_date', '<=', $request->end_date);
            }

            if ($request->has('year') && $request->has('month')) {
                $query->whereYear('expense_date', $request->year)
                      ->whereMonth('expense_date', $request->month);
            } elseif ($request->has('month')) {
                $monthParts = explode('-', $request->month);
                if (count($monthParts) === 2) {
                    $query->whereYear('expense_date', $monthParts[0])
                          ->whereMonth('expense_date', $monthParts[1]);
                }
            }

            if ($request->filled('store_id')) {
                $query->where('store_id', $request->store_id);
            }

            if ($request->filled('search')) {
                $query->where('description', 'like', '%' . $request->search . '%');
            }

            $expenses = $query->orderBy('expense_date', 'desc')
                              ->paginate($request->input('per_page', 30));

            return $this->jsonPaginated($expenses);
        } catch (\Exception $e) {
            return $this->handleException($e, '지출 목록 조회 실패');
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'expense_date' => 'required|date',
                'description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
            ]);

            $user = Auth::user();

            if (!$user->isStore()) {
                return $this->jsonError('매장 사용자만 지출을 등록할 수 있습니다.', 403);
            }

            $validated['store_id'] = $user->store_id;
            $validated['created_by'] = $user->id;

            $expense = Expense::create($validated);

            return $this->jsonSuccess($expense->load(['store', 'createdBy']), '지출이 등록되었습니다.', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, '지출 등록 실패');
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Expense::query();

            if ($user->isStore()) {
                $query->where('store_id', $user->store_id);
            } elseif ($user->isBranch()) {
                $query->whereHas('store', fn ($q) => $q->where('branch_id', $user->branch_id));
            }

            if ($request->has('year') && $request->has('month')) {
                $summary = $query->whereYear('expense_date', $request->year)
                                 ->whereMonth('expense_date', $request->month)
                                 ->selectRaw('COUNT(*) as count, SUM(amount) as total, AVG(amount) as average')
                                 ->first();

                return $this->jsonSuccess([
                    'period' => sprintf('%04d-%02d', $request->year, $request->month),
                    'type' => 'monthly',
                    'count' => intval($summary->count ?? 0),
                    'total' => floatval($summary->total ?? 0),
                    'average' => floatval($summary->average ?? 0),
                ]);
            }

            if ($request->has('year')) {
                $monthlyData = $query->whereYear('expense_date', $request->year)
                    ->selectRaw('EXTRACT(MONTH FROM expense_date) as month, COUNT(*) as count, SUM(amount) as total')
                    ->groupByRaw('EXTRACT(MONTH FROM expense_date)')
                    ->orderBy('month')
                    ->get()
                    ->map(fn ($item) => [
                        'month' => intval($item->month),
                        'count' => intval($item->count),
                        'total' => floatval($item->total ?? 0),
                    ]);

                return $this->jsonSuccess([
                    'period' => intval($request->year),
                    'type' => 'yearly',
                    'monthly_data' => $monthlyData,
                ]);
            }

            return $this->jsonError('year 또는 month 파라미터가 필요합니다.', 400);
        } catch (\Exception $e) {
            return $this->handleException($e, '지출 요약 조회 실패');
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $expense = Expense::with(['store', 'createdBy'])->findOrFail($id);

            if ($user->isStore() && $expense->store_id !== $user->store_id) {
                return $this->jsonError('권한이 없습니다.', 403);
            }

            if ($user->isBranch()) {
                $expense->load('store');
                if ($expense->store && $expense->store->branch_id !== $user->branch_id) {
                    return $this->jsonError('권한이 없습니다.', 403);
                }
            }

            return $this->jsonSuccess($expense);
        } catch (\Exception $e) {
            return $this->handleException($e, '지출 상세 조회 실패');
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $expense = Expense::findOrFail($id);
            $user = Auth::user();

            if (!$user->isStore() || $expense->store_id !== $user->store_id) {
                return $this->jsonError('권한이 없습니다.', 403);
            }

            $validated = $request->validate([
                'expense_date' => 'sometimes|required|date',
                'description' => 'sometimes|required|string|max:255',
                'amount' => 'sometimes|required|numeric|min:0',
            ]);

            $expense->update($validated);

            return $this->jsonSuccess($expense->fresh(['store', 'createdBy']), '지출이 수정되었습니다.');
        } catch (\Exception $e) {
            return $this->handleException($e, '지출 수정 실패');
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $expense = Expense::findOrFail($id);
            $user = Auth::user();

            if (!$user->isStore() || $expense->store_id !== $user->store_id) {
                return $this->jsonError('권한이 없습니다.', 403);
            }

            $expense->delete();

            return $this->jsonSuccess(null, '지출이 삭제되었습니다.');
        } catch (\Exception $e) {
            return $this->handleException($e, '지출 삭제 실패');
        }
    }
}
