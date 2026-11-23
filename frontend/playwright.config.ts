import { defineConfig, devices } from '@playwright/test'

/*
| Browser tests of whole pages: the real SPA against the real API.
| `npm run test:e2e` — see .claude/rules/schainserp-product.md, "Testing".
|
| The backend's .env points at the shared Aiven database, so these tests
| only read: they sign in and click through pages, and never save. Signing
| in still writes an ADMIN_LOGIN audit row and a token, as any sign-in does.
|
| Credentials come from frontend/.env.e2e (git-ignored; copy
| .env.e2e.example). Tests that need one are skipped when it's missing.
|
| The servers: an already-running dev setup (start-dev.ps1) is reused;
| otherwise Laravel (port 8000) and Vite (port 5173) are started for the run.
*/

try {
  process.loadEnvFile('.env.e2e')
} catch {
  // No local credentials: the tests that need them skip themselves.
}

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results',
  // One browser, in order: the tests share one database and one dev server.
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  timeout: 60_000,
  // Every API call goes to the remote Aiven database, and a cold sign-in
  // (token signing) can take several seconds — more than the 5 s default.
  expect: { timeout: 15_000 },
  use: {
    baseURL: 'http://localhost:5173',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } }],
  webServer: [
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8000',
      cwd: '../schainbackend',
      url: 'http://127.0.0.1:8000/up',
      reuseExistingServer: true,
      timeout: 90_000,
    },
    {
      command: 'npm run dev -- --port 5173 --strictPort',
      url: 'http://localhost:5173',
      reuseExistingServer: true,
      timeout: 90_000,
    },
  ],
})
