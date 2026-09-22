import { useEffect } from 'react';
import { useSidebar } from '@/context/SidebarContext';

const Backdrop: React.FC = () => {
    const { isMobileOpen, toggleMobileSidebar } = useSidebar();

    useEffect(() => {
        if (!isMobileOpen) {
            return;
        }

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                toggleMobileSidebar();
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isMobileOpen, toggleMobileSidebar]);

    if (!isMobileOpen) {
        return null;
    }

    return (
        <button
            type="button"
            aria-label="Tutup navigasi"
            className="fixed inset-0 z-40 cursor-default bg-gray-900/50 lg:hidden"
            onClick={toggleMobileSidebar}
        />
    );
};

export default Backdrop;
