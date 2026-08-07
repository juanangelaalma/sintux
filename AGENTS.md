# AGENTS.md

## Project Architecture & AI Coding Rules

This project is a **Laravel Modular Monolith** for a multi-tenant accounting/ERP application.

The application uses:

* Laravel
* Inertia.js
* React
* TypeScript
* `stancl/tenancy` for multi-tenancy
* Tenant isolation using separate database/schema per tenant
* Laravel built-in authentication
* Modular Monolith architecture

The primary architectural goal is:

> **Keep business modules highly cohesive, keep dependencies explicit, and avoid unnecessary abstraction or over-engineering.**

---

# 1. Core Architectural Principles

Follow these principles when implementing or modifying code:

1. **Business capabilities define modules.**
2. **Modules own their own business logic.**
3. **Do not directly access another module's internal implementation.**
4. **Cross-module communication must go through a public Application API or domain/application events.**
5. **Prefer simple solutions over unnecessary abstractions.**
6. **Do not introduce interfaces, repositories, DTOs, factories, commands, queries, or other patterns unless there is a concrete reason.**
7. **Accounting is a core module and must have a particularly strong boundary.**
8. **Tenant context must be handled centrally by the tenancy infrastructure, not manually inside business logic.**
9. **React is a presentation layer. Business rules belong to the Laravel backend.**
10. **Do not optimize the architecture for theoretical future requirements. Optimize it for current business complexity while preserving clear boundaries.**

---

# 2. High-Level Architecture

The application is a Modular Monolith.

Recommended module structure:

```text
app/
└── Modules/
    ├── Sales/
    ├── Product/
    ├── Purchasing/
    ├── Inventory/
    ├── Payment/
    ├── Tax/
    ├── Contact/
    ├── Accounting/
    └── Reporting/
```

Each module should be independently understandable and own its business capability.

A module should generally follow:

```text
Module/
├── Application/
├── Domain/
├── Infrastructure/
└── Http/
```

Additional folders should only be introduced when justified by actual complexity.

Do NOT create a large predefined folder hierarchy just for architectural purity.

---

# 3. Module Responsibilities

Each layer has a specific responsibility.

## Domain

The Domain contains business concepts and business rules.

Examples:

```text
Domain/
├── Invoice.php
├── InvoiceItem.php
├── InvoiceStatus.php
└── Rules/
```

Domain code should answer:

> "What are the business rules?"

Examples:

* An already-paid invoice cannot be cancelled.
* A journal must balance.
* A fiscal period cannot accept postings after closing.
* An invoice must contain at least one item.

Domain code should NOT contain infrastructure concerns such as:

```php
DB::table(...)
Http::get(...)
Storage::put(...)
Inertia::render(...)
Request
```

The domain should not depend on React, Inertia, HTTP, or database implementation details.

---

# 4. Application Layer

Application contains use cases and orchestration.

Examples:

```text
Application/
├── CreateInvoice.php
├── PostInvoice.php
├── CancelInvoice.php
└── GetInvoice.php
```

Application code answers:

> "What does the system need to do?"

Application services may coordinate:

* Domain objects
* Other module public APIs
* Persistence
* Events
* External services

Example:

```php
class CreateInvoice
{
    public function execute(array $data): Invoice
    {
        // orchestrate the use case
    }
}
```

Application code should not contain HTTP-specific presentation logic.

Avoid:

```php
Inertia::render(...)
return response()->json(...)
$request->validated()
```

inside Application services.

---

# 5. Infrastructure Layer

Infrastructure contains technical implementation details.

Examples:

```text
Infrastructure/
├── Persistence/
├── External/
└── ServiceProvider.php
```

Infrastructure may contain:

* Eloquent models
* Repository implementations
* Database access
* External API clients
* File storage
* Queue infrastructure
* Laravel service providers

Infrastructure answers:

> "How is this technically implemented?"

For example:

```php
class EloquentInvoiceRepository
{
    public function save(Invoice $invoice): void
    {
        // Eloquent persistence
    }
}
```

Do not move business rules into infrastructure merely because the code interacts with Eloquent.

---

# 6. HTTP Layer

HTTP is an adapter between Laravel HTTP/Inertia and the Application layer.

Example:

