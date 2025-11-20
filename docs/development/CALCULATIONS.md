# Sales Calculation Formulas

## Overview

The system implements Excel-like calculation formulas for sales margin calculations. The primary calculation engine is `SalesCalculator` (`app/Helpers/SalesCalculator.php`).

## Field Mapping

### Input Fields (Supports Multiple Naming Conventions)

```php
// English / Korean / Excel Column
price_setting / base_price     → K (액면가/셋팅가)
verbal1                         → L (구두1)
verbal2                         → M (구두2)
grade_amount                    → N (그레이드)
addon_amount / additional_amount → O (부가추가)
paper_cash / cash_activation    → P (서류상현금개통)
usim_fee                        → Q (유심비)
new_mnp_disc / new_mnp_discount → R (신규/MNP할인)
deduction                       → S (차감)
cash_in / cash_received         → W (현금받음)
payback                         → X (페이백)
```

### Calculated Fields (ACTUAL IMPLEMENTATION)

```php
// Total Rebate Calculation
total_rebate = K + L + M + N + O  → T (리베총계)

// Settlement Calculation
settlement = T - P + Q + R - S + W - X  → U (정산금)

// Tax Calculation [Currently DISABLED]
tax = U × 0.10  → V (세금) [Currently returns 0]

// Margin Before Tax [Currently equals Settlement]
margin_before = U - V  → Y (세전마진) [Currently Y = U since V = 0]

// Margin After Tax [Currently equals Settlement]
margin_after = U  → Z (세후마진) [Currently Z = U]
```

## Important Calculation Notes

### 🚨 CRITICAL: Deduction is SUBTRACTED

**Deduction (S) is SUBTRACTED from settlement, not added**:
```php
settlement = T - P + Q + R - S + W - X
//                      ^^^ SUBTRACTED
```

### ⚠️ Tax Calculation Currently DISABLED

**Tax calculation always returns 0 in production**:
- `tax (V) = 0` (not `U × 0.10`)
- **Settlement = Margin** (both before/after tax)
- When tax is re-enabled, formulas will be:
  - `margin_before (Y) = U - V`
  - `margin_after (Z) = Y`

## Detailed Formula Breakdown

### Step 1: Total Rebate (T)
```php
Total Rebate = Price Setting + Verbal1 + Verbal2 + Grade + Addon
             = K + L + M + N + O
```

**Example**:
```
Price Setting (K) = 100,000
Verbal1 (L)       = 20,000
Verbal2 (M)       = 15,000
Grade (N)         = 10,000
Addon (O)         = 5,000
-------------------------
Total Rebate (T)  = 150,000
```

### Step 2: Settlement (U)
```php
Settlement = Total Rebate - Cash Activation + USIM Fee + Discount - Deduction + Cash In - Payback
           = T - P + Q + R - S + W - X
```

**Example**:
```
Total Rebate (T)      = 150,000
Cash Activation (P)   = -10,000  (subtract)
USIM Fee (Q)          = +3,000   (add)
Discount (R)          = +5,000   (add)
Deduction (S)         = -2,000   (SUBTRACT, not add!)
Cash In (W)           = +10,000  (add)
Payback (X)           = -5,000   (subtract)
------------------------------------
Settlement (U)        = 151,000
```

### Step 3: Tax (V) [Currently Disabled]
```php
Tax = Settlement × 0.10
    = U × 0.10

// CURRENT IMPLEMENTATION: Always returns 0
Tax = 0
```

**Example** (when enabled):
```
Settlement (U) = 151,000
Tax (V)        = 151,000 × 0.10 = 15,100  [NOT IMPLEMENTED]

// Current implementation:
Tax (V) = 0
```

### Step 4: Margin Before Tax (Y)
```php
Margin Before Tax = Settlement - Tax
                  = U - V

// CURRENT IMPLEMENTATION (V = 0):
Margin Before Tax = U - 0 = U
```

**Example**:
```
Settlement (U)         = 151,000
Tax (V)                = 0  [Currently disabled]
----------------------------
Margin Before Tax (Y)  = 151,000
```

### Step 5: Margin After Tax (Z)
```php
Margin After Tax = Margin Before Tax  [When tax is disabled]
                 = U

// When tax is enabled in the future:
Margin After Tax = Margin Before Tax = Y
```

**Example**:
```
Margin Before Tax (Y)  = 151,000
----------------------------
Margin After Tax (Z)   = 151,000  [Currently equals Y]
```

## SalesCalculator Usage

### Basic Calculation
```php
use App\Helpers\SalesCalculator;

$data = [
    'price_setting' => 100000,
    'verbal1' => 20000,
    'verbal2' => 15000,
    'grade_amount' => 10000,
    'addon_amount' => 5000,
    'cash_activation' => 10000,
    'usim_fee' => 3000,
    'new_mnp_discount' => 5000,
    'deduction' => 2000,
    'cash_received' => 10000,
    'payback' => 5000,
];

$result = SalesCalculator::computeRow($data);

// Returns:
// [
//     'total_rebate' => 150000,
//     'settlement_amount' => 151000,
//     'tax_amount' => 0,
//     'margin_before_tax' => 151000,
//     'margin_after_tax' => 151000,
// ]
```

### With Dealer Profile

```php
$dealerProfile = DealerProfile::find($dealerId);

$result = SalesCalculator::computeRow($data, $dealerProfile);
// Uses dealer-specific calculation rules if available
```

## FieldMapper Usage

### Excel Column to Field Name
```php
use App\Helpers\FieldMapper;

$fieldName = FieldMapper::getFieldFromExcelColumn('K');
// Returns: 'price_setting'

$columnLetter = FieldMapper::getExcelColumnFromField('price_setting');
// Returns: 'K'
```

### Korean to English Field Names
```php
$fieldName = FieldMapper::getFieldFromKorean('액면가');
// Returns: 'price_setting'

$koreanName = FieldMapper::getKoreanFromField('price_setting');
// Returns: '액면가'
```

## Testing Calculations

### Unit Tests
```bash
./vendor/bin/phpunit --filter="SalesCalculatorTest"
```

### Test Specific Calculation
```php
// tests/Unit/SalesCalculatorTest.php
public function test_deduction_is_subtracted_not_added()
{
    $data = [
        'total_rebate' => 100000,
        'deduction' => 5000,
        // ... other fields
    ];

    $result = SalesCalculator::computeRow($data);

    // Deduction should SUBTRACT from settlement
    $this->assertEquals(95000, $result['settlement_amount']);
}
```

## Common Calculation Issues

### Issue 1: Deduction Being Added Instead of Subtracted
**Problem**: Settlement is higher than expected
**Solution**: Check that deduction is using subtraction: `settlement = ... - S + ...`

### Issue 2: Tax Not Applying
**Status**: Expected behavior - tax calculation is currently disabled
**Workaround**: Settlement = Margin (both before/after tax)

### Issue 3: Field Name Mismatch
**Problem**: Calculation fails with unknown field
**Solution**: Use `FieldMapper` to handle multiple naming conventions

## Future Enhancements

### When Tax is Re-enabled
```php
// Current:
tax = 0
margin_before = settlement - 0 = settlement
margin_after = margin_before = settlement

// After tax re-enabled:
tax = settlement × 0.10
margin_before = settlement - tax
margin_after = margin_before  (same since no additional tax)
```

---

**Back to**: [Development Docs Index](README.md) | [CLAUDE.md](../../CLAUDE.md)
