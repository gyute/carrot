---
paths:
  - 'resources/js/components/**'
---

# Components

## The carrot mark is drawn twice, and its shape has two traps
PortalCarrot in components/portal-logo.tsx and public/favicon.svg hold the same glyph by hand - there is no shared source, so change both together and re-render favicon.ico and apple-touch-icon.png from favicon.svg. apple-touch-icon needs an opaque background because iOS paints transparency black.
The mark has no tile behind it and appears on the blue header, the near-black sidebar and white auth cards, so its colors must stay mid-tone (pale vanishes on white, dark vanishes in dark mode) and the leaves thick enough to read at 16px.
Shape: the tip arc is the circle inscribed between the two sides - a plain arc across truncated sides bulges out and reads as a droplet. The lobe curve's tangent at the shoulder is blended toward the side direction; a plain corner kinks, full tangency flattens the shoulder into a leaf.
