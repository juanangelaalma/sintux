import { expect, test } from '@playwright/test';
import { loginAs } from './auth-helpers';
import { E2E_USER } from './env';

test('user can log in with valid credentials', async ({ page }) => {
    await loginAs(page, E2E_USER.email, E2E_USER.password);

    await expect(page).toHaveURL(/\/dashboard/);
    await expect(
        page.getByRole('heading', { name: 'Dashboard' }),
    ).toBeVisible();
});

test('user cannot log in with an invalid password', async ({ page }) => {
    await loginAs(page, E2E_USER.email, 'wrong-password');

    await expect(page).toHaveURL(/\/login/);
    await expect(
        page.getByText('These credentials do not match our records.'),
    ).toBeVisible();
});

test('guests are redirected to login from the dashboard', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page).toHaveURL(/\/login/);
});

test('public registration is disabled', async ({ page }) => {
    const response = await page.goto('/register');

    expect(response?.status()).toBe(404);
});
