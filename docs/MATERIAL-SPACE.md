# Material & Space / implementation notes

## Hierarchy

NORTHLINE remains an architectural editorial site. Material 3 supplies semantic roles and interaction foundations, not a generic app shell. The homepage moves from a large typographic introduction to the featured architectural image, then service scope, selected work, material detail and a practical planning invitation. The primary action always leads to the existing project planner.

## Source ownership

- `Studio.php`: native-block homepage and server-rendered material study markup.
- `Content.php`: shared pages, case studies and conservative content seeding.
- `Blocks.php` / `editor.js`: matching server and editor registrations.
- `tokens.css`: generated from `creative/material-space.tokens.json`.
- `studio.css`: responsive visual layer over the existing functional primitives.
- `studio.js`: progressive material selection; no external library.
- `render_architecture.py`: reproducible Blender scenes and matched camera pairs.
- `render_detail.py`: separately bounded material-detail jobs.

The WordPress and browser-demo routes use the same content source. Empty dynamic attributes serialize to `{}`, not `[]`; the latter previously caused the preview builder to drop the floorplan block.

## Interaction and fallback

Material buttons use native button semantics with `aria-pressed` and `aria-controls`. A polite live region announces the selected study. The three panels are ordinary server HTML and remain visible without JavaScript, while nonfunctional controls stay hidden. Mobile navigation is readable without scripts. Visible focus, minimum 48px material controls, reduced motion, print and forced-color treatments are included. These decisions are not a blanket claim of WCAG certification.

## Editing an existing installation

Theme/plugin activation does not replace edited page content. `wp northline seed` preserves existing pages. `wp northline design-draft` creates a new unpublished homepage for review; it does not change `page_on_front`. The complete design is also available as the `northline/material-space-home` block pattern. Ordinary editorial text remains editable as native WordPress blocks.

## Figma handoff status

Target file: https://www.figma.com/design/pK6rEgw84SKQMcF8jAhJln

On September 30, 2026, file creation succeeded, but subsequent canvas calls returned the connected Starter plan's MCP quota error. Therefore no completed editable Figma composition is claimed. The JSON design foundation and running site are the available handoff sources. A future Figma composition must contain editable text, components and hierarchy; it must not be replaced with a flattened full-page screenshot.

## Evidence interpretation

`tests/design.php` covers native markup contracts. `tests/design.cjs` covers responsive layout, route availability, real image decoding, material keyboard controls, mobile navigation and no-JavaScript reading. `tests/browser.cjs` covers the existing planner and optional real WordPress mobile-to-staff journey. Test artifacts identify the actual run and outcome. Screenshots are evidence of the implemented website, not Figma canvases or completed client projects.
