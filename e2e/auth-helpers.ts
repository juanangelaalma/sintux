import type { Page } from '@playwright/test';

/** Sign in through the UI login form. */
export async function loginAs(
    page: Page,
    email: string,
    password: string,
): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);

    // Wait for the login POST to complete: navigating away earlier would
    // cancel the request and drop the session cookie from its response.
    const loginResponse = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            new URL(response.url()).pathname === '/login',
    );

    await page.getByTestId('login-button').click();
    await loginResponse;
}
