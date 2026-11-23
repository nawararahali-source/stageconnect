import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  testMatch: '**/*.e2e.ts',
  timeout: 60000,
  use: {
    baseURL: 'http://localhost:4200',
    headless: false,
    screenshot: 'on',
    video: 'retain-on-failure',
  },
});
