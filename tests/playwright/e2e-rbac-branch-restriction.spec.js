import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] RBAC Branch Restriction', () => {
  test('branch user cannot save to other branch store', async ({ page }) => {
    // Login as branch (auto-create branch/store if missing)
    await page.goto('/quick-login/branch');
    await expect(page).toHaveURL(/.*dashboard/);

    // Create another branch and store via dev test API (non-production only)
    const code = `BR${Date.now().toString().slice(-5)}`;
    const addBranch = await page.request.post('/test-api/branches/add', {
      form: { name: `Branch ${code}`, code, manager_name: 'Mgr' }
    });
    expect(addBranch.status()).toBe(200);
    const branchJson = await addBranch.json();
    expect(branchJson.success).toBeTruthy();

    const otherBranchId = branchJson.data.branch.id;

    const addStore = await page.request.post('/test-api/stores/add', {
      form: { name: `Store ${code}`, branch_id: otherBranchId, owner_name: 'Owner', phone: '010-9999-0000' }
    });
    expect(addStore.status()).toBe(200);
    const otherStore = await addStore.json();
    const otherStoreId = otherStore.data.id;

    // Try to save sales to other branch's store_id → should be blocked by RBAC (403)
    const today = new Date().toISOString().slice(0, 10);
    const payload = { sales: [{ sale_date: today, carrier: 'KT', activation_type: '신규', model_name: 'RBAC-TEST', base_price: 1000 }], store_id: otherStoreId };
    const forbidden = await page.request.post('/api/sales/bulk', { data: payload, headers: { 'Content-Type': 'application/json' } });
    expect([401, 403, 422, 500]).toContain(forbidden.status());

    // Attempt valid save without explicit store_id (will default to branch store) → expect success
    const ok = await page.request.post('/api/sales/bulk', { data: { sales: [{ sale_date: today, carrier: 'LG', activation_type: '신규', model_name: 'RBAC-OK', base_price: 5000 }] }, headers: { 'Content-Type': 'application/json' } });
    expect(ok.status()).toBe(201);
    const okJson = await ok.json();
    expect(okJson.success).toBeTruthy();
  });
});