```text
Http/
├── Controllers/
└── Requests/
```

Controllers should remain thin.

Preferred flow:

```text
HTTP Request
    ↓
Controller
    ↓
Application Use Case
    ↓
Domain
    ↓
Infrastructure
```

Avoid putting significant business logic inside controllers.

Bad:

```php
public function store(Request $request)
{
    // 100 lines of invoice business logic
}
```

Preferred:

```php
public function store(CreateInvoiceRequest $request)
{
    $invoice = $this->createInvoice->execute(
        $request->validated()
    );

    return redirect()->route(...);
}
```

---

# 7. Do Not Over-Engineer

The architecture should evolve with the application.

Do NOT automatically create:

```text
Interface
Implementation
RepositoryInterface
Repository
ServiceInterface
Service
DTO
Factory
Mapper
Command
Handler
Query
QueryHandler
```

for every feature.

For example, do not create:

```text
ProductRepositoryInterface
ProductRepository
ProductServiceInterface
ProductService
ProductFactory
ProductMapper
ProductDTO
```

unless there is a real architectural or business reason.

Prefer:

```text
Application/
└── ProductQuery.php
```

when that is sufficient.

Introduce additional abstractions when:

* A dependency needs to be replaced.
* Multiple implementations genuinely exist.
* Testing requires a meaningful boundary.
* A module has become sufficiently complex.
* A stable contract between modules is required.
* The abstraction protects an important architectural boundary.

> **Abstraction is a tool, not a requirement.**

---

# 8. Cross-Module Communication

Modules may depend on other modules, but dependencies must be explicit.

Example:

```text
Sales → Product
```

is acceptable.

However:

```text
Sales → Product internal Model
Sales → Product internal Repository
Sales → Product database implementation
```

is not acceptable.

## Preferred approach

Expose a public Application API.

Example:

```text
Product/
└── Application/
    └── ProductQuery.php
```

Sales can use:

```php
$product = $productQuery->findForSale($productId);
```

Instead of:

```php
use Modules\Product\Infrastructure\Models\ProductModel;

$product = ProductModel::find($productId);
```

The dependency should look like:

```text
Sales
  │
  │ Product Application API
  ▼
Product
  │
  ▼
Infrastructure
  │
  ▼
Database
```

Never bypass the module boundary.

---

# 9. Prefer Use-Case-Specific Cross-Module APIs

Avoid generic APIs when a more explicit API better represents the business requirement.

Prefer:

```php
$productQuery->findForSale($productId);
```

over:

```php
$productQuery->find($productId);
```

when Sales only needs product information relevant to selling.

Other consumers may have different needs:

```php
$productQuery->findForPurchase($productId);

$productQuery->findForInventory($productId);
```

This prevents consumers from becoming coupled to the complete internal Product model.

---

# 10. Domain/Application Events

Use events when the requirement is:

> "Something happened, and other modules may react."

Example:

```text
InvoicePosted
      │
      ├── Accounting
      ├── Inventory
      ├── Tax
      └── Reporting
```

Example:

```php
InvoicePosted::dispatch($invoiceId);
```

Events are appropriate for:

* Cross-module reactions
* Notifications
* Accounting reactions
* Inventory reactions
* Reporting projections
* Other loosely coupled side effects

Do NOT use events for every interaction.

If a module simply needs data immediately, prefer a synchronous Application API:

```text
Sales → ProductQuery
```

Use:

```text
Sales → Event → Other Modules
```

when the semantics are:

> "Invoice has been posted."

not:

> "Please give me this product."

---

# 11. Shared Database Does Not Mean Shared Models

The application may use a shared physical database infrastructure while keeping module boundaries.

Modules must not assume that because tables are in the same database they may freely use each other's Eloquent models.

Avoid:

```php
use Modules\Product\Infrastructure\Models\ProductModel;
```

inside Sales.

Prefer the Product Application API.

The database is an implementation detail.

---

# 12. Accounting Module

Accounting is a critical domain and should have a strong boundary.

Typical structure:

```text
Accounting/
├── Application/
│   ├── RecordJournal.php
│   ├── PostJournal.php
│   ├── ReverseJournal.php
│   └── GetLedger.php
│
├── Domain/
│   ├── Journal.php
│   ├── JournalLine.php
│   ├── Account.php
│   ├── FiscalPeriod.php
│   └── Rules/
│
├── Infrastructure/
└── Http/
```

