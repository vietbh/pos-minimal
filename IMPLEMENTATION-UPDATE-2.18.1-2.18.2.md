# Implementation update — Phase 2.18.1 + 2.18.2

This repository contains the implementation of the prompt sources already stored under `docs/prompt-source/`.

## Implemented

- Home/Dashboard simplified around Today, Attention, Quick Actions and a single 7-day sales chart.
- Permission-aware dashboard tabs: Overview, Operations, Insights, Administration.
- Existing `role_label` / `is_admin_role` Home view contract remains controller-owned.
- 7-day sales are aggregated in the read-side repository; Twig only renders the supplied values.
- Chart has real numeric summary, real 7-day values and million-VND display labels.
- Canonical semantic UI tokens and global button/form/card/table/status/feedback/tabs/theme overrides added to `assets/styles/app.css`.
- Light/Dark/System appearance reuses the existing `data-appearance` and User preference infrastructure.
- Base HTML now has a deterministic `data-appearance` value even for anonymous pages (`system`).
- Existing font size, density, high-contrast and reduced-motion attributes remain supported.
- Business data is configured to wrap/reflow rather than being hidden by ellipsis in the affected Home/global primitives.
- Added unit coverage for dashboard navigation resolution.

## Not invented

The current repository has no Guild domain/application/UI implementation. The Active Guild rule from the prompt is therefore not fabricated into the codebase; it remains applicable when a real Guild context exists.

The current repository also has no dedicated Home Activity read model/page. An Activity tab was not invented merely to fill a navigation slot.

## Validation

- PHP syntax validation passed for all changed PHP files.
- Vietnamese and English translation YAML parsed successfully.
- Static Home/controller/theme/chart contract checks passed.
- PHPUnit could not be executed in this runtime because the repository does not contain `vendor/` and the `composer` executable is unavailable.
