"""Prepare editorial assets and handover documentation. Run inside the release workflow."""
from pathlib import Path
import re
import urllib.request

root = Path(__file__).resolve().parents[1]
images = root / 'theme/northline/assets/images'
images.mkdir(parents=True, exist_ok=True)
source = 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1800&q=88&fm=jpg'
request = urllib.request.Request(source, headers={'User-Agent': 'NORTHLINE-Portfolio-Asset-Review/1.0'})
with urllib.request.urlopen(request, timeout=40) as response:
    photo = response.read(12_000_000)
if not photo.startswith(b'\xff\xd8'):
    raise RuntimeError('Editorial source did not return a JPEG; do not publish a broken image.')
(images / 'editorial-interior.jpg').write_bytes(photo)

content = root / 'plugins/northline-planner/includes/Content.php'
s = content.read_text()
s = s.replace("self::image('birch-house-after.jpg', 'Original architectural visualisation of an oak and limestone kitchen opening towards a garden', 'nl-hero-image')", "self::image('editorial-interior.jpg', 'Editorial kitchen photograph with warm natural materials and daylight; not completed NORTHLINE client work', 'nl-hero-image')", 1)
s = s.replace("self::p('01 / BIRCH HOUSE', 'nl-kicker')", "self::p('01 / EDITORIAL INTERIOR', 'nl-kicker')", 1)
s = s.replace("self::p('Oak. Limestone. Room to breathe.')", "self::p('Material. Light. Everyday life.')", 1)
s = s.replace("self::p('Fictional design study / Original visualisation', 'nl-small')", "self::p('Editorial photography / Unsplash / Not NORTHLINE client work', 'nl-small')", 1)
content.write_text(s)
functions = root / 'theme/northline/functions.php'
functions.write_text(functions.read_text().replace("/assets/images/birch-house-after.jpg", "/assets/images/editorial-interior.jpg"))

# Explicit filenames make the optional static preview portable to static file CDNs.
builder = root / 'tools/build-preview.php'
s = builder.read_text()
needle = "    $dir = $out . ($route ? '/' . $route : '');"
if 'NORTHLINE_PORTABLE_LINKS' not in s:
    s = s.replace(needle, "    // NORTHLINE_PORTABLE_LINKS: WordPress itself keeps its normal permalinks.\n    $html = preg_replace('/href=\"([^\"]*\\/)\"/', 'href=\"$1index.html\"', $html);\n" + needle)
    s += "\nforeach (['planner.js', 'demo.js'] as $script) {\n    $file = $out . '/assets/' . $script;\n    if (!is_file($file)) throw new RuntimeException('Missing planner script: ' . $script);\n    $js = file_get_contents($file);\n    $js = preg_replace(\"~C\\\\.home\\\\+'(privacy|plan-your-renovation|consultation|demo-desk)/~\", \"C.home+'$1/index.html\", $js);\n    file_put_contents($file, $js);\n}\n"
builder.write_text(s)