Other modules must NOT directly create or manipulate accounting internals.

Do not do:

```php
JournalEntry::create(...);
```

from Sales.

Instead:

```text
Sales
  ↓
InvoicePosted
  ↓
Accounting
  ↓
Create Journal
```

Accounting owns:

* Journal rules
* Debit/credit rules
* Ledger rules
* Fiscal period rules
* Accounting posting rules
* Reversal rules
* Financial integrity

Other modules provide business facts; Accounting determines how those facts affect the ledger.

---

# 13. Financial History and Snapshots

Accounting and transactional data must preserve historical correctness.

Do not depend on live Product data for historical invoice values.

Example:

```text
Product today:
Laptop
Price = $1,000
```

Invoice should store its historical transaction data:

```text
InvoiceItem
├── product_id
├── product_name
├── unit_price
├── tax_rate
└── quantity
```

If the Product price later changes to $1,200, an existing invoice must remain $1,000.

Use references to master data where appropriate, but store historical transactional values where business correctness requires it.

---

# 14. Multi-Tenancy

The application uses `stancl/tenancy`.

Multi-tenancy is a fundamental architectural concern and must be considered from the beginning.

Do not treat tenancy as something to retrofit after the application is complete.

However, do not spread tenancy logic throughout every module.

The desired flow is:

```text
Request
   ↓
Authenticate User
   ↓
Identify Tenant
   ↓
Initialize Tenant Context
   ↓
Module Application
   ↓
Infrastructure
   ↓
Current Tenant Database/Schema
```

Business modules should not manually determine the tenant database/schema.

Avoid:

```php
DB::connection('tenant')->...
```

inside business logic unless there is a specific infrastructure-level reason.

Avoid:

```php
tenant_123.products
```

or manually constructing tenant-specific database/schema names.

Tenant resolution and database/schema switching belong to the tenancy infrastructure.

---

# 15. Tenant Isolation

The application uses tenant isolation through separate database/schema per tenant.

Conceptually:

```text
Tenant A
└── products
└── invoices
└── journals

Tenant B
└── products
└── invoices
└── journals
```

Tenant business data belongs to the tenant database/schema.

Do not assume every tenant table needs:

```text
tenant_id
```

when database/schema isolation already provides the isolation boundary.

A `tenant_id` should only be added when it has a genuine business or technical purpose.

---

# 16. Central vs Tenant Data

Keep the distinction between central data and tenant-owned business data clear.

Conceptually:

```text
Central
├── users
├── tenants
├── domains
├── tenant_memberships
└── subscriptions

Tenant
├── products
├── customers
├── invoices
├── payments
├── journals
└── ledger_entries
```

The exact data ownership should be decided according to the business requirement.

Authentication and tenant identity should not be duplicated unnecessarily across every tenant database.

---

# 17. Authentication

Use Laravel's built-in authentication unless a concrete enterprise requirement requires an external identity provider.

Current default:

```text
Authentication Provider
→ Laravel Built-in
```

Do not introduce WorkOS, SSO, or other authentication infrastructure without a concrete requirement.

Authentication should establish:

```text
User
    ↓
Tenant Membership
    ↓
Active Tenant
```

Authorization should then determine what the authenticated user can do within that tenant.

---

# 18. Inertia + React

The frontend uses:

```text
Laravel
+
Inertia
+
React
+
TypeScript
```

React is the presentation layer.

Recommended structure:

```text
resources/
└── js/
    ├── Pages/
    │   ├── Sales/
    │   ├── Product/
    │   ├── Purchasing/
    │   ├── Inventory/
    │   ├── Accounting/
    │   └── Reporting/
    │
    ├── Components/
    ├── Layouts/
    └── app.tsx
```

React must not contain core business rules.

React is responsible for:

* Rendering UI
* User interaction
* Form state
* Presentation logic
* Client-side UI state

Before writing page markup, search `resources/js/components`. Extract presentational UI when it is reused or clearly needed by multiple pages; keep business-specific page UI local and avoid speculative one-use abstractions.

Laravel is responsible for:

