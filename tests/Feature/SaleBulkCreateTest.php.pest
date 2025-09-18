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

    $this->headquartersUser = User::factory()->create([
        'role' => 'headquarters',
        'branch_id' => null,
        'store_id' => null,
    ]);
});

it('매장 사용자가 정상적으로 판매 데이터를 저장할 수 있다', function () {
    $salesData = [
        'sales' => [
            [
                'sale_date' => '2024-01-15',
                'carrier' => 'SK',
                'activation_type' => '신규',
                'model_name' => 'iPhone 15',
                'base_price' => 1000000,
                'verbal1' => 50000,
                'salesperson' => '홍길동',
            ],
        ],
    ];

    $response = $this->actingAs($this->storeUser)
        ->postJson('/api/sales/bulk', $salesData);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'saved_count' => 1,
        ]);

    $this->assertDatabaseHas('sales', [
        'store_id' => $this->store->id,
        'branch_id' => $this->branch->id,
        'carrier' => 'SK',
        'model_name' => 'iPhone 15',
    ]);
});

it('잘못된 통신사로 요청시 검증 에러가 발생한다', function () {
    $salesData = [
        'sales' => [
            [
                'sale_date' => '2024-01-15',
                'carrier' => 'INVALID',  // 잘못된 통신사
                'activation_type' => '신규',
                'model_name' => 'iPhone 15',
            ],
        ],
    ];

    $response = $this->actingAs($this->storeUser)
        ->postJson('/api/sales/bulk', $salesData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['sales.0.carrier']);
});

it('지사 사용자는 소속 지사의 다른 매장 판매 데이터를 생성할 수 있다', function () {
    $anotherStore = Store::factory()->create([
        'name' => '강남2점',
        'branch_id' => $this->branch->id,
    ]);

    $salesData = [
        'sales' => [
            [
                'sale_date' => '2024-01-15',
                'carrier' => 'KT',
                'activation_type' => 'MNP',
                'model_name' => 'Galaxy S24',
            ],
        ],
        'store_id' => $anotherStore->id,
    ];

    $response = $this->actingAs($this->branchUser)
        ->postJson('/api/sales/bulk', $salesData);

    $response->assertStatus(201);

    $this->assertDatabaseHas('sales', [
        'store_id' => $anotherStore->id,
        'carrier' => 'KT',
    ]);
});

it('본사 사용자는 매장과 지사 정보를 모두 제공해야 한다', function () {
    $salesData = [
        'sales' => [
            [
                'sale_date' => '2024-01-15',
                'carrier' => 'LG',
                'activation_type' => '기변',
                'model_name' => 'iPhone 15',
            ],
        ],
        // store_id, branch_id 누락
    ];

    $response = $this->actingAs($this->headquartersUser)
        ->postJson('/api/sales/bulk', $salesData);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => '본사는 매장과 지사 정보를 모두 제공해야 합니다.',
        ]);
});

it('1000개 초과 데이터 요청시 검증 에러가 발생한다', function () {
    $salesData = [
        'sales' => array_fill(0, 1001, [  // 1001개 생성
            'sale_date' => '2024-01-15',
            'carrier' => 'SK',
            'activation_type' => '신규',
            'model_name' => 'iPhone 15',
        ]),
    ];

    $response = $this->actingAs($this->storeUser)
        ->postJson('/api/sales/bulk', $salesData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['sales']);
});

it('계산된 필드들이 정확히 저장된다', function () {
    $salesData = [
        'sales' => [
            [
                'sale_date' => '2024-01-15',
                'carrier' => 'SK',
                'activation_type' => '신규',
                'model_name' => 'iPhone 15',
                'base_price' => 100000,
                'verbal1' => 20000,
                'verbal2' => 10000,
                'grade_amount' => 5000,
                'additional_amount' => 3000,
            ],
        ],
    ];

    $response = $this->actingAs($this->storeUser)
        ->postJson('/api/sales/bulk', $salesData);

    $response->assertStatus(201);

    $sale = Sale::first();

    // 리베이트 총계: 100000 + 20000 + 10000 + 5000 + 3000 = 138000
    expect($sale->rebate_total)->toBe(138000.0);

    // 정산금: 138000 (리베이트 총계와 동일, 다른 필드 0)
    expect($sale->settlement_amount)->toBe(138000.0);

    // 세금: 138000 * 0.133 = 18354 (반올림)
    expect($sale->tax)->toBe(18354.0);
});

it('권한이 없는 사용자는 생성할 수 없다', function () {
    $salesData = [
        'sales' => [
            [
                'sale_date' => '2024-01-15',
                'carrier' => 'SK',
                'activation_type' => '신규',
                'model_name' => 'iPhone 15',
            ],
        ],
    ];

    $response = $this->postJson('/api/sales/bulk', $salesData);

    $response->assertStatus(401);
});
