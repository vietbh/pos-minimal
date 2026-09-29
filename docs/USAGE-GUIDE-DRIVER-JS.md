# Interactive Usage Guide

The application uses Driver.js 1.4.0 for interactive, action-by-action walkthroughs.

- Driver.js is loaded from the pinned jsDelivr CDN assets in `templates/base.html.twig`.
- The Stimulus controller is `assets/controllers/usage_guide_controller.js`.
- A guide is selected with the `?guide=` query parameter and runs against real controls on the current page.
- Guides never submit business mutations automatically; they only highlight controls and explain what the user should do.
- Stable `data-guide` markers are used instead of positional CSS selectors.
- `/app/guide` links to the real module and starts the corresponding guide after navigation.

Reference: https://driverjs.com/docs/installation