* Business rules
* Authorization
* Validation of business constraints
* Transaction processing
* Cross-module orchestration
* Accounting rules
* Tenant isolation

---

# 19. Inertia Controllers

Controllers provide the boundary between Laravel and React.

Example:

```php
return Inertia::render('Sales/Invoices/Create', [
    'customers' => $customers,
    'products' => $products,
]);
```

The Controller may orchestrate Application APIs to prepare the data required by a page.

Do not put domain logic into Inertia controllers.

Preferred:

```text
React Page
    ↕
Inertia
    ↕
Controller
    ↓
Application
    ↓
Domain
```

---

# 20. Cross-Module Data for Inertia Pages

A page may require data from multiple modules.

For example:

```text
Invoice Create Page
├── Customers
├── Products
├── Tax Categories
└── Payment Terms
```

The backend may orchestrate these dependencies:

```text
InvoiceController
      │
      ├── Contact Application API
      ├── Product Application API
      ├── Tax Application API
      └── Payment Application API
```

Do not make React directly understand module internals.

React should receive page-oriented props.

---

# 21. Dashboard / Aggregated Views

For pages requiring data from many modules, it is acceptable to create a dedicated Application use case.

Example:

```text
Dashboard/
└── Application/
    └── GetDashboard.php
```

The use case may aggregate:

```text
Sales
Inventory
Accounting
Payment
```

into a page-oriented result.

Do not force React to make many unnecessary requests just because the backend has separate modules.

---

# 22. Frontend Components vs Module Boundaries

Not every React component needs to belong to a backend module.

Use:

```text
Pages/Sales/
```

for page-level module-specific UI.

Use:

```text
Components/
```

for genuinely reusable UI components.

Examples:

```text
Components/
├── DataTable.tsx
├── MoneyInput.tsx
├── DatePicker.tsx
└── Modal.tsx
```

Do not move business-specific components into `Components/` merely because they are technically reusable.

Prefer:

```text
Pages/Sales/Invoices/InvoiceForm.tsx
```

when the component is primarily a Sales concept.

---

# 23. Dependency Direction

Prefer dependencies that point toward stable business boundaries.

Example:

```text
HTTP
 ↓
Application
 ↓
Domain

Application
 ↓
Other Module's Public Application API

Application
 ↓
Infrastructure abstractions/implementations
```

Avoid:

```text
Sales
 ↓
Product Infrastructure
 ↓
Product Eloquent Model
```

Avoid:

```text
Domain
 ↓
HTTP
```

Avoid:

```text
Domain
 ↓
Inertia
```

Avoid:

```text
Domain
 ↓
React
```

---

# 24. Avoid God Modules

Do not allow Accounting, Core, Shared, or another module to become a dumping ground.

Especially avoid:

```text
Shared/
├── UserService.php
├── ProductService.php
├── InvoiceService.php
├── AccountingHelper.php
└── EverythingElse.php
```

If functionality clearly belongs to a business capability, put it in that module.

`Shared` should contain only genuinely shared, business-neutral functionality.

---

# 25. Database Transactions

When a use case modifies multiple records that must remain consistent, transaction boundaries should be handled at the Application/use-case level or an appropriate infrastructure abstraction.

Example:

```text
Post Invoice
    ↓
Create Invoice State
    ↓
Create Accounting Journal
    ↓
Update required state
```

If these operations must be atomic, ensure the transaction boundary covers the required operations.

Do not scatter transactions randomly across low-level repository methods.

---

# 26. Queues and Async Work

Use synchronous calls when the caller requires the result immediately.

Use jobs/events for:

* Long-running work
* Notifications
* Heavy reporting
* External integrations
* Non-critical side effects
* Processing that can safely happen asynchronously

Do not introduce asynchronous processing merely to make the architecture appear more distributed.

This is a monolith.

Use queues when they solve an actual problem.

---

# 27. Testing Strategy

Prefer tests around business behavior rather than implementation details.

Prioritize:

1. Domain/business rules
2. Application use cases
3. Module boundaries
4. Important HTTP flows
5. Tenant isolation
6. Critical accounting behavior

For accounting, tests should strongly protect:

* Debit/credit balancing
* Posting rules
* Reversal
* Fiscal periods
* Historical transaction correctness
* Tenant isolation

