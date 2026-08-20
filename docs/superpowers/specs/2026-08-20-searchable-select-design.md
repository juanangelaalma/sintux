# Searchable Select Design

## Scope

Replace Product's static Chart of Account selects with one reusable, searchable single-select component. Reuse the same component for Unit selection without moving Unit creation behavior into it.

## Component

Create `resources/js/components/ui/searchable-select.tsx`.

Inputs:

- `options`: items with an ID and display label.
- `value`: selected ID or `null`.
- `onChange`: receives the selected ID or `null`.
- `placeholder`: empty-state text.

Behavior:

- Opens an input-backed dropdown.
- Filters options by the supplied display label, case-insensitively.
- Shows the selected label when closed.
- Allows clearing an optional selection.
- Closes after selection and on outside click.
- Uses semantic input/listbox roles and keyboard focusable options.

## Integration

`create.tsx` maps Chart of Account `code` and `name` to labels such as `1-10200 - Persediaan Barang`, then uses the component for purchase, sales, and inventory account fields.

`unit-combobox.tsx` uses the shared selection UI and retains only its Product-specific add-unit request and add-option action.

## Validation

Chart of Account IDs remain validated by `StoreProductRequest` and `UpdateProductRequest`. The client component does not add business validation.

## Tests

Add a focused frontend test only if the project has a runnable React test setup. Otherwise TypeScript, ESLint, Prettier, and the existing Product feature test are the verification path.
