import { expect, test } from '@playwright/test';

test('app loads and redirects to login', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveURL(/\/login/);
    await expect(
        page.getByRole('heading', { name: 'Log in to your account' }),
    ).toBeVisible();
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByTestId('login-button')).toBeVisible();
});
