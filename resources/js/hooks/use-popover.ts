import type { RefObject } from 'react';
import { useEffect, useRef, useState } from 'react';

/**
 * Open/close state for a hand-rolled popover: closes on Escape and on a
 * pointer-down outside `ref`. No focus trap, so an input inside keeps focus
 * while the user interacts with the rest of the panel.
 */
export function usePopover<T extends HTMLElement>(): {
    ref: RefObject<T | null>;
    open: boolean;
    setOpen: (open: boolean) => void;
    toggle: () => void;
} {
    const ref = useRef<T>(null);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            if (!ref.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    return { ref, open, setOpen, toggle: () => setOpen(!open) };
}