# WordPress' block editor uses these native controls rather than a page-builder dependency.
editor = r'''/* Native WordPress controls for the four focused NORTHLINE dynamic blocks. */
(function (wp) {
  'use strict';
  const h = wp.element.createElement;
  const { registerBlockType } = wp.blocks;
  const { InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps } = wp.blockEditor;
  const { PanelBody, TextControl, SelectControl, RangeControl, ToggleControl, Button, Placeholder } = wp.components;
  const ServerSideRender = wp.serverSideRender.default || wp.serverSideRender;
  const definitions = {
    planner: { title: 'NORTHLINE project planner', attributes: { mode: { type: 'string', default: 'planner' } } },
    projects: { title: 'NORTHLINE project gallery', attributes: { limit: { type: 'number', default: 6 }, filters: { type: 'boolean', default: true } } },
    comparison: { title: 'NORTHLINE before & after', attributes: { before: { type: 'string', default: '' }, after: { type: 'string', default: '' }, label: { type: 'string', default: 'Matched architectural view' } } },
    plan: { title: 'NORTHLINE concept floor plan', attributes: { label: { type: 'string', default: 'Birch House / Ground floor' } } }
  };
  Object.entries(definitions).forEach(([name, definition]) => {
    registerBlockType('northline/' + name, {
      apiVersion: 3, category: 'design', icon: 'building', ...definition,
      supports: { html: false, multiple: name !== 'planner' },
      edit({ attributes, setAttributes }) {
        const controls = [];
        if (name === 'planner') controls.push(h(SelectControl, { key: 'mode', label: 'Workspace', value: attributes.mode, options: [{ label: 'Project planner', value: 'planner' }, { label: 'Private consultation desk', value: 'consultation' }], onChange: mode => setAttributes({ mode }) }));
        if (name === 'projects') {
          controls.push(h(RangeControl, { key: 'limit', label: 'Number of projects', min: 1, max: 60, value: attributes.limit, onChange: limit => setAttributes({ limit }) }));
          controls.push(h(ToggleControl, { key: 'filters', label: 'Show category filters', checked: attributes.filters, onChange: filters => setAttributes({ filters }) }));
        }
        if ('label' in attributes) controls.push(h(TextControl, { key: 'label', label: 'Caption / accessible label', value: attributes.label, onChange: label => setAttributes({ label }) }));
        if (name === 'comparison') {
          ['before', 'after'].forEach(key => {
            controls.push(h('div', { key },
              h(TextControl, { label: key + ' image URL', value: attributes[key], onChange: value => setAttributes({ [key]: value }) }),
              h(MediaUploadCheck, {}, h(MediaUpload, {
                allowedTypes: ['image'],
                onSelect: media => setAttributes({ [key]: media.url }),
                render: ({ open }) => h(Button, { variant: 'secondary', onClick: open }, 'Choose ' + key + ' image')
              }))
            ));
          });
        }
        const preview = name === 'planner'
          ? h(Placeholder, { label: definition.title, instructions: 'The live website loads the accessible, save-first workflow. Choose the planner or consultation workspace in the inspector.' })
          : h(ServerSideRender, { block: 'northline/' + name, attributes });
        return h('div', useBlockProps(), h(InspectorControls, {}, h(PanelBody, { title: definition.title }, controls)), preview);
      },
      save() { return null; }
    });
  });
})(window.wp);
'''
(root / 'plugins/northline-planner/assets/editor.js').write_text(editor)

