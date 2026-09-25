import { execFileSync } from 'node:child_process';
import { E2E_COMPANY, E2E_SUPERADMIN, E2E_USER, e2eEnv } from './env';

function phpQuote(value: string): string {
    return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

/**
 * Idempotent E2E setup: migrate the E2E database, sweep leftovers from
 * interrupted runs, seed the role catalog and ensure the fixed login
 * users exist. Safe to run before every `playwright test`.
 */
async function globalSetup(): Promise<void> {
    const env = { ...process.env, ...e2eEnv() };

    const artisan = (args: string[]): void => {
        execFileSync('php', ['artisan', ...args], { env, stdio: 'inherit' });
    };

    const tinker = (code: string): void => {
        artisan(['tinker', '--execute', code]);
    };

    artisan(['migrate', '--force']);

    // Remove a half-created suite company (tenant row, admin user, schema)
    // so reruns never hit unique violations.
    tinker(
        [
            `$admin = \\App\\Models\\User::where('email', ${phpQuote(E2E_COMPANY.adminEmail)})->first();`,
            `if ($admin) { \\Modules\\Company\\Models\\CompanyUser::where('user_id', $admin->id)->delete(); $admin->delete(); }`,
            `$tenant = \\App\\Models\\Tenant::find(${phpQuote(E2E_COMPANY.id)});`,
            `if ($tenant) { $tenant->delete(); }`,
            `\\Illuminate\\Support\\Facades\\DB::statement('DROP SCHEMA IF EXISTS "${E2E_COMPANY.schema}" CASCADE');`,
        ].join(' '),
    );

    artisan([
        'db:seed',
        '--class=Modules\\Company\\Database\\Seeders\\RolePermissionSeeder',
        '--force',
    ]);

    // Stale sessions would keep previous runs logged in.
    tinker("\\Illuminate\\Support\\Facades\\DB::table('sessions')->delete();");

    const seedUser = (
        email: string,
        name: string,
        role: string,
        password: string,
    ): void => {
        tinker(
            `\\App\\Models\\User::updateOrCreate(['email' => ${phpQuote(email)}], ['name' => ${phpQuote(name)}, 'role' => ${phpQuote(role)}, 'password' => \\Illuminate\\Support\\Facades\\Hash::make(${phpQuote(password)})]);`,
        );
    };

    seedUser(E2E_USER.email, 'E2E User', 'user', E2E_USER.password);
    seedUser(
        E2E_SUPERADMIN.email,
        'E2E Superadmin',
        'superadmin',
        E2E_SUPERADMIN.password,
    );
}

export default globalSetup;
