import type { PropsWithChildren } from 'react';
import CompanyLayout from '@/layouts/company/company-layout';

export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <CompanyLayout>
            <div className="py-2">{children}</div>
        </CompanyLayout>
    );
}
