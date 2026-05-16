# WM Posts Blocks

A small, production-style WordPress plugin that ships two custom Gutenberg blocks — a dynamic **Posts Grid** and a companion **Posts Filter** — wired together via a clean, decoupled event contract. All demo content is seeded automatically on activation; no manual setup is required.

Built as a technical assessment for a **WordPress Web Development Team Lead** role.

**Author**: Itay Haephrati &nbsp;·&nbsp; [itaycode.com](https://itaycode.com)
**Repository**: [github.com/itay1313/wm-pi](https://github.com/itay1313/wm-pi)

---

## Table of contents

1. [Demo & screenshots](#demo--screenshots)
2. [Quick start](#quick-start)
3. [What's in the box](#whats-in-the-box)
4. [Architectural decisions](#architectural-decisions)
5. [Inter-block communication — deep dive](#inter-block-communication--deep-dive)
6. [Demo content seeding](#demo-content-seeding)
7. [Project layout](#project-layout)
8. [Coding standards](#coding-standards)
9. [Verification checklist](#verification-checklist)
10. [Known limitations & tradeoffs](#known-limitations--tradeoffs)
11. [What I would do next](#what-i-would-do-next)
12. [License](#license)

---

## Demo & screenshots

After running the quick-start commands below, visit:

- **Demo page (frontend)**: <http://localhost:8888/?page_id=28> — or any page with the slug `wm-posts-demo`
- **Admin (block editor)**: <http://localhost:8888/wp-admin/post.php?post=28&action=edit>
- **REST endpoint**: <http://localhost:8888/wp-json/wm/v1/posts?columns=3&per_page=6>

Login: `admin` / `password`

---

## Quick start

### Prerequisites

| Tool | Version | Notes |
|---|---|---|
| Node.js | ≥ 18 | Tested on Node 24 |
| npm | ≥ 9 | Comes with Node |
| Docker Desktop | latest | Must be running before `npm run env start` |

> **macOS note**: If `docker --version` returns *command not found* after installing Docker Desktop, open Docker Desktop once and accept the prompt to install CLI tools — or symlink manually: `sudo ln -s /Applications/Docker.app/Contents/Resources/bin/docker /usr/local/bin/docker`.

### Setup (4 commands)

```bash
git clone https://github.com/itay1313/wm-pi.git
cd wm-pi
npm install
npm run build
npm run env start
```

The first `wp-env start` takes 2–5 minutes (Docker pulls WordPress + MySQL images). Subsequent starts are seconds.

When it finishes:

| URL | Purpose |
|---|---|
| <http://localhost:8888> | Frontend |
| <http://localhost:8888/wp-admin> | Admin (`admin` / `password`) |
| <http://localhost:8888/?page_id=28> | The pre-built demo page |

The plugin is **already active** (it's listed in `.wp-env.json`) and demo content is **already seeded**.

### Day-to-day commands

```bash
npm run start        # Webpack watch mode (auto-rebuild on JS/SCSS changes)
npm run build        # Production build of all blocks
npm run lint:js      # ESLint via @wordpress/scripts
npm run format       # Prettier
npm run env stop     # Stop containers (preserves DB)
npm run env destroy  # Wipe containers + DB
```

### Re-seeding from scratch

Seeding is idempotent — deactivating and reactivating the plugin will **not** create duplicate posts. To force a clean re-seed:

```bash
npx wp-env run cli wp option delete wm_pb_seeded
# then deactivate + reactivate the plugin in wp-admin
```

---

## What's in the box

### Three Gutenberg blocks

| Block | Type | Responsibility |
|---|---|---|
| `wm/posts-grid` | Dynamic | Grid of posts with configurable columns (2 / 3 / 4) and posts-per-page. Renders server-side; refreshes via REST when filters change. |
| `wm/posts-pagination` | Dynamic, inner block | Pagination control. Locked to live inside `wm/posts-grid` via the `ancestor` field. |
| `wm/posts-filter` | Dynamic | Frontend UI with category + tag checkboxes. Broadcasts filter changes as `window` events. |

### Seeded demo content

Created automatically when the plugin is first activated:

- **4 categories**: Product News · Tutorials · Case Studies · Opinion
- **8 tags**: WordPress · React · PHP · JavaScript · CSS · Performance · Accessibility · DevOps
- **12 posts** — deterministic but overlapping category/tag assignments, so every realistic filter combination returns a meaningful subset (never empty, never identical to "all posts")
- **Featured images** — locally generated colored SVGs, one per post (no network dependency)
- **1 demo page** — `/wm-posts-demo/`, with both blocks pre-placed and wired

All seeded items are tagged with `_wm_demo = 1` (post-meta and term-meta) so `uninstall.php` can remove them cleanly without ever touching user content.

---

## Architectural decisions

A short, explicit account of the calls I made and why.

### 1. Three blocks, one server-side renderer

Both the initial server-render and the AJAX refresh path call the same PHP method — `Wm_PB_Renderer::render_grid()`. This means the markup can never drift between the two render paths. There is no client-side templating engine. The frontend payload remains small, accessible on first paint, and SEO-friendly.

The cost is that REST responses carry HTML instead of JSON — perhaps 2–3× the size. For a posts grid this is irrelevant; for a 1000-item SaaS dashboard it would not be.

### 2. Pagination as a true inner block

The pagination is its own registered block (`wm/posts-pagination`) with `ancestor: ["wm/posts-grid"]` in its `block.json`. Gutenberg enforces this — you cannot insert the pagination block anywhere except inside a Posts Grid. The grid uses `InnerBlocks` with `templateLock: "all"` and a fixed template so pagination is always present whenever a grid is inserted.

On the frontend the grid's PHP render embeds pagination markup directly, rather than running the inner block's own render callback. This keeps pagination state (current page, total pages) co-located with the query that produced it. Otherwise the inner block would need to introspect its parent's query — awkward in WordPress's block-rendering model.

### 3. Stable, per-instance `queryId`

Each Posts Grid block gets a short, stable `queryId` (e.g. `q-1a2b3c4d5e`) derived from its Gutenberg `clientId` on first render and persisted into attributes. The filter can optionally target a specific `queryId` (controls just that grid); if left blank, it broadcasts to every grid on the page. This makes multi-grid layouts work without configuration.

### 4. SVG-based demo images

`media_sideload_image` from a public CDN is the obvious path for demo featured images, but it requires network access and is non-deterministic across machines. I generate small SVG attachments locally instead — ~300 bytes each, identical on every activation, work offline, and look intentional rather than placeholder-y.

The SVG MIME type is enabled temporarily (via an `upload_mimes` filter that is added and removed in the same call site) only for the activator's own attachment inserts. The plugin never permanently weakens upload security.

### 5. Idempotent activation

A `wm_pb_seeded` option guards the seeding routine, so deactivate → reactivate is a no-op. Every seeded entity carries a `_wm_demo` marker meta. On uninstall, `uninstall.php` deletes everything bearing that marker and nothing else. User-created content is untouched, even if it shares a title or slug with seeded content.

---

## Inter-block communication — deep dive

The brief explicitly disallows nesting the filter inside the grid: *"the two blocks can be placed independently anywhere on the same page and should still work together."* That ruled out the easiest option and forced a real design decision.

### The chosen approach: custom DOM events + REST

```
                         ┌──────────────────────────────┐
   user checks a box ──▶ │ Posts Filter (view.js)       │
                         │  - collects selected IDs     │
                         │  - dispatches CustomEvent on │
                         │    window:                   │
                         │    'wm:filter-change'    │
                         └──────────────┬───────────────┘
                                        │
                                        │  detail: { categories, tags, targetQueryId }
                                        ▼
                         ┌──────────────────────────────┐
                         │ Posts Grid (view.js)         │
                         │  listens on window for       │
                         │  'wm:filter-change'      │
                         └──────────────┬───────────────┘
                                        │
                                        │  fetch('/wp-json/wm/v1/posts?...')
                                        ▼
                         ┌──────────────────────────────┐
                         │ REST endpoint (PHP)          │
                         │  Wm_PB_Renderer::        │
                         │    render_grid($attrs)       │
                         │  → returns HTML              │
                         └──────────────┬───────────────┘
                                        │
                                        ▼  swap innerHTML, sync dataset
                                  Grid is updated.
```

**Filter logic** (handled in PHP, not JS):
- **OR within a filter type** — `tax_query` with `'operator' => 'IN'` over the selected term IDs.
- **AND across filter types** — `tax_query` outer `'relation' => 'AND'` joining the category clause and the tag clause.

### Alternatives considered

| Approach | Why not chosen |
|---|---|
| **Nest the filter inside the grid (InnerBlocks)** | Explicitly disallowed by the brief. Also breaks the "one filter, multiple grids" pattern. |
| **WordPress Interactivity API** | The "right" answer for new code in 2025+. Skipped because (a) it adds real cognitive overhead, (b) the brief doesn't require it, and (c) the custom-event approach demonstrates the same architectural insight — server-rendered HTML, no template duplication — with less coupling to a still-evolving framework. The upgrade path is straightforward. |
| **Redux / `@wordpress/data` store** | One slice of state, one consumer. Adding a global store would be ceremony, not architecture. |
| **URL query string as source of truth** | Tempting (shareable filtered URLs, browser back button). Breaks the "multiple grids on one page" requirement — a single URL can't address multiple independent grids — and forces page reloads or aggressive `history.pushState` plumbing. |
| **JS-only filtering on already-rendered DOM** | Cannot honor `posts_per_page` correctly, cannot paginate, and fights the dynamic-block model. |

### Why HTML over JSON

The REST endpoint returns rendered HTML rather than post objects. Reasons:

- One template, one place. Markup can never drift between initial SSR and AJAX refresh.
- No client-side templating engine, no React-on-the-frontend, no Handlebars.
- Identical accessibility surface on initial load and after refresh.

For a posts grid this is the right call. For a large data dashboard, JSON + client templating would win on payload size.

---

## Demo content seeding

The activator (`includes/class-activator.php`) does the following, in order:

1. Bail early if `wm_pb_seeded` option is already set.
2. Create categories (`Product News`, `Tutorials`, `Case Studies`, `Opinion`), tagging each term with `_wm_demo = 1`.
3. Create tags (8 of them), same marker.
4. For each of 12 hard-coded post titles, in order:
   - Skip if a post with the same slug already exists.
   - Insert the post (published, dated descending so the latest is "today").
   - Assign categories using a deterministic rotation — guarantees overlap.
   - Assign 2–4 tags using a separate rotation — guarantees richness.
   - Generate a colored SVG with the post's initials, register it as an attachment, set it as the featured image.
   - Stamp `_wm_demo = 1` on the post and the attachment.
5. Create a demo page with both blocks pre-placed and a matching `queryId` / `targetQueryId` so they're wired together out of the box.
6. Set `wm_pb_seeded = 1`.

Seeded entities total: 4 terms + 8 terms + 12 posts + 12 attachments + 1 page = **37 demo items**, all tagged for clean removal on uninstall.

---

## Project layout

```
wm-posts-blocks/
├── wm-posts-blocks.php        Plugin bootstrap, block registration, hook wiring
├── uninstall.php                  Demo cleanup on plugin delete
├── .wp-env.json                   wp-env (Docker) config — plugin auto-activates
├── package.json                   Dev dependencies, npm scripts
├── README.md                      This file
│
├── includes/
│   ├── helpers.php                Input sanitization (term IDs, column clamping)
│   ├── class-renderer.php         Shared SSR for all three blocks
│   ├── class-rest-api.php         Registers /wp-json/wm/v1/posts
│   └── class-activator.php        Idempotent demo seeding
│
├── src/
│   ├── posts-grid/
│   │   ├── block.json             Metadata, attributes, asset wiring
│   │   ├── index.js               registerBlockType
│   │   ├── edit.js                InspectorControls + ServerSideRender preview
│   │   ├── save.js                InnerBlocks.Content (persists pagination)
│   │   ├── view.js                Frontend: listens for filter events, AJAX refresh
│   │   └── style.scss             Grid layout + pagination styles
│   │
│   ├── posts-pagination/
│   │   ├── block.json             ancestor: ["wm/posts-grid"]
│   │   └── index.js               Editor placeholder; PHP renders on frontend
│   │
│   └── posts-filter/
│       ├── block.json
│       ├── index.js               InspectorControls (targetQueryId)
│       ├── view.js                Frontend: dispatches CustomEvent on change
│       └── style.scss
│
└── build/                         Generated by @wordpress/scripts (gitignored)
```

---

## Coding standards

- **PHP** — WordPress Coding Standards conventions: tab indentation, `esc_*` on every output, `wp_kses_post` for rich HTML, prepared statements via `$wpdb->prepare`, capability/permission checks declared explicitly even when the endpoint is intentionally public.
- **JavaScript** — `@wordpress/scripts` ESLint config (`npm run lint:js`). No external React libraries; only `@wordpress/*` packages.
- **CSS / SCSS** — BEM naming under a single `wm-posts-*` namespace. No global selectors.
- **i18n** — every user-facing string is wrapped with `__()` / `_e()` / `esc_html__()` under the `wm-posts-blocks` text domain.
- **Security** — `esc_attr` on every dynamic attribute, `esc_html` on every dynamic text node, `wp_kses_post` on rich HTML. The REST endpoint returns SSR output that has already been escaped during PHP rendering.

---

## Verification checklist

A quick end-to-end smoke test you can run after `npm run env start`:

1. **Admin loads** — visit <http://localhost:8888/wp-admin> and log in (`admin` / `password`).
2. **Plugin is active** — Plugins screen shows "WM Posts Blocks" enabled.
3. **Seeding ran** — Posts screen lists 12 demo posts, each with a colored featured image and at least one category + one tag.
4. **Demo page exists** — Pages screen lists "WM Posts Demo". Open it in the block editor; the filter block is on top, the grid + pagination below.
5. **Inspector controls work** — click the grid, open the right sidebar, change Columns to 4 and Posts per page to 4. The editor preview updates.
6. **Frontend renders** — view the demo page. You should see 6 posts in a 3-column grid with pagination at the bottom.
7. **Filter — OR within type** — check two categories; the grid refreshes via AJAX to show posts in either category.
8. **Filter — AND across types** — keep the categories checked, add a tag; the grid narrows to posts that match the categories AND at least the selected tag.
9. **Pagination** — click "Next" with filters active; the page changes, filters are preserved.
10. **Multiple grids** — add a second Posts Grid block on a fresh page with the same `queryId` (or a unique one) and verify the filter targets the intended grid.
11. **Reactivation is safe** — deactivate the plugin, reactivate it; no duplicate posts appear.
12. **Uninstall is clean** — delete the plugin via the Plugins screen; the 12 demo posts, page, attachments, categories, and tags are removed. Any user-created content remains.

### Programmatic smoke test

```bash
# Number of posts matching a category (Tutorials, term_id 3)
curl -s "http://localhost:8888/wp-json/wm/v1/posts?per_page=20&categories=3" \
  | python3 -c "import json,sys,re; h=json.load(sys.stdin)['html']; print(len(re.findall(r'wm-posts-grid__link', h)))"

# Category AND tag (Tutorials AND React)
curl -s "http://localhost:8888/wp-json/wm/v1/posts?per_page=20&categories=3&tags=7" \
  | python3 -c "import json,sys,re; h=json.load(sys.stdin)['html']; print(len(re.findall(r'wm-posts-grid__link', h)))"
```

---

## Known limitations & tradeoffs

These are documented honestly. If any matter for the production version, they're 5–30 minutes to address.

- **No nonce on the REST endpoint.** Intentional — it's a public-read endpoint over already-public posts. If the underlying query ever returned private posts, you'd add `permission_callback` + `current_user_can`.
- **No caching layer.** Every filter change hits the database. For a high-traffic site I'd wrap the renderer in `wp_cache_*` keyed by a hash of the query args, or use a Transient with a sensible TTL and term-change invalidation.
- **Filter UI fetches all terms with `hide_empty => true` at render time.** Fine for the demo's 4 categories and 8 tags; for a site with thousands of terms you'd swap to a typeahead or paginated taxonomy picker.
- **No "Apply" button — filters refetch immediately on change.** Trivial to debounce, but adds latency to the perceived UX. The current behavior feels more responsive on a small dataset.
- **Pagination block has no own attributes** — no variant selector, label customization, or ellipsis style. Easy to add; the brief didn't request it.
- **Editor preview uses `ServerSideRender`** — convenient, but issues an extra HTTP roundtrip per attribute change. For a polished production block I'd debounce or render a JS-side preview from the same data shape the server returns.
- **SVG demo images** — fine for local dev. Production sites typically disallow SVG uploads; the activator scopes the MIME exception narrowly, but it's still a temporary capability widening.
- **Filter targets a queryId, not a block UUID.** Two grids sharing a queryId will both receive updates — by design (so a filter can address a group). The brief doesn't require uniqueness; if it did, I'd swap to UUID.

---

## What I would do next

If this were a production plugin and not a one-week assessment, the priority list:

1. **Migrate the view scripts to the Interactivity API.** The architecture maps cleanly; it's mostly a syntax change. Buys SSR-friendly directives and a clear pattern for future state.
2. **Cache the REST responses.** Transient with a hashed args key, invalidated on `save_post` / `created_term` / `deleted_term`.
3. **Move category + tag pickers to a typeahead component** for taxonomies that exceed a few dozen terms.
4. **E2E tests with Playwright** via `@wordpress/e2e-test-utils-playwright`. Cover the verification checklist above.
5. **Unit tests for the PHP renderer** with the WordPress core test suite (PHPUnit).
6. **Variations / patterns** so editors can drop a "Posts Grid + Filter" pre-wired pattern from the inserter.

---

## License

GPL-2.0-or-later, matching WordPress core.

---

## About the author

**Itay Haephrati** — engineer and product builder.
Web: [itaycode.com](https://itaycode.com) · GitHub: [@itay1313](https://github.com/itay1313)
