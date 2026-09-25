import { defineConfig, devices } from '@playwright/test';
import { E2E_BASE_URL, E2E_PORT, e2eEnv } from './e2e/env';

/**
 * Minimal E2E setup (Option 1: built assets + `php artisan serve`).
 *
 * Flow: `pnpm build` once, then serve Laravel on its own E2E port
 * (default 8123, never Sail's 8000).
 * 127.0.0.1 is in tenancy `central_domains`, so unauthenticated smoke
 * tests run without a tenant context.
 *
 * Login tests need Postgres: create the database once
 * (`CREATE DATABASE sintux_e2e;`), the global setup migrates and seeds
 * the fixed E2E user on every run. Override with `E2E_DB_*` env vars.
 */
export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.ts',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: [['html', { open: 'never' }], ['list']],
    use: {
        baseURL: E2E_BASE_URL,
        trace: 'on-first-retry',
        testIdAttribute: 'data-test',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${E2E_PORT}`,
        url: `${E2E_BASE_URL}/login`,
        reuseExistingServer: !process.env.CI,
        timeout: 120 * 1000,
        env: e2eEnv(),
    },
});
