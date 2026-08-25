import {
    BarChart3,
    CalendarDays,
    ClipboardList,
    FileSignature,
    FolderClosed,
    Mail,
    Megaphone,
    MessageSquareText,
    Users,
} from 'lucide-react';
import type { ComponentType } from 'react';

const COLUMNS = 8;
const ROWS = 5;

/**
 * Tone of every tile in the grid, read left to right, top to bottom. Kept as a
 * literal pattern so the curtain renders identically on every request.
 */
const TILE_PATTERN =
    '10203042' + '02400830' + '40063060' + '04200630' + '20063082';

const TILE_TONES: Record<string, string> = {
    '0': 'bg-white/0',
    '2': 'bg-white/6',
    '3': 'bg-black/4',
    '4': 'bg-white/4',
    '6': 'bg-black/6',
    '8': 'bg-white/8',
    '1': 'bg-white/10',
};

/** Icons dropped into individual tiles, keyed by the tile index they sit in. */
const TILE_ICONS: Record<number, ComponentType<{ className?: string }>> = {
    2: Megaphone,
    5: FolderClosed,
    8: FileSignature,
    15: Mail,
    19: BarChart3,
    24: Users,
    29: MessageSquareText,
    33: CalendarDays,
    38: ClipboardList,
};

/**
 * The tiled blue curtain used behind the portal gate pages.
 */
export default function PortalBackdrop() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 overflow-hidden bg-linear-135 from-sky-500 via-sky-600 to-blue-800"
        >
            <div
                className="grid h-full w-full"
                style={{
                    gridTemplateColumns: `repeat(${COLUMNS}, minmax(0, 1fr))`,
                    gridTemplateRows: `repeat(${ROWS}, minmax(0, 1fr))`,
                }}
            >
                {TILE_PATTERN.split('').map((tone, index) => {
                    const Icon = TILE_ICONS[index];

                    return (
                        <div
                            key={index}
                            className={`flex items-center justify-center ${TILE_TONES[tone]}`}
                        >
                            {Icon && (
                                <Icon className="hidden size-[42%] max-h-24 max-w-24 text-white/12 sm:block" />
                            )}
                        </div>
                    );
                })}
            </div>

            <div className="absolute inset-0 bg-linear-to-b from-transparent via-transparent to-blue-950/25" />
        </div>
    );
}
