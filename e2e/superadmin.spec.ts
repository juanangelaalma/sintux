import { expect, test } from '@playwright/test';
import { loginAs } from './auth-helpers';
import { E2E_COMPANY, E2E_SUPERADMIN, E2E_USER } from './env';

test('superadmin lands on the admin dashboard after login', async ({
    page,
}) => {
    await loginAs(page, E2E_SUPERADMIN.email, E2E_SUPERADMIN.password);

    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await expect(
        page.getByRole('heading', { name: 'Sintux Admin Panel' }),
    ).toBeVisible();
});

test('regular users are redirected away from admin pages', async ({ page }) => {
    await loginAs(page, E2E_USER.email, E2E_USER.password);
    await expect(page).toHaveURL(/\/dashboard$/);

    for (const path of [
        '/admin/dashboard',
        '/admin/companies',
        '/admin/roles',
    ]) {
        await page.goto(path);

        expect(new URL(page.url()).pathname).toBe('/dashboard');
    }
});

test('superadmin can create, update and delete a company', async ({ page }) => {
    // Tenant creation runs real schema migrations, seeding and cleanup.
    test.setTimeout(180 * 1000);

    await loginAs(page, E2E_SUPERADMIN.email, E2E_SUPERADMIN.password);
    await page.goto('/admin/companies');

    const row = page.getByRole('row', { name: E2E_COMPANY.id });

    await page.getByRole('button', { name: 'Create Company' }).click();
    await page.getByPlaceholder('e.g. acme-corp').fill(E2E_COMPANY.id);
    await expect(page.locator('input[readonly]')).toHaveValue(
        E2E_COMPANY.schema,
    );
    await page.getByPlaceholder('e.g. Acme Corporation').fill(E2E_COMPANY.name);
    await page.getByPlaceholder('e.g. John Doe').fill(E2E_COMPANY.adminName);
    await page
        .getByPlaceholder('e.g. admin@acme.com')
        .fill(E2E_COMPANY.adminEmail);
    await page
        .getByPlaceholder('Minimum 8 characters')
        .fill(E2E_COMPANY.adminPassword);
    await page.getByRole('button', { name: 'Create', exact: true }).click();

    await expect(row).toBeVisible({ timeout: 60 * 1000 });

    await row.getByRole('button', { name: 'Edit' }).click();
    // Scope to the edit modal form: the admin header also renders a
    // search form, but only the modal form mentions "Company Name".
    await page
        .locator('form', { hasText: 'Company Name' })
        .getByRole('textbox')
        .fill(E2E_COMPANY.updatedName);
    await page.getByRole('button', { name: 'Save', exact: true }).click();

    const updatedRow = page.getByRole('row', {
        name: E2E_COMPANY.updatedName,
    });

    await expect(updatedRow).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await updatedRow.getByRole('button', { name: 'Delete' }).click();
    await expect(updatedRow).toHaveCount(0);
});

test('superadmin can update role permissions', async ({ page }) => {
    await loginAs(page, E2E_SUPERADMIN.email, E2E_SUPERADMIN.password);
    await page.goto('/admin/roles');

    const memberRow = page.getByRole('row', { name: /Member/ });

    await expect(memberRow.getByText('1 permissions')).toBeVisible();
    await memberRow.getByRole('button', { name: 'Edit' }).click();
    await page
        .getByRole('checkbox', { name: 'contact.view', exact: true })
        .check();
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(memberRow.getByText('2 permissions')).toBeVisible();

    await page.reload();
    await expect(memberRow.getByText('2 permissions')).toBeVisible();

    await memberRow.getByRole('button', { name: 'Edit' }).click();
    await page
        .getByRole('checkbox', { name: 'contact.view', exact: true })
        .uncheck();
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(memberRow.getByText('1 permissions')).toBeVisible();
});
