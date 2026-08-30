import { createElement } from 'react';
import { toolIcon } from '@/lib/tool-presets';

/**
 * Renders the lucide icon a tool picked by name. Looking the component up
 * inside a component keeps the lookup out of the parent's render.
 */
export default function ToolIcon({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    return createElement(toolIcon(name), { className });
}
