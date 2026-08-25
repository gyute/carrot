---
paths:
  - 'resources/**'
---

# Resources

## Portal gate UI: Japanese copy, original branding, forced light palette
The app is a copyright-free sample groupware portal. All UI copy is Japanese (APP_LOCALE=ja, Japanese validation/auth strings live in lang/ja/). The product is called CARROT - no industry qualifier, no tagline. The wordmark and carrot mark in resources/js/components/portal-logo.tsx are original; never copy names, logos, or assets from the real groupware products used as visual references.
Auth pages render through layouts/auth/auth-portal-layout.tsx (tiled blue backdrop in components/portal-backdrop.tsx). Pages pass `title`/`description`/`wide` via their static `.layout` object.
Gate and landing surfaces carry the `.portal-surface` class (resources/css/app.css). It re-declares the `--color-*` theme tokens AND `color`, because body's inherited `text-foreground` is already computed - redefining only `--background` etc. has no effect on descendants.
