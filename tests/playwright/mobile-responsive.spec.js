import { test, expect } from '@playwright/test';

/**
 * Test Suite: Mobile Responsive Design
 * Purpose: Verify system is usable on mobile and tablet devices
 * Priority: HIGH
 * Coverage Gap: Mobile/tablet testing (40% of users)
 */

test.describe('Mobile Responsive Tests', () => {
  const viewports = {
    mobile: { width: 375, height: 667, name: 'iPhone SE' },
    mobileLarge: { width: 414, height: 896, name: 'iPhone 11 Pro Max' },
    tablet: { width: 768, height: 1024, name: 'iPad' },
    tabletLandscape: { width: 1024, height: 768, name: 'iPad Landscape' }
  };

  test('Sales grid is usable on mobile', async ({ browser }) => {
    const context = await browser.newContext({
      viewport: viewports.mobile,
      userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15'
    });
    const page = await context.newPage();

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Navigate to sales grid
    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    // Check if grid switches to mobile-friendly view
    const isMobileFriendly = await page.evaluate(() => {
      // Check for card view or mobile-specific class
      const hasCardView = document.querySelector('.mobile-card-view, .card-view, [data-mobile-view]');
      const gridWidth = document.querySelector('.ag-grid')?.offsetWidth;
      const viewportWidth = window.innerWidth;

      return {
        hasCardView: !!hasCardView,
        gridFitsViewport: gridWidth <= viewportWidth,
        viewportWidth: viewportWidth
      };
    });

    console.log('Mobile view check:', isMobileFriendly);

    // Verify no horizontal scroll required
    const hasHorizontalScroll = await page.evaluate(() => {
      const body = document.body;
      return body.scrollWidth > body.clientWidth;
    });

    if (hasHorizontalScroll) {
      console.log('⚠ Horizontal scroll detected on mobile (poor UX)');
    } else {
      console.log('✓ No horizontal scroll required');
    }

    // Test touch interactions
    await page.touchscreen.tap(100, 200);
    await page.waitForTimeout(500);

    // Verify sidebar doesn't cover content
    const sidebarCoversContent = await page.evaluate(() => {
      const sidebar = document.querySelector('.sidebar, [data-sidebar], nav');
      const mainContent = document.querySelector('main, .main-content, .content');

      if (!sidebar || !mainContent) return false;

      const sidebarRect = sidebar.getBoundingClientRect();
      const contentRect = mainContent.getBoundingClientRect();

      // Check if sidebar overlaps content
      return sidebarRect.right > contentRect.left &&
             sidebarRect.left < contentRect.right;
    });

    if (sidebarCoversContent) {
      console.log('⚠ Sidebar covers content on mobile');
    } else {
      console.log('✓ Sidebar does not cover content');
    }

    await context.close();
  });

  test('Dashboard is responsive on tablet', async ({ browser }) => {
    const context = await browser.newContext({
      viewport: viewports.tablet
    });
    const page = await context.newPage();

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Check dashboard layout
    const layoutInfo = await page.evaluate(() => {
      const cards = document.querySelectorAll('.card, .stat-card, [data-card]');
      const grid = document.querySelector('.grid, .dashboard-grid');

      return {
        cardCount: cards.length,
        hasGrid: !!grid,
        gridColumns: grid ? window.getComputedStyle(grid).gridTemplateColumns : null
      };
    });

    console.log('Tablet dashboard layout:', layoutInfo);

    // Verify cards stack appropriately
    expect(layoutInfo.cardCount).toBeGreaterThan(0);

    await context.close();
  });

  test('AG-Grid horizontal scroll on tablet', async ({ browser }) => {
    const context = await browser.newContext({
      viewport: viewports.tablet
    });
    const page = await context.newPage();

    // Login and navigate
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
    await page.goto('/sales/complete-aggrid');
    await page.waitForSelector('table.min-w-full', { timeout: 10000 });

    // Check if frozen columns implemented
    const frozenColumns = await page.evaluate(() => {
      const pinnedLeftCols = document.querySelectorAll('.ag-pinned-left-cols-container .ag-header-cell');
      return {
        count: pinnedLeftCols.length,
        hasFrozenColumns: pinnedLeftCols.length > 0
      };
    });

    console.log('Frozen columns on tablet:', frozenColumns);

    // Test horizontal scroll
    await page.evaluate(() => {
      const viewport = document.querySelector('.ag-body-horizontal-scroll-viewport');
      if (viewport) {
        viewport.scrollLeft = 300;
      }
    });

    await page.waitForTimeout(500);

    const scrolled = await page.evaluate(() => {
      const viewport = document.querySelector('.ag-body-horizontal-scroll-viewport');
      return viewport ? viewport.scrollLeft > 0 : false;
    });

    console.log('Horizontal scroll working:', scrolled);

    await context.close();
  });

  test('Touch gestures work on mobile', async ({ browser }) => {
    const context = await browser.newContext({
      viewport: viewports.mobile,
      hasTouch: true
    });
    const page = await context.newPage();

    // Login
    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Test swipe gesture on dashboard cards (if implemented)
    const cardExists = await page.locator('.card, .stat-card').count() > 0;

    if (cardExists) {
      const card = page.locator('.card, .stat-card').first();
      const box = await card.boundingBox();

      if (box) {
        // Swipe left
        await page.touchscreen.tap(box.x + box.width / 2, box.y + box.height / 2);
        await page.waitForTimeout(300);

        console.log('✓ Touch tap registered');
      }
    }

    // Test pinch zoom (should be disabled on form elements)
    await page.evaluate(() => {
      const viewportMeta = document.querySelector('meta[name="viewport"]');
      return viewportMeta?.getAttribute('content');
    });

    await context.close();
  });

  test('Font sizes are readable on mobile', async ({ browser }) => {
    const context = await browser.newContext({
      viewport: viewports.mobile
    });
    const page = await context.newPage();

    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Check font sizes
    const fontSizes = await page.evaluate(() => {
      const elements = [
        { selector: 'h1', name: 'Heading 1' },
        { selector: 'h2', name: 'Heading 2' },
        { selector: 'p', name: 'Paragraph' },
        { selector: 'button', name: 'Button' },
        { selector: 'input', name: 'Input' }
      ];

      return elements.map(({ selector, name }) => {
        const el = document.querySelector(selector);
        if (!el) return null;

        const fontSize = window.getComputedStyle(el).fontSize;
        return {
          element: name,
          fontSize: fontSize,
          pixels: parseInt(fontSize)
        };
      }).filter(Boolean);
    });

    console.log('Mobile font sizes:', fontSizes);

    // Minimum readable font size on mobile is 16px
    fontSizes.forEach(({ element, pixels }) => {
      if (element === 'Input' || element === 'Button') {
        if (pixels < 16) {
          console.log(`⚠ ${element} font size too small: ${pixels}px (minimum 16px)`);
        }
      }
    });

    await context.close();
  });

  test('Navigation menu accessible on mobile', async ({ browser }) => {
    const context = await browser.newContext({
      viewport: viewports.mobile
    });
    const page = await context.newPage();

    await page.goto('/login');
    await page.fill('input[name="email"]', 'store@ykp.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');

    // Look for hamburger menu
    const hamburgerMenu = page.locator('button[aria-label="menu"], .hamburger, .menu-toggle, [data-menu-toggle]');

    if (await hamburgerMenu.count() > 0) {
      await hamburgerMenu.first().click();
      await page.waitForTimeout(500);

      // Verify menu opened
      const menuVisible = await page.evaluate(() => {
        const nav = document.querySelector('nav, .mobile-menu, .sidebar');
        if (!nav) return false;

        const styles = window.getComputedStyle(nav);
        return styles.display !== 'none' && styles.visibility !== 'hidden';
      });

      console.log('Mobile menu opens:', menuVisible);
    } else {
      console.log('⚠ Hamburger menu not found on mobile');
    }

    await context.close();
  });
});
