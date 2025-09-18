import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  timeout: 90000, // 30초 → 90초로 증가
  fullyParallel: false, // 순차 실행 (로그인 테스트)
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1, // 재시도 1회 추가
  workers: 1, // 단일 워커 (세션 충돌 방지)
  reporter: 'html',
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    headless: !!process.env.CI, // CI에서는 headless로 실행
    navigationTimeout: 30000, // 페이지 로딩 타임아웃
    actionTimeout: 15000, // 액션 타임아웃
  },

  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
      },
    }
  ],

  webServer: {
    command: 'php artisan serve',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
  },
});
