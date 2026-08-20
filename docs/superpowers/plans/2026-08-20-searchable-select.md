# Searchable Select Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Product's Chart of Account fields a reusable, searchable single-select UI.

**Architecture:** Add one controlled `SearchableSelect` presentation component under `resources/js/components/ui/`. It owns only dropdown visibility, keyboard navigation, filtering, and selection. Product maps Unit and Chart of Account records to the component; the existing Unit-specific creation request remains in `unit-combobox.tsx`.

**Tech Stack:** React 19, TypeScript, Tailwind CSS, Inertia.js, ESLint, Prettier.

## Global Constraints

- Do not add an npm dependency.
- Use ASCII in new source code.
- The component is single-select and does not include Product or accounting business rules.
- Search matches supplied option labels case-insensitively.
- A null value is optional and can be cleared by users.
- Preserve Product's existing Chart of Account Laravel validation.

---

## File Structure

- Create: `resources/js/components/ui/searchable-select.tsx` - controlled, accessible single-select search UI.
- Modify: `resources/js/pages/Product/components/unit-combobox.tsx` - compose the shared select while preserving Unit creation behavior.
- Modify: `resources/js/pages/Product/Products/create.tsx` - replace local Chart of Account select with shared select.

### Task 1: Create SearchableSelect

**Files:**
- Create: `resources/js/components/ui/searchable-select.tsx`

**Interfaces:**
- Produces:

```ts
export type SearchableSelectOption = {
    id: number;
    label: string;
};

export type SearchableSelectProps = {
    options: SearchableSelectOption[];
    value: number | null;
    onChange: (value: number | null) => void;
    placeholder: string;
    emptyMessage?: string;
};

export function SearchableSelect(props: SearchableSelectProps): React.JSX.Element;
```

- [ ] **Step 1: Add a focused component test if a React test runner is configured**

Run:

```bash
test -f vitest.config.ts -o -f jest.config.ts
```

Expected: If a configuration is present, add a test proving filtering by an option label and clearing emits `null`. If absent, do not add test infrastructure.

- [ ] **Step 2: Implement the controlled component**

Create `resources/js/components/ui/searchable-select.tsx` with this public shape:

```tsx
export type SearchableSelectOption = {
    id: number;
    label: string;
};

export function SearchableSelect({ options, value, onChange, placeholder, emptyMessage = 'Tidak ada hasil' }: SearchableSelectProps) {
    // Keep open state and query local. Derive the selected option and filtered options from props.
    // Use a ref plus document mousedown listener to close on outside click.
    // Render an input with role="combobox" and a list with role="listbox".
    // Enter chooses the highlighted option, ArrowDown/ArrowUp moves it, Escape closes it.
    // A visible clear button calls onChange(null) when a value is selected.
}
```

- [ ] **Step 3: Run the focused test or static verification**

Run:

```bash
npx eslint resources/js/components/ui/searchable-select.tsx
npx tsc --noEmit --pretty false
```

Expected: exit status `0`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/ui/searchable-select.tsx
git commit -m "feat(ui): add searchable select"
```

### Task 2: Adapt Unit Combobox

**Files:**
- Modify: `resources/js/pages/Product/components/unit-combobox.tsx`

**Interfaces:**
- Consumes: `SearchableSelectOption` and `SearchableSelect` from `@/components/ui/searchable-select`.
- Produces: existing `UnitCombobox` props unchanged:

```ts
type Props = {
    value: number;
    options: UomOption[];
    onChange: (uomId: number) => void;
    onOptionAdded?: (newOption: UomOption) => void;
};
```

- [ ] **Step 1: Preserve existing add-Unit behavior in a local extension**

Replace only the duplicated selection UI. Map `UomOption` to `SearchableSelectOption`:

```tsx
const selectableOptions = uomOptions.map((option) => ({
    id: option.id,
    label: `${option.name} (${option.code})`,
}));
```

Keep `handleCreateNewUom` and render an add action only when a non-empty query has no exact Unit match. Do not move the POST request into `SearchableSelect`.

- [ ] **Step 2: Verify Unit selection types**

Run:

```bash
npx eslint resources/js/pages/Product/components/unit-combobox.tsx
npx tsc --noEmit --pretty false
```

Expected: exit status `0`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Product/components/unit-combobox.tsx
git commit -m "refactor(product): reuse searchable unit select"
```

### Task 3: Use SearchableSelect for Chart of Accounts

**Files:**
- Modify: `resources/js/pages/Product/Products/create.tsx`

**Interfaces:**
- Consumes: `SearchableSelect` from `@/components/ui/searchable-select`.
- Consumes page prop:

```ts
type ChartOfAccountOption = {
    id: number;
    code: string;
    name: string;
};
```

- [ ] **Step 1: Replace the local ChartOfAccountSelect helper**

Import and use the shared component. Map Account records without changing the backend contract:

```tsx
const chartOfAccountOptions = chartOfAccounts.map((account) => ({
    id: account.id,
    label: `${account.code} - ${account.name}`,
}));
```

Pass each form field directly:

```tsx
<SearchableSelect
    options={chartOfAccountOptions}
    value={form.data.purchase_account_id}
    onChange={(value) => form.setData('purchase_account_id', value)}
    placeholder="Pilih akun pembelian"
/>
```

Repeat with `sales_account_id` and `inventory_account_id`, using field-appropriate placeholder text.

- [ ] **Step 2: Verify the Product page**

Run:

```bash
npx eslint resources/js/pages/Product/Products/create.tsx
npx tsc --noEmit --pretty false
git diff --check
```

Expected: every command exits with status `0`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Product/Products/create.tsx
git commit -m "feat(product): search chart of accounts"
```

### Task 4: Final Verification

**Files:**
- Modify: `graphify-out/graph.json` and generated graph artifacts only if graphify changes them.

- [ ] **Step 1: Run frontend quality checks**

Run:

```bash
npm run lint:check
npm run format:check
npm run types:check
```

Expected: exit status `0`. If unrelated repository errors fail global checks, record the exact failures and run focused checks for the three changed frontend files.

- [ ] **Step 2: Run Product feature test**

Run:

```bash
php artisan test Modules/Product/tests/Feature/ProductCrudTest.php
```

Expected: pass. If PostgreSQL is unavailable, record the connection error; do not change test configuration.

- [ ] **Step 3: Update code graph**

Run:

```bash
graphify update .
```

Expected: graph artifacts refreshed.

- [ ] **Step 4: Inspect final diff**

Run:

```bash
git diff --check
git status --short
```

Expected: no whitespace errors and only intended files changed.

## Self-Review

- Spec coverage: Tasks 1-3 cover reusable search, Chart of Account selection, Unit reuse, outside click, clearing, keyboard handling, and no new dependency. Task 4 covers validation.
- Placeholders: none.
- Type consistency: every consumer uses `number | null`; Unit adapts its non-null selected ID through its existing callback.