(root / 'docs').mkdir(exist_ok=True)
(root / 'docs/ASSETS.md').write_text('''# Image provenance

## Editorial homepage photograph

The homepage uses an editorial interior photograph obtained from Unsplash. It is not a NORTHLINE commission, case-study result or photograph generated specifically for this fictional practice.

Source: https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea
License reference: https://unsplash.com/license
Local file: `theme/northline/assets/images/editorial-interior.jpg`.

The photo is labelled as editorial photography on the page. Do not remove that distinction or imply the pictured property is a completed NORTHLINE project.

## Six matched design studies

The twelve before/after images are original Blender Cycles renders, not photographs. Each pair keeps its camera and architectural shell. The adjacent provenance JSON files record the rendering setup. Reproduce them with `tools/render_architecture.py` and the pinned Blender image workflow.

These studies are deliberately described as fictional proposals. Do not invent client testimonials, completed contracts, awards or construction approvals. Native AI image generation was not used; the original generated case-study imagery is authored 3D visualisation.

The technical render sources are included so the designs can be refined rather than relying on untraceable image files.
''')
(root / 'docs/HANDOVER.md').write_text('''# NORTHLINE — owner handover

## Install

Use an HTTPS WordPress installation with PHP 8.2 or later, MySQL/MariaDB tables using InnoDB, and PHP GD plus fileinfo. Upload `northline-theme.zip` in Appearance → Themes and activate it. Upload `northline-planner-plugin.zip` in Plugins and activate it. Set your site timezone in Settings → General and select a readable permalink structure in Settings → Permalinks.

Open the NORTHLINE admin menu and select **Install missing demo content**. This creates eight main pages, two utility pages and six project case studies. Repeating the operation preserves existing edited content. The home page is assigned only when a front page has not already been configured.

CLI alternative:

```sh
wp plugin activate northline-planner
wp theme activate northline
wp option update timezone_string America/Halifax
wp rewrite structure '/%postname%/'
wp northline seed
```

## Edit without code

Open **Project case studies**, choose a project and use the normal WordPress block editor to change its headings, paragraphs, materials and construction sequence. The before/after block has image pickers and an accessible caption in its inspector. Replace the two images with genuinely matched views. A featured image overrides the gallery thumbnail. Gallery blocks expose their item count and category-filter setting.

Use **Appearance → Editor** for the header, navigation, footer, templates and global theme styles. The page content is not a flattened image or a proprietary page-builder layout. Keep the enquiry workflow in its dedicated planner block rather than duplicating form fields in ordinary page content.

## Demonstrate the actual workflow

On a phone, complete the planner with fictional details and one non-sensitive image. Save the brief, inspect the illustrative assumptions and reserve a consultation. In a separate authenticated staff session, open NORTHLINE, select that enquiry, review its private reference image and save a note. The client and staff views use the same persisted enquiry.

An enquiry is committed before email is attempted. The outbox shows pending, failed or transport-accepted state. Failed email never means the saved enquiry should be deleted. Staff can retry failed messages. Transport acceptance is not proof of inbox delivery.

## Before accepting real enquiries

Replace the fictional identity and privacy notice with the actual operator's verified information. Confirm the service area and review the sample rate card with a qualified local professional: its numbers are fictional allowances, not researched local pricing. Configure and test a real SMTP/transactional email provider and sending domain. Set the studio notification address in NORTHLINE.

Configure both `NORTHLINE_TURNSTILE_SITE_KEY` and `NORTHLINE_TURNSTILE_SECRET` together to enable the optional challenge. The default timestamp challenge, honeypot and per-connection limits are a baseline, not a claim that targeted spam is impossible.

Default appointments are 45 minutes, Tuesdays and Thursdays at 10:00 and 14:00 in the WordPress site timezone, over a rolling three-week window with at least one day's notice. Developers can change hours with `northline_consultation_hours`. The booking database prevents duplicate slot reservations. There is no Google/Outlook synchronization or external calendar availability check.

Arrange reliable cron execution, backups, monitoring and a staff process for appointments, failed mail and deletion requests. WP-Cron depends on visits unless a server scheduler runs due events. Test the whole flow on the final domain, not only on a preview.

## Privacy operations

Private brief links expire after 30 days. Treat them as confidential bearer credentials. Reference images are decoded and re-encoded to remove metadata, stored privately in the database, and served only through a capability- and nonce-protected staff endpoint. They are not public Media Library uploads.

Enquiries and associated records are removed after 180 days without an update. Staff can permanently erase an enquiry, its references, outbox and booking. WordPress's personal-data exporter/eraser supports brief records; arrange secure delivery of requested reference-image copies before erasure.

## Standalone preview

The browser demo is a separate, clearly labelled demonstration, not a deployed WordPress installation. Enquiries, images and bookings exist only in that browser's local storage. The demo staff desk is intentionally unprotected and sends no real email. Use fictional information. Clearing the demo desk removes its stored records.

Run `python3 -m http.server 8080 --bind 127.0.0.1` from the unpacked preview directory, then open `http://127.0.0.1:8080`. The WordPress ZIPs provide the real server-side implementation.
''')
(root / 'docs/ARCHITECTURE.md').write_text('''# Technical boundaries

The block theme contains presentation, native patterns, templates and progressive enhancements. The focused plugin owns project content types, validation, calculations, private uploads, enquiries, consultation slots, outbox processing and staff access.

A submission uses a UUID idempotency key and a normalized payload fingerprint. A retry of the same payload returns the same enquiry; a different payload using the same key is rejected. Enquiry, estimate snapshot and notification outbox entries are committed in one InnoDB transaction. Images are uploaded afterwards, so a bad image cannot erase a saved brief.

Estimates use affected area × scope rate × finish multiplier. A 10% design allowance and 15% contingency on construction plus design are shown separately. Totals round outwards to CAD 1,000. The declared budget never changes the calculated allowance. Exclusions and the fictional rate-card version are preserved with each enquiry.

Email workers claim due messages using expiring leases and ownership tokens. Failures use exponential retry delays, with five automatic attempts and a manual staff retry. Delivery is at-least-once: a transport acceptance followed by a process crash can cause a duplicate message. No exactly-once email guarantee is made.

Bookings are protected by unique constraints on both slot and enquiry, with the booking and notification records committed transactionally. Appointments are stored in UTC and displayed in the site timezone. A repeated reservation for the same brief and slot is idempotent.

The JSON endpoints validate server-side rather than trusting browser checks. Private bearer credentials are compared through stored hashes and expire. The link carries the token in a fragment, which the consultation page removes from the address bar after storing it in session storage. Enquiry responses are private/no-store. Staff mutations use capabilities and nonces.

Reference ingestion validates MIME and dimensions, restricts JPEG/PNG/WebP, caps size and count, decodes pixels and re-encodes a JPEG. SVG is not accepted. Database storage and backups require access controls appropriate for personal information.

Tests cover domain arithmetic/validation, isolated WordPress persistence and email failure/recovery, booking conflicts, private access, erasure, content preservation, responsive screenshots and a real mobile-to-staff browser flow. The release workflow records actual results and the tested WordPress version under `evidence/`; an included test is not a substitute for a successful result.
''')
(root / 'docs/DESIGN.md').write_text('''# Design handover

Editable Figma file: https://www.figma.com/design/qHhDUg0W0faPhdkVIIeEDO

The file includes a brand-token collection, reusable primary/quiet buttons, desktop and mobile homepage layouts, and the project-planner workspace. Layouts use editable layers rather than a single screenshot. The website uses system Georgia, Arial and Courier; the Figma reference uses the available Libre Baskerville and Inter equivalents.

The visual system combines warm limestone, deep olive, restrained clay accents, fine architectural rules, offset project imagery, annotated concept diagrams and generous editorial typography. Keep the fictional case-study disclosures and editorial photo attribution visible when making design changes.

The original before/after renders are design studies, not a claim to photographic fidelity or completed construction. See ASSETS.md for provenance.
''')
(root / 'README.md').write_text('''# NORTHLINE

A fictional premium design-and-build practice, implemented as a custom WordPress block theme and a focused project-planning plugin.

## What is included

Eight main pages: Home, Studio, Approach, Kitchens, Extensions, Whole-home renovations, Projects and Plan your renovation. Six substantial fictional project case studies include the original problem, design decisions, materials, proposed construction stages and matched before/after visualisations. Consultation and privacy are utility pages outside the eight-page navigation scope.

The six-step mobile planner collects scope, property and affected area, finish, timing, declared budget, priorities, optional private references and contact consent. It produces an assumption-led illustrative allowance, saves the enquiry durably, queues follow-up email independently, and enables a consultation reservation. Authorized staff can review the same brief, images, notes, booking and outbox state.

## Packages

Release assets contain an installable theme ZIP, an installable plugin ZIP, a standalone browser demonstration, the complete source and QA evidence. Start with [the installation and owner handover](docs/HANDOVER.md).

The standalone preview is not a production WordPress host. It stores fictional demo records in that browser and sends no email. A real installation needs hosting, verified operator details, an email provider and appointment operations before accepting real enquiries.

## Design and imagery

[Editable Figma design](https://www.figma.com/design/qHhDUg0W0faPhdkVIIeEDO). The homepage's editorial photograph is distinguished from the original Blender-generated case-study visualisations. No client work, awards or testimonials are invented. See [asset provenance](docs/ASSETS.md) and [design handover](docs/DESIGN.md).

## Development and verification

```sh
php tests/domain.php
php tools/build-preview.php
python3 tools/package.py
```

The WordPress integration test requires `NORTHLINE_TESTING=1` and an isolated database. Never run it against client data. Browser acceptance tests use Playwright and isolated WordPress/preview servers. CI records test output, screenshots and the actual WordPress version in `evidence/`.

See [architecture and operational boundaries](docs/ARCHITECTURE.md). Sample budget figures are fictional planning allowances, not market quotations. No real outbound email or third-party calendar synchronization is claimed by the automated test fixtures.
''')
print('Editorial photo, portable preview, editor controls and handover documentation prepared.')
