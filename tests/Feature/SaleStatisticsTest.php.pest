<?php

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => '강남지사']);
    $this->store = Store::factory()->create([
        'name' => '강남점',
        'branch_id' => $this->branch->id,
    ]);

    $this->storeUser = User::factory()->create([
        'role' => 'store',
        'branch_id' => $this->branch->id,
        'store_id' => $this->store->id,
    ]);

    $this->branchUser = User::factory()->create([
        'role' => 'branch',
        'branch_id' => $this->branch->id,
        'store_id' => null,
    ]);

    // 테스트 데이터 생성
    Sale::factory()->create([
        'store_id' => $this->store->id,
        'branch_id' => $this->branch->id,
        'sale_date' => '2024-01-15',
        'carrier' => 'SK',
        'activation_type' => '신규',
        'settlement_amount' => 100000,
        'margin_after_tax' => 15000,
    ]);

    Sale::factory()->create([
        'store_id' => $this->store->id,
        'branch_id' => $this->branch->id,
        'sale_date' => '2024-01-16',
        'carrier' => 'KT',
        'activation_type' => 'MNP',
        'settlement_amount' => 200000,
        'margin_after_tax' => 25000,
    ]);
});

it('매장 사용자는 자신의 매장 통계만 조회할 수 있다', function () {
    $response = $this->actingAs($this->storeUser)
        ->getJson('/api/sales/statistics?start_date=2024-01-01&end_date=2024-01-31');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'period' => ['start', 'end'],
            'summary' => [
                'total_count',
                'total_settlement',
                'total_margin',
                'avg_settlement',
                'active_stores',
            ],
            'by_carrier',
            'by_activation_type',
            'user_context' => ['role', 'accessible_stores'],
        ]);

    $data = $response->json();

    expect($data['summary']['total_count'])->toBe(2);
    expect($data['summary']['total_settlement'])->toBe(300000.0);
    expect($data['summary']['total_margin'])->toBe(40000.0);
    expect($data['user_context']['role'])->toBe('store');
});

it('통신사별 통계가 정확히 집계된다', function () {
    $response = $this->actingAs($this->storeUser)
        ->getJson('/api/sales/statistics?start_date=2024-01-01&end_date=2024-01-31');

    $data = $response->json();
    $byCarrier = collect($data['by_carrier'])->keyBy('carrier');

    expect($byCarrier['SK']['count'])->toBe(1);
    expect($byCarrier['SK']['total'])->toBe(100000.0);
    expect($byCarrier['KT']['count'])->toBe(1);
    expect($byCarrier['KT']['total'])->toBe(200000.0);
});

it('개통 유형별 통계가 정확히 집계된다', function () {
    $response = $this->actingAs($this->storeUser)
        ->getJson('/api/sales/statistics?start_date=2024-01-01&end_date=2024-01-31');

    $data = $response->json();
    $byType = collect($data['by_activation_type'])->keyBy('activation_type');

    expect($byType['신규']['count'])->toBe(1);
    expect($byType['신규']['total'])->toBe(100000.0);
    expect($byType['MNP']['count'])->toBe(1);
    expect($byType['MNP']['total'])->toBe(200000.0);
});

it('데이터가 없는 기간의 통계 조회시 빈 결과를 반환한다', function () {
    $response = $this->actingAs($this->storeUser)
        ->getJson('/api/sales/statistics?start_date=2024-02-01&end_date=2024-02-28');

    $response->assertStatus(200);

    $data = $response->json();

    expect($data['summary']['total_count'])->toBe(0);
    expect($data['summary']['total_settlement'])->toBe(0);
    expect($data['by_carrier'])->toBeEmpty();
    expect($data['by_activation_type'])->toBeEmpty();
    expect($data)->toHaveKey('message', '선택한 기간에 데이터가 없습니다.');
});

it('잘못된 날짜 형식으로 요청시 검증 에러가 발생한다', function () {
    $response = $this->actingAs($this->storeUser)
        ->getJson('/api/sales/statistics?start_date=invalid-date&end_date=2024-01-31');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_date']);
});

it('종료일이 시작일보다 빠른 경우 검증 에러가 발생한다', function () {
    $response = $this->actingAs($this->storeUser)
        ->getJson('/api/sales/statistics?start_date=2024-01-31&end_date=2024-01-01');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);
});

it('날짜 파라미터가 없으면 오늘 날짜를 기본값으로 사용한다', function () {
    $today = now()->format('Y-m-d');

    $response = $this->actingAs($this->storeUser)
        ->getJson('/api/sales/statistics');

    $response->assertStatus(200);

    $data = $response->json();

    expect($data['period']['start'])->toBe($today);
    expect($data['period']['end'])->toBe($today);
});

it('지사 사용자는 소속 지사의 모든 매장 통계를 조회할 수 있다', function () {
    // 같은 지사의 다른 매장 추가
    $anotherStore = Store::factory()->create([
        'name' => '강남2점',
        'branch_id' => $this->branch->id,
    ]);

    Sale::factory()->create([
        'store_id' => $anotherStore->id,
        'branch_id' => $this->branch->id,
        'sale_date' => '2024-01-17',
        'carrier' => 'LG',
        'settlement_amount' => 150000,
    ]);

    $response = $this->actingAs($this->branchUser)
        ->getJson('/api/sales/statistics?start_date=2024-01-01&end_date=2024-01-31');

    $data = $response->json();

    // 3개 판매 (기존 2개 + 새로 추가한 1개)
    expect($data['summary']['total_count'])->toBe(3);
    expect($data['summary']['active_stores'])->toBe(2); // 2개 매장
});
