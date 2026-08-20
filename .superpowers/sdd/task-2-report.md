# Task 2 Report: Send Eligible Taxes to Product Forms

## Files

- `Modules/Product/app/Http/Controllers/ProductController.php`
  - Injected `Modules\Accounting\Application\TaxQuery`.
  - Added `purchaseTaxes` and `salesTaxes` Inertia props to `create()` and `edit()`.
- `Modules/Product/tests/Feature/ProductCrudTest.php`
  - Seeds one purchase-eligible and one sales-eligible tax in the tenant schema.
  - Asserts the create page exposes each expected tax ID.

## TDD

### RED

```bash
php artisan test Modules/Product/tests/Feature/ProductCrudTest.php --filter=test_company_member_can_manage_products
```

Result: blocked before the test executes. Laravel cannot resolve the configured PostgreSQL host `pgsql`:

```text
SQLSTATE[08006] [7] could not translate host name "pgsql" to address: Name or service not known
```

The intended red assertion was added before the controller wiring. The failure is an unavailable test database service, not the missing props.

### GREEN

```bash
php artisan test Modules/Product/tests/Feature/ProductCrudTest.php --filter=test_company_member_can_manage_products
```

Result: blocked by the same unavailable `pgsql` hostname before test setup completes.

```bash
composer lint:check
```

Result: passed (`pint`).

Additional checks passed:

```bash
php -l Modules/Product/app/Http/Controllers/ProductController.php
php -l Modules/Product/tests/Feature/ProductCrudTest.php
```

## Commit

`ab55a4f feat(product): provide eligible taxes`

## Self-Review

- Product consumes Accounting only through its public `Application\TaxQuery` API.
- Both required page actions expose `purchaseTaxes` and `salesTaxes`.
- The test uses tenant-local table setup instead of importing Accounting's internal model, preserving the module test boundary.
- The commit contains only the two Task 2 files.

## Concerns

- The required feature test cannot run until the configured PostgreSQL service is available at host `pgsql`.
- The existing test helper does not publish an Accounting-owned tax fixture, so tenant-local setup uses the `taxes` and `chart_of_accounts` schema required by the public TaxQuery behavior.

## Review Fixes

- Added `Modules/Accounting/tests/Support/EligibleTaxFixture.php`, an Accounting-owned fixture that creates active purchase-eligible and sales-eligible taxes through Accounting models in the current tenant context.
- Updated `Modules/Product/tests/Feature/ProductCrudTest.php` to use only that fixture. Product no longer writes Accounting tables directly.
- Replaced index-coupled assertions with scoped Inertia assertions that locate each expected tax ID.

## Review-Fix Verification

```bash
php artisan test Modules/Product/tests/Feature/ProductCrudTest.php --filter=test_company_member_can_manage_products
```

Result: blocked before test setup because the configured PostgreSQL host is unavailable:

```text
SQLSTATE[08006] [7] could not translate host name "pgsql" to address: Name or service not known
```

`vendor/bin/sail` is not present in this checkout.

Passed:

```bash
php -l Modules/Accounting/tests/Support/EligibleTaxFixture.php
php -l Modules/Product/tests/Feature/ProductCrudTest.php
composer lint:check
graphify update .
```

## Review-Fix Commit

Pending.
