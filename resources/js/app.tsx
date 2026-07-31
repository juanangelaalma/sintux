import { createInertiaApp } from '@inertiajs/react';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        if (name === 'welcome') {
            return null;
        }

        if (name.startsWith('auth/')) {
            return AuthLayout;
        }

        return AppLayout;
    },
    strictMode: true,
    progress: {
        color: '#465fff',
    },
});

// This will set light / dark mode on load...
initializeTheme();
