import { test, expect } from '@playwright/test';

test.describe('[Deploy E2E] Profile Permissions', () => {
  test('HQ profile can access many stores', async ({ page }) => {
    await page.goto('/quick-login/headquarters');
    const res = await page.request.get('/api/profile');
    expect(res.status()).toBe(200);
    const profile = await res.json();
    expect(profile.role).toBe('headquarters');
    expect(Array.isArray(profile.permissions.accessible_store_ids)).toBeTruthy();
  });

  test('Store profile accessible_store_ids contains only its store', async ({ page }) => {
    await page.goto('/quick-login/store');
    const res = await page.request.get('/api/profile');
    expect(res.status()).toBe(200);
    const profile = await res.json();
    expect(profile.role).toBe('store');
    expect(profile.permissions.accessible_store_ids.length).toBe(1);
  });
});

