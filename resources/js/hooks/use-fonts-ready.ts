import { useEffect, useState } from 'react';

/**
 * RAC's SelectionIndicator (SharedElement) snapshots its geometry on unmount
 * and replays it on remount. With StrictMode's double mount, the snapshot can
 * be taken while web fonts are still swapping, and the stale width/translate
 * permanently strands the tab pill. Mount the indicator only after fonts
 * settle so the snapshot always reflects the final layout.
 */
export function useFontsReady() {
    const [ready, setReady] = useState(false);

    useEffect(() => {
        let alive = true;

        document.fonts.ready.then(() => {
            if (alive) {
                setReady(true);
            }
        });

        return () => {
            alive = false;
        };
    }, []);

    return ready;
}
