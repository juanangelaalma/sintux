import { Head, useForm } from '@inertiajs/react';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import PageHeader from '@/components/ui/page-header';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
import CompanyLayout from '@/layouts/company/company-layout';
import { warehouseLabels } from './types';
import type { BranchOption, Warehouse, WarehouseForm } from './types';

type Props = {
    warehouse: Warehouse;
    branches: BranchOption[];
};

export default function Edit({ warehouse, branches }: Props) {
    const form = useForm<WarehouseForm>({
        branch_id: warehouse.branch_id,
        code: warehouse.code,
        name: warehouse.name,
        warehouse_type: warehouse.warehouse_type,
        address: warehouse.address ?? '',
        is_active: warehouse.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/warehouse/warehouses/${warehouse.id}`);
    };

    return (
        <CompanyLayout>
            <Head title={`Edit ${warehouseLabels.singular}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title={`Edit ${warehouseLabels.singular}`} description={warehouseLabels.description} />

                <form onSubmit={submit} className="space-y-6">
                    <FormField label="Branch" required error={form.errors.branch_id}>
                        <SelectInput
                            value={form.data.branch_id}
                            onChange={(e) => form.setData('branch_id', Number(e.target.value))}
                        >
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name} ({branch.code})
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>

                    <FormField label="Kode" required error={form.errors.code}>
                        <TextInput value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                    </FormField>

                    <FormField label="Nama" required error={form.errors.name}>
                        <TextInput value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </FormField>

                    <FormField label="Tipe" required error={form.errors.warehouse_type}>
                        <SelectInput
                            value={form.data.warehouse_type}
                            onChange={(e) =>
                                form.setData('warehouse_type', e.target.value as WarehouseForm['warehouse_type'])
                            }
                        >
                            <option value="general">Umum</option>
                            <option value="regular">Reguler</option>
                            <option value="consignment">Konsinyasi</option>
                        </SelectInput>
                    </FormField>

                    <FormField label="Alamat" error={form.errors.address}>
                        <textarea
                            value={form.data.address}
                            onChange={(e) => form.setData('address', e.target.value)}
                            rows={3}
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                    </FormField>

                    <FormField label="Status">
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) => form.setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                            />
                            <span className="text-sm text-gray-700 dark:text-gray-300">Aktif</span>
                        </label>
                    </FormField>

                    <FormActions onCancel={() => window.history.back()} submitLabel="Perbarui" processing={form.processing} />
                </form>
            </div>
        </CompanyLayout>
    );
}
