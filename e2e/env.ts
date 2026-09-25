/**
 * E2E server port. Defaults to 8123 (not 8000) so the suite never
 * collides with Sail (`APP_PORT=8000`) or `php artisan serve` run by hand.
 * With `reuseExistingServer`, a foreign app on the E2E port would
 * otherwise be silently reused and tests would hit the wrong database.
 */
export const E2E_PORT = process.env.E2E_PORT ?? '8123';

export const E2E_BASE_URL =
    process.env.E2E_BASE_URL ?? `http://127.0.0.1:${E2E_PORT}`;
/**
 * Shared E2E environment.
 *
 * The E2E suite needs its own Postgres database (login posts hit the
 * central `users` table). Point it at a reachable server with:
 * `E2E_DB_HOST`, `E2E_DB_PORT`, `E2E_DB_DATABASE`, `E2E_DB_USERNAME`,
 * `E2E_DB_PASSWORD`. Defaults assume a local Postgres with the same
 * `laravel` / `secret` credentials as Sail/CI.
 */
export function e2eEnv(): Record<string, string> {
    return {
        APP_ENV: 'testing',
        APP_URL: E2E_BASE_URL,
        DB_CONNECTION: 'pgsql',
        DB_URL: '',
        DB_HOST: process.env.E2E_DB_HOST ?? '127.0.0.1',
        DB_PORT: process.env.E2E_DB_PORT ?? '5432',
        DB_DATABASE: process.env.E2E_DB_DATABASE ?? 'sintux_e2e',
        DB_USERNAME: process.env.E2E_DB_USERNAME ?? 'laravel',
        DB_PASSWORD: process.env.E2E_DB_PASSWORD ?? 'secret',
        // Database sessions: the array driver lives in PHP memory and does
        // not survive across HTTP requests under `php artisan serve`, so
        // logins would be lost on the redirect after POST /login.
        SESSION_DRIVER: 'database',
        CACHE_STORE: 'array',
        QUEUE_CONNECTION: 'sync',
        MAIL_MAILER: 'array',
        // The PHP built-in server is single-threaded by default, so one
        // slow request (e.g. tenant creation runs schema migrations)
        // would hang every parallel test. Workers share nothing except
        // the database, which is exactly what the suite needs.
        PHP_CLI_SERVER_WORKERS: '5',
    };
}

export const E2E_USER = {
    email: process.env.E2E_USER_EMAIL ?? 'e2e@example.com',
    password: process.env.E2E_USER_PASSWORD ?? 'secret123',
};

export const E2E_SUPERADMIN = {
    email: process.env.E2E_SUPERADMIN_EMAIL ?? 'e2e-superadmin@example.com',
    password: process.env.E2E_SUPERADMIN_PASSWORD ?? 'secret123',
};

export const E2E_COMPANY = {
    id: 'e2e-acme',
    name: 'E2E Acme Corp',
    updatedName: 'E2E Acme Corp Updated',
    schema: 'company_e2e_acme',
    adminName: 'E2E Acme Admin',
    adminEmail: 'admin@e2e-acme.test',
    adminPassword: 'secret123',
};
