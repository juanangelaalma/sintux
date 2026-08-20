# Task 3 Report: Enforce Tax Posting Direction

## Boundary Fix

- Replaced Product's direct `taxes` table validation rules with closure validation backed by `Modules\Accounting\Application\TaxQuery`.
- Added `TaxQuery::isEligibleForPurchase(int $taxId): bool` and `TaxQuery::isEligibleForSale(int $taxId): bool`; both require an active tax and the matching non-null posting account.
- Preserved nullable Product tax fields and field-specific errors.
- Added Accounting eligibility predicate coverage and an independent Product sales-direction rejection using `EligibleTaxFixture`.

## Final Verification

| Command | Result |
| --- | --- |
| `php -l Modules/Accounting/app/Application/TaxQuery.php Modules/Product/app/Http/Requests/{StoreProductRequest,UpdateProductRequest}.php Modules/Accounting/tests/Feature/TaxQueryTest.php Modules/Product/tests/Feature/ProductCrudTest.php` | Passed. |
| `composer lint:check` | Passed. |
| `git diff --check` | Passed. |
| `php artisan test Modules/Accounting/tests/Feature/TaxQueryTest.php` | Blocked before assertions: `SQLSTATE[08006] [7] could not translate host name "pgsql" to address: Name or service not known`. |
| `php artisan test Modules/Product/tests/Feature/ProductCrudTest.php --filter=test_company_member_can_manage_products` | Blocked before assertions: `SQLSTATE[08006] [7] could not translate host name "pgsql" to address: Name or service not known`. |

## Changes

- Added `Rule::exists` validation to Store and Update product requests.
- Purchase tax IDs now require active taxes with `input_account_id`.
- Sales tax IDs now require active taxes with `output_account_id`.
- Added create and update rejection assertions for a sales-only tax used as `purchase_tax_id`.
- Tests use `Modules\Accounting\Tests\Support\EligibleTaxFixture` and do not access Accounting models or tax tables directly.

## Tests

| Command | Result |
| --- | --- |
| `php artisan test Modules/Product/tests/Feature/ProductCrudTest.php --filter=test_company_member_can_manage_products` | Blocked: `SQLSTATE[08006] [7] could not translate host name "pgsql" to address: Name or service not known` during test setup. |
| `php artisan test Modules/Product/tests/Feature/ProductCrudTest.php` | Blocked: both tests fail with the same unavailable `pgsql` host during test setup. |
| `composer lint:check` | Passed. |

## Concerns

- No Docker Compose services are running. The test configuration inherits `DB_HOST=pgsql` from `.env`, which resolves only from the Sail network. Start Sail and run the Product feature test to observe the required red/green validation behavior.
