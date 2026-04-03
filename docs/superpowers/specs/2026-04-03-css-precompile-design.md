# Design: Pre-compiled CSS for dbmyadmin package

**Date:** 2026-04-03  
**Status:** Approved  
**Version target:** v1.0.8

## Problem

The blade views in `resources/views/` use Tailwind CSS utility classes extensively (spacing, colors, layout, dark mode variants). Because the files live in `vendor/lucapellegrino/dbmyadmin/` on the end-user's site, Tailwind's JIT purge never scans them. The result: no styles, no spacing, no colors — the UI is completely unstyled.

## Solution

Ship a pre-compiled `dist/dbmyadmin.css` with the package and auto-load it via Filament's `FilamentAsset` mechanism. The package developer runs a build step once; end users need zero configuration.

## Architecture

### Build pipeline (package dev only)

| File | Purpose |
|------|---------|
| `package.json` | Declares `tailwindcss@^3` as devDependency; defines `build:css` script |
| `tailwind.config.js` | Content: `resources/views/**/*.blade.php`; extends theme with Filament color names |
| `resources/css/dbmyadmin.css` | Tailwind input file — contains only `@tailwind utilities` |
| `dist/dbmyadmin.css` | Generated output, minified, committed to the repo |

### Runtime (end-user site)

`DbMyAdminServiceProvider::boot()` calls:

```php
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

FilamentAsset::register([
    Css::make('dbmyadmin-styles', __DIR__ . '/../dist/dbmyadmin.css'),
], package: 'lucapellegrino/dbmyadmin');
```

Filament injects the stylesheet into every panel page automatically.

## Color mapping

Tailwind does not ship `primary`, `success`, etc. by default. We extend the theme:

| Filament name | Mapped to Tailwind color |
|---------------|--------------------------|
| `primary`     | `violet`                 |
| `success`     | `emerald`                |
| `danger`      | `red`                    |
| `warning`     | `amber`                  |
| `info`        | `blue`                   |
| `gray`        | already in Tailwind core |

These are compiled as hardcoded values. If the end-user customizes their Filament panel's primary color, Filament's own components will adapt but our layout accents will use violet. This is acceptable for an admin utility tool.

## Tailwind config details

```js
const colors = require('tailwindcss/colors')

module.exports = {
  content: ['./resources/views/**/*.blade.php'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: colors.violet,
        success: colors.emerald,
        danger:  colors.red,
        warning: colors.amber,
        info:    colors.blue,
      },
    },
  },
  plugins: [],
}
```

## Files changed

| File | Change |
|------|--------|
| `package.json` | New file |
| `tailwind.config.js` | New file |
| `resources/css/dbmyadmin.css` | New file |
| `dist/dbmyadmin.css` | New file (generated artifact committed to repo) |
| `src/DbMyAdminServiceProvider.php` | Add `FilamentAsset::register()` in `boot()` |

## Release

Tag `v1.0.8`, push to GitHub, instruct user to run `composer update lucapellegrino/dbmyadmin` in the test app.

## Out of scope

- No changes to blade views
- No design improvements beyond making existing styles work
- No CSS variables / dynamic theming
