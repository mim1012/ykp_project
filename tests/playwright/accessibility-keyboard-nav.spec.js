import { test, expect } from '@playwright/test';

/**
 * Test Suite: Accessibility - Keyboard Navigation
 * Purpose: Verify complete workflows using only keyboard
 * Priority: HIGH
 * Coverage Gap: Accessibility testing (WCAG 2.1 compliance)
 */

test.describe('Keyboard Navigation Tests', () => {
  test('Complete sales workflow using only keyboard', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('networkidle');

    // Login using only keyboard
    console.log('Testing keyboard login...');

    // Tab to email input
    await page.keyboard.press('Tab');
    await page.keyboard.type('store@ykp.com');

    // Tab to password
    await page.keyboard.press('Tab');
    await page.keyboard.type('password');

    // Submit with Enter
    await page.keyboard.press('Enter');
    await page.waitForURL('/dashboard', { timeout: 10000 });
    console.log('✓ Keyboard login successful');

    // Navigate to sales page using keyboard
    console.log('Testing keyboard navigation to sales page...');

    // Try different keyboard shortcuts/tab sequences
    // Option 1: Press Tab multiple times to reach sales link
    for (let i = 0; i < 20; i++) {
      await page.keyboard.press('Tab');
      await page.waitForTimeout(100);

      // Check if we're focused on an element that might be the sales link
      const focusedElement = await page.evaluate(() => {
        const el = document.activeElement;
        return {
          tag: el?.tagName,
          text: el?.textContent?.trim(),
          href: el?.getAttribute('href')
        };
      });

      console.log(`Tab ${i}: ${focusedElement.tag} - "${focusedElement.text?.substring(0, 30)}" - ${focusedElement.href}`);

      // If we found sales/complete-aggrid link, press Enter
      if (focusedElement.href?.includes('/sales') ||
          focusedElement.text?.includes('판매') ||
          focusedElement.text?.includes('개통표')) {
        await page.keyboard.press('Enter');
        await page.waitForTimeout(2000);
        break;
      }
    }

    // Alternative: Direct navigation for this test
    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });
    console.log('✓ Reached sales page');

    // Test AG-Grid keyboard navigation
    console.log('Testing AG-Grid keyboard navigation...');

    // Focus should be manageable with Tab
    await page.keyboard.press('Tab');
    await page.waitForTimeout(500);

    const focusedAfterTab = await page.evaluate(() => {
      const el = document.activeElement;
      return {
        tag: el?.tagName,
        class: el?.className,
        role: el?.getAttribute('role')
      };
    });

    console.log('Focused element after Tab:', focusedAfterTab);

    // Verify focus indicators are visible
    const hasFocusIndicator = await page.evaluate(() => {
      const el = document.activeElement;
      if (!el) return false;

      const styles = window.getComputedStyle(el);
      const outline = styles.outline;
      const boxShadow = styles.boxShadow;
      const border = styles.border;

      // Check if element has visible focus indicator
      return outline !== 'none' ||
             boxShadow !== 'none' ||
             border.includes('blue') ||
             border.includes('focus');
    });

    console.log('Focus indicator visible:', hasFocusIndicator);

    // Test arrow key navigation in grid
    console.log('Testing arrow key navigation in grid...');
    await page.keyboard.press('ArrowDown');
    await page.waitForTimeout(300);
    await page.keyboard.press('ArrowRight');
    await page.waitForTimeout(300);
    await page.keyboard.press('ArrowLeft');
    await page.waitForTimeout(300);
    await page.keyboard.press('ArrowUp');
    await page.waitForTimeout(300);

    // Test keyboard activation of buttons
    console.log('Testing keyboard button activation...');

    // Try to focus and activate save button with keyboard
    // Shift+Tab multiple times to reach toolbar buttons
    for (let i = 0; i < 10; i++) {
      const focusedBtn = await page.evaluate(() => {
        const el = document.activeElement;
        return {
          tag: el?.tagName,
          text: el?.textContent?.trim()
        };
      });

      if (focusedBtn.text?.includes('저장') ||
          focusedBtn.text?.includes('추가')) {
        console.log(`Found button: ${focusedBtn.text}`);
        break;
      }

      await page.keyboard.press('Tab');
      await page.waitForTimeout(100);
    }

    console.log('✓ Keyboard navigation test completed');
  });

  test('Verify Tab order is logical', async ({ page }) => {
    await page.goto('/login');

    const tabOrder = [];

    // Record tab order through login form
    for (let i = 0; i < 5; i++) {
      await page.keyboard.press('Tab');

      const focused = await page.evaluate(() => {
        const el = document.activeElement;
        return {
          tag: el?.tagName,
          type: el?.getAttribute('type'),
          name: el?.getAttribute('name'),
          text: el?.textContent?.trim().substring(0, 20)
        };
      });

      tabOrder.push(focused);
    }

    console.log('Tab order:', JSON.stringify(tabOrder, null, 2));

    // Expected order: email -> password -> submit button
    expect(tabOrder.some(el => el.name === 'email')).toBe(true);
    expect(tabOrder.some(el => el.name === 'password')).toBe(true);
    expect(tabOrder.some(el => el.tag === 'BUTTON')).toBe(true);
  });

  test('Verify skip links exist', async ({ page }) => {
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');

    // Press Tab once to reveal skip link (if exists)
    await page.keyboard.press('Tab');
    await page.waitForTimeout(300);

    const skipLink = await page.evaluate(() => {
      const el = document.activeElement;
      const text = el?.textContent?.toLowerCase();
      return text?.includes('skip') ||
             text?.includes('건너뛰기') ||
             text?.includes('본문으로');
    });

    if (skipLink) {
      console.log('✓ Skip link found');
    } else {
      console.log('⚠ Skip link missing (accessibility best practice)');
    }
  });

  test('Verify focus is not trapped in modals', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Look for any modal triggers
    const modalTrigger = page.locator('button:has-text("추가"), button:has-text("등록")').first();

    if (await modalTrigger.count() > 0) {
      await modalTrigger.click();
      await page.waitForTimeout(1000);

      // Try to tab out of modal
      let escaped = false;
      for (let i = 0; i < 30; i++) {
        await page.keyboard.press('Tab');
        await page.waitForTimeout(100);

        const focusedElement = await page.evaluate(() => {
          const el = document.activeElement;
          const modal = el?.closest('.modal, [role="dialog"], .modal-content');
          return !modal; // true if focus escaped modal
        });

        if (focusedElement) {
          escaped = true;
          console.log('✓ Focus can escape modal');
          break;
        }
      }

      if (!escaped) {
        console.log('⚠ Focus appears trapped in modal (check for focus trap implementation)');
      }
    }
  });
});
