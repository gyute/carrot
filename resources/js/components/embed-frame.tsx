import { ExternalLink } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Logical viewport the embedded page is laid out against, in CSS pixels. The
 * frame is rendered this wide and then scaled down to whatever room the portal
 * has, so the page never scrolls sideways and never drops to its mobile layout
 * just because the portal's content column is narrow.
 */
const FRAME_WIDTH = 1280;

/** Tracks how far the frame has to shrink to fit its box. Never scales up. */
function useScaleToFit() {
    const boxRef = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(1);

    useEffect(() => {
        const box = boxRef.current;

        if (!box) {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            setScale(Math.min(1, entry.contentRect.width / FRAME_WIDTH));
        });

        observer.observe(box);

        return () => observer.disconnect();
    }, []);

    return { boxRef, scale };
}

type Props = {
    url: string;
    title: string;
};

/**
 * An external page framed inside the portal. The frame is locked down: no
 * allow-top-navigation, so the embedded page cannot move the portal out from
 * under the user, and the URL only ever comes from an approved embed tool,
 * never from the request.
 *
 * scheme-light matters: the portal is light only, but a page that states no
 * colours of its own (a plain text file, say) follows the visitor's OS
 * setting and paints white text, which disappears against the frame's white
 * background.
 */
export default function EmbedFrame({ url, title }: Props) {
    const { boxRef, scale } = useScaleToFit();

    return (
        <>
            <div className="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
                <div className="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-500">
                    <span className="truncate font-mono">{url}</span>
                    <a
                        href={url}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="ml-auto inline-flex shrink-0 items-center gap-1 font-medium text-sky-700 underline decoration-sky-300 underline-offset-4"
                    >
                        新しいタブで開く
                        <ExternalLink className="size-3.5" />
                    </a>
                </div>

                <div
                    ref={boxRef}
                    className="relative h-[calc(100svh-24rem)] min-h-96 overflow-hidden bg-white"
                >
                    <iframe
                        key={url}
                        src={url}
                        title={title}
                        sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads"
                        referrerPolicy="no-referrer"
                        loading="lazy"
                        style={{
                            width: FRAME_WIDTH,
                            height: `${100 / scale}%`,
                            transform: `scale(${scale})`,
                            transformOrigin: 'top left',
                        }}
                        className="block border-0 bg-white scheme-light"
                    />
                </div>
            </div>

            <p className="mt-3 text-xs text-slate-400">
                埋め込みを許可していないサイトは表示されません。その場合は「新しいタブで開く」から開いてください。
            </p>
        </>
    );
}
