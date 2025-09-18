import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Sales Query Filters & Access', () => {
  test('store user cannot read other store sales via filter', async ({ page }) => {
    // login as store
    await page.goto('/quick-login/store');

    // create another branch and store (dev route)
    const code = `BR${Date.now().toString().slice(-5)}`;
    const addBranch = await page.request.post('/test-api/branches/add', {
      form: { name: `Branch ${code}`, code, manager_name: 'Mgr' }
    });
    expect(addBranch.status()).toBe(200);
    const branchJson = await addBranch.json();
    const otherBranchId = branchJson.data.branch.id;

    const addStore = await page.request.post('/test-api/stores/add', {
      form: { name: `Store ${code}`, branch_id: otherBranchId, owner_name: 'Owner', phone: '010-9999-0000' }
    });
    const otherStore = await addStore.json();
    const otherStoreId = otherStore.data.id;

    // try filter by otherStoreId
    const res = await page.request.get(`/api/sales?store_id=${otherStoreId}`);
    expect(res.status()).toBe(200);
    const json = await res.json();
    const data = json?.data || json; // paginator or array
    if (Array.isArray(data)) {
      for (const s of data) {
        expect(s.store_id).not.toBe(otherStoreId);
      }
    }
  });
});