Do not create tests solely to satisfy architectural patterns.

---

# 28. AI Coding Rules

When modifying the codebase, AI agents MUST first understand the existing module boundary.

Before creating a new abstraction, ask:

1. Which module owns this business concept?
2. Is this business logic, application orchestration, infrastructure, or HTTP?
3. Does another module already expose the required functionality?
4. Can the problem be solved with the existing structure?
5. Is the proposed abstraction solving a real problem?

AI agents should NOT:

* Create unnecessary interfaces.
* Create unnecessary repositories.
* Create unnecessary DTOs.
* Create unnecessary service layers.
* Create unnecessary events.
* Create unnecessary API endpoints.
* Bypass module boundaries for convenience.
* Access another module's Eloquent models directly.
* Access another module's infrastructure directly.
* Put business rules inside React.
* Put business rules inside controllers.
* Manually switch tenant databases inside business modules.
* Introduce microservices because a module exists.
* Split the monolith into services without explicit architectural approval.

---

# 29. Before Adding a New Module

A new module should represent a meaningful business capability.

Good examples:

```text
Sales
Purchasing
Inventory
Accounting
Payment
Tax
Contact
Reporting
```

Do not create modules for technical concerns such as:

```text
Validation
Database
Email
Helpers
Utils
```

unless they genuinely represent an architectural boundary.

---

# 30. Architectural Decision Rule

When multiple valid solutions exist:

> Prefer the simplest solution that preserves the module boundary and business correctness.

Priority:

```text
Business correctness
        ↓
Clear module boundary
        ↓
Maintainability
        ↓
Testability
        ↓
Performance
        ↓
Abstraction
```

Do not sacrifice business correctness for architectural purity.

Do not sacrifice developer velocity for theoretical flexibility.

---

# 31. Example: Sales → Product

Correct:

```text
Sales
  │
  ▼
Product Application API
  │
  ▼
Product Infrastructure
  │
  ▼
Tenant Database
```

Example:

```php
$product = $productQuery->findForSale($productId);
```

Incorrect:

```php
use Modules\Product\Infrastructure\Models\ProductModel;

$product = ProductModel::find($productId);
```

---

# 32. Example: Sales → Accounting

Sales should not manipulate accounting internals.

Incorrect:

```php
JournalEntry::create(...);
```

Preferred:

```text
Sales
  ↓
InvoicePosted
  ↓
Accounting
  ↓
Accounting Application
  ↓
Journal
  ↓
Ledger
```

Accounting owns the accounting rules.

---

# 33. Example: Product → Sales

When Product changes:

```text
ProductUpdated
```

may notify interested modules if they need to react.

However, do not use events merely as a replacement for simple synchronous data access.

Use:

```text
ProductQuery
```

when Sales needs data immediately.

Use:

```text
ProductUpdated
```

when Sales needs to react to a change.

---

# 34. Architecture in One Diagram

The intended architecture can be summarized as:

```text
                         Browser
                            │
                            ▼
                    Inertia + React
                            │
                            ▼
                       HTTP Layer
                            │
                            ▼
                    Application Layer
                            │
                ┌───────────┼───────────┐
                │           │           │
                ▼           ▼           ▼
             Sales       Product    Accounting
                │           │           │
                └───── Public APIs / Events ─────┘
                            │
                            ▼
                         Domain
                            │
                            ▼
                     Infrastructure
                            │
                            ▼
                    Tenant DB / Schema
```

Tenant initialization happens before business operations:

```text
Request
  ↓
Authentication
  ↓
Tenant Identification
  ↓
Tenant Initialization
  ↓
Application
  ↓
Domain
  ↓
Infrastructure
  ↓
Current Tenant DB/Schema
```

---

# 35. Final Rule

This project is a **Modular Monolith, not a distributed system**.

We want:

```text
High Cohesion
+
Explicit Boundaries
+
Simple Dependencies
+
Strong Accounting Integrity
+
Tenant Isolation
+
Fast Development
```

We do NOT want:

```text
Maximum abstraction
+
Maximum number of interfaces
+
Maximum number of layers
+
Microservices complexity
```

The architecture should help developers build the product, not become the product itself.

> **When in doubt, preserve the business boundary and choose the simplest implementation that works.**
