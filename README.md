<div align="center">

# WM Posts Blocks

### Production-style WordPress plugin shipping two custom Gutenberg blocks with a premium, decoupled UI.

A dynamic **Posts Grid** and a companion **Posts Filter** — wired together via a clean event contract, polished with a dark cinematic sidebar layout, and seeded with demo content on activation. Zero manual setup.

[![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Block API v3](https://img.shields.io/badge/Block%20API-v3-0073aa)](https://developer.wordpress.org/block-editor/reference-guides/block-api/)
[![Built with @wordpress/scripts](https://img.shields.io/badge/built%20with-%40wordpress%2Fscripts-21759b)](https://www.npmjs.com/package/@wordpress/scripts)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

[**Itay Haephrati**](https://itaycode.com) &nbsp;·&nbsp; [itaycode.com](https://itaycode.com) &nbsp;·&nbsp; [GitHub](https://github.com/itay1313/wm-pi)

</div>

---

## ✨ Highlights

- **Dynamic Posts Grid** — server-rendered via `WP_Query`. Configurable columns (2 / 3 / 4) and posts per page via Inspector Controls. Refreshes in place via REST when filters change.
- **Posts Filter** — sidebar + topbar layout inspired by modern component libraries. Categories live in a sticky left rail with icons and counts; tags render as accent-glowing chips; search is debounced.
- **Pagination as a true inner block** — registered separately with `ancestor: ["wm/posts-grid"]`, so Gutenberg enforces correct nesting at the API level.
- **Decoupled inter-block communication** — `window` custom events, queryId routing, no shared state, no Redux. The two blocks can sit anywhere on the page (even multiple grids) and stay in sync.
- **One server template, two render paths** — the same PHP renderer feeds the initial SSR and the AJAX refresh, eliminating template drift by design.
- **Idempotent activation** — seeds 4 categories, 8 tags, 12 posts, 12 SVG featured images, and a fully-wired demo page. Re-activation is a no-op.
- **Honest uninstall** — `uninstall.php` removes only items tagged with `_wm_demo`. User content is never touched.

---

## 📐 Layout

The demo page is built on a CSS Grid shell. The filter and grid blocks remain independent siblings — the filter uses `display: contents` so its inner sidebar and topbar land directly in the parent grid areas.

```
┌──────────┬────────────────────────────────────────────────────┐
│          │  #tag  #tag  #tag  …            [Clear]  🔎 Search │
│  Browse  ├────────────────────────────────────────────────────┤
│  • All   │  ┌────────┐  ┌────────┐  ┌────────┐                │
│  • Cat 1 │  │  post  │  │  post  │  │  post  │                │
│  • Cat 2 │  └────────┘  └────────┘  └────────┘                │
│  • Cat 3 │  ┌────────┐  ┌────────┐  ┌────────┐                │
│   ⋮      │  │  post  │  │  post  │  │  post  │                │
│          │  └────────┘  └────────┘  └────────┘                │
│          │                ‹  1  2  3  ›                       │
└──────────┴────────────────────────────────────────────────────┘
   sidebar              topbar (row 1)  +  main grid (row 2)
```

---

## 🚀 Quick start

### Prerequisites

| Tool | Version | Notes |
|---|---|---|
| Node.js | ≥ 18 | Tested on Node 24 |
| npm | ≥ 9 | Comes with Node |
| Docker Desktop | latest | Must be running before `npm run env start` |

> **macOS note**: If `docker --version` returns *command not found* after installing Docker Desktop, open Docker Desktop once and accept the prompt to install CLI tools — or symlink manually: `sudo ln -s /Applications/Docker.app/Contents/Resources/bin/docker /usr/local/bin/docker`.

### Setup in four commands

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
| <http://localhost:8888/wm-posts-demo/> | The pre-built demo page (filter + grid) |
| <http://localhost:8888/wp-admin> | Admin (`admin` / `password`) |
| <http://localhost:8888/wp-json/wm/v1/posts> | The REST endpoint powering the AJAX refresh |

The plugin is **already active** (declared in `.wp-env.json`) and demo content is **already seeded**.

### Daily workflow

```bash
npm run start        # Webpack watch — auto-rebuild on JS/SCSS changes
npm run build        # Production build of all three blocks
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

## 📦 What's in the box

### Three Gutenberg blocks

| Block | Type | Responsibility |
|---|---|---|
| `wm/posts-grid` | Dynamic | Grid of posts with configurable columns (2 / 3 / 4) and posts-per-page. Renders server-side; refreshes via REST when filters change. |
| `wm/posts-pagination` | Dynamic, inner block | Pagination control. Locked to live inside `wm/posts-grid` via the `ancestor` field. |
| `wm/posts-filter` | Dynamic | Sidebar + topbar UI: categories list, tag chips, debounced search, clear-all. Broadcasts filter changes as `window` events. |

### Seeded demo content

Created automatically when the plugin is first activated:

- **4 categories** — Product News · Tutorials · Case Studies · Opinion
- **8 tags** — WordPress · React · PHP · JavaScript · CSS · Performance · Accessibility · DevOps
- **12 posts** — deterministic but overlapping category/tag assignments, so every realistic filter combination returns a meaningful subset (never empty, never identical to "all posts").
- **12 featured images** — locally generated colored SVGs, one per post, no network dependency.
- **1 demo page** — `/wm-posts-demo/`, with both blocks pre-placed inside a `wm-app-shell` Group block.

All seeded items are stamped with `_wm_demo = 1` (post-meta and term-meta) so `uninstall.php` can remove them cleanly without ever touching user content.

---

## 🏛️ Architectural decisions

A short, explicit account of the calls I made and why.

### 1. Three blocks, one server-side renderer

Both the initial server-render and the AJAX refresh path call the same PHP method — `Wm_PB_Renderer::render_grid()`. This means the markup can never drift between the two render paths. There is no client-side templating engine. The frontend payload remains small, accessible on first paint, and SEO-friendly.

The cost is that REST responses carry HTML instead of JSON — perhaps 2–3× the size. For a posts grid this is irrelevant; for a 1000-item SaaS dashboard it would not be.

### 2. Pagination as a true inner block

The pagination is its own registered block (`wm/posts-pagination`) with `ancestor: ["wm/posts-grid"]` in its `block.json`. Gutenberg enforces this — you cannot insert the pagination block anywhere except inside a Posts Grid. The grid uses `InnerBlocks` with `templateLock: "all"` and a fixed template so pagination is always present whenever a grid is inserted.

On the frontend the grid's PHP render embeds pagination markup directly, rather than running the inner block's own render callback. This keeps pagination state (current page, total pages) co-located with the query that produced it. Otherwise the inner block would need to introspect its parent's query — awkward in WordPress's block-rendering model.

### 3. Stable, per-instance `queryId`

Each Posts Grid block gets a short, stable `queryId` (e.g. `q-1a2b3c4d5e`) derived from its Gutenberg `clientId` on first render and persisted into attributes. The filter can optionally target a specific `queryId` (controls just that grid); if left blank, it broadcasts to every grid on the page. This makes multi-grid layouts work without configuration.

### 4. CSS Grid shell with `display: contents`

The brief requires that filter and grid remain independent siblings — so I can't nest one inside the other. To still produce a magazine-style sidebar + topbar / main layout, the filter block renders two sibling elements (sidebar + topbar) inside its own wrapper, and the wrapper uses `display: contents`. This pulls the sidebar and topbar into the parent `.wm-app-shell` grid as if they were direct children, letting `grid-template-areas` arrange them around the grid block.

Result: the blocks stay independent and editable individually, but the page renders as a single cohesive shell.

### 5. SVG-based demo images

`media_sideload_image` from a public CDN is the obvious path for demo featured images, but it requires network access and is non-deterministic across machines. I generate small SVG attachments locally instead — ~300 bytes each, identical on every activation, work offline, and look intentional rather than placeholder-y.

The SVG MIME type is enabled temporarily (via an `upload_mimes` filter that is added and removed in the same call site) only for the activator's own attachment inserts. The plugin never permanently weakens upload security.

### 6. Idempotent activation, honest uninstall

A `wm_pb_seeded` option guards the seeding routine, so deactivate → reactivate is a no-op. Every seeded entity carries a `_wm_demo` marker meta. On uninstall, `uninstall.php` deletes everything bearing that marker and nothing else. User-created content is untouched, even if it shares a title or slug with seeded content.

---

## 🔌 Inter-block communication — deep dive

The brief explicitly disallows nesting the filter inside the grid: *"the two blocks can be placed independently anywhere on the same page and should still work together."* That ruled out the easiest option and forced a real design decision.

### The chosen approach: custom DOM events + REST

```
                         ┌──────────────────────────────┐
   user checks a box ──▶ │ Posts Filter (view.js)       │
   user types in search  │  - collects selected IDs     │
                         │  - dispatches CustomEvent on │
                         │    window:                   │
                         │    'wm:filter-change'        │
                         └──────────────┬───────────────┘
                                        │
                              detail: { categories, tags,
                                        search, targetQueryId }
                                        ▼
                         ┌──────────────────────────────┐
                         │ Posts Grid (view.js)         │
                         │  listens on window for       │
                         │  'wm:filter-change'          │
                         └──────────────┬───────────────┘
                                        │
                                        │  fetch('/wp-json/wm/v1/posts?...')
                                        ▼
                         ┌──────────────────────────────┐
                         │ REST endpoint (PHP)          │
                         │  Wm_PB_Renderer::            │
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
- **Search** — passed through as `WP_Query`'s native `s` parameter.

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

## 🌱 Demo content seeding

The activator (`includes/class-activator.php`) does the following, in order:

1. Bail early if `wm_pb_seeded` option is already set.
2. Create categories (`Product News`, `Tutorials`, `Case Studies`, `Opinion`), tagging each term with `_wm_demo = 1`.
3. Create tags (8 of them), same marker.
4. For each of 12 hard-coded post titles:
   - Skip if a post with the same slug already exists.
   - Insert the post (published, dated descending so the latest is "today").
   - Assign categories using a deterministic rotation — guarantees overlap.
   - Assign 2–4 tags using a separate rotation — guarantees richness.
   - Generate a colored SVG with the post's initials, register it as an attachment, set it as the featured image.
   - Stamp `_wm_demo = 1` on the post and the attachment.
5. Create a demo page with both blocks pre-placed inside a Group block carrying the `wm-app-shell` class.
6. Set `wm_pb_seeded = 1`.

Seeded entities total: 4 terms + 8 terms + 12 posts + 12 attachments + 1 page = **37 demo items**, all tagged for clean removal on uninstall.

---

## 🗂️ Project layout

```
wm-posts-blocks/
├── wm-posts-blocks.php           Plugin bootstrap, block registration, hook wiring
├── uninstall.php                 Demo cleanup on plugin delete
├── .wp-env.json                  wp-env (Docker) config — plugin auto-activates
├── package.json                  Dev dependencies, npm scripts
├── README.md                     This file
│
├── includes/
│   ├── helpers.php               Input sanitization (term IDs, column clamping)
│   ├── class-renderer.php        Shared SSR for all three blocks
│   ├── class-rest-api.php        Registers /wp-json/wm/v1/posts
│   └── class-activator.php       Idempotent demo seeding
│
├── src/
│   ├── posts-grid/
│   │   ├── block.json            Metadata, attributes, asset wiring
│   │   ├── index.js              registerBlockType
│   │   ├── edit.js               InspectorControls + ServerSideRender preview
│   │   ├── save.js               InnerBlocks.Content (persists pagination)
│   │   ├── view.js               Frontend: listens for filter events, AJAX refresh
│   │   └── style.scss            Modern dark card styling + pagination
│   │
│   ├── posts-pagination/
│   │   ├── block.json            ancestor: ["wm/posts-grid"]
│   │   └── index.js              Editor placeholder; PHP renders on frontend
│   │
│   └── posts-filter/
│       ├── block.json
│       ├── index.js              InspectorControls (targetQueryId)
│       ├── view.js               Frontend: collects selections, dispatches events
│       └── style.scss            Sidebar + topbar shell, chips, search, accent glow
│
└── build/                        Generated by @wordpress/scripts (gitignored)
```

---

## ✅ Coding standards

- **PHP** — WordPress Coding Standards conventions: tab indentation, `esc_*` on every output, `wp_kses_post` for rich HTML, prepared statements via `$wpdb->prepare`, capability/permission checks declared explicitly even when the endpoint is intentionally public.
- **JavaScript** — `@wordpress/scripts` ESLint config (`npm run lint:js`). No external React libraries; only `@wordpress/*` packages.
- **CSS / SCSS** — BEM naming under a single `wm-posts-*` namespace. All visual tokens (colors, radii, spacing) live as CSS custom properties scoped to `.wm-app-shell` for easy theming.
- **i18n** — every user-facing string is wrapped with `__()` / `_e()` / `esc_html__()` under the `wm-posts-blocks` text domain.
- **Security** — `esc_attr` on every dynamic attribute, `esc_html` on every dynamic text node, `wp_kses_post` on rich HTML. The REST endpoint returns SSR output that has already been escaped during PHP rendering.

---

## 🧪 Verification checklist

A quick end-to-end smoke test you can run after `npm run env start`:

1. **Admin loads** — visit <http://localhost:8888/wp-admin> and log in (`admin` / `password`).
2. **Plugin is active** — Plugins screen shows "WM Posts Blocks" enabled, attributed to *Itay Haephrati* with itaycode.com linked.
3. **Seeding ran** — Posts screen lists 12 demo posts, each with a colored featured image and at least one category + one tag.
4. **Demo page exists** — Pages screen lists "WM Posts Demo". Open it in the block editor; the filter block sits at the top of a `wm-app-shell` Group, with the grid + pagination below it.
5. **Inspector controls work** — click the grid, open the right sidebar, change Columns to 4 and Posts per page to 4. The editor preview updates.
6. **Frontend renders** — view the demo page at `/wm-posts-demo/`. You should see a left sidebar with categories, a topbar with tag chips + search, and a 3-column grid of cards.
7. **Filter — OR within type** — check two categories; the grid refreshes via AJAX to show posts in either category.
8. **Filter — AND across types** — keep the categories checked, add a tag chip; the grid narrows to posts that match the categories AND at least the selected tag.
9. **Search** — type into the search input; the grid refreshes 250 ms after you stop typing.
10. **Pagination** — click "Next" with filters active; the page changes, filters are preserved.
11. **Multiple grids** — add a second Posts Grid block on a fresh page with the same `queryId` (or a unique one) and verify the filter targets the intended grid.
12. **Reactivation is safe** — deactivate the plugin, reactivate it; no duplicate posts appear.
13. **Uninstall is clean** — delete the plugin via the Plugins screen; the 12 demo posts, page, attachments, categories, and tags are removed. Any user-created content remains.

### Programmatic smoke test

```bash
# Number of posts matching a category (Tutorials, term_id 3)
curl -s "http://localhost:8888/wp-json/wm/v1/posts?per_page=20&categories=3" \
  | python3 -c "import json,sys,re; d=json.loads(sys.stdin.read(),strict=False); print(len(re.findall(r'wm-posts-grid__link', d['html'])))"

# Category AND tag (Tutorials AND React)
curl -s "http://localhost:8888/wp-json/wm/v1/posts?per_page=20&categories=3&tags=7" \
  | python3 -c "import json,sys,re; d=json.loads(sys.stdin.read(),strict=False); print(len(re.findall(r'wm-posts-grid__link', d['html'])))"

# Search
curl -s "http://localhost:8888/wp-json/wm/v1/posts?per_page=20&search=react" \
  | python3 -c "import json,sys,re; d=json.loads(sys.stdin.read(),strict=False); print(len(re.findall(r'wm-posts-grid__link', d['html'])))"
```

---

## ⚖️ Known limitations & tradeoffs

These are documented honestly. If any matter for the production version, they're 5–30 minutes to address.

- **No nonce on the REST endpoint.** Intentional — it's a public-read endpoint over already-public posts. If the underlying query ever returned private posts, you'd add `permission_callback` + `current_user_can`.
- **No caching layer.** Every filter change hits the database. For a high-traffic site I'd wrap the renderer in `wp_cache_*` keyed by a hash of the query args, or use a Transient with a sensible TTL and term-change invalidation.
- **Filter UI fetches all terms with `hide_empty => true` at render time.** Fine for the demo's 4 categories and 8 tags; for a site with thousands of terms you'd swap to a typeahead or paginated taxonomy picker.
- **No "Apply" button — filters refetch immediately on change.** Search is debounced 250 ms; checkbox changes are instant. Trivial to add a manual apply if needed.
- **Pagination block has no own attributes** — no variant selector, label customization, or ellipsis style. Easy to add; the brief didn't request it.
- **Editor preview uses `ServerSideRender`** — convenient, but issues an extra HTTP roundtrip per attribute change. For a polished production block I'd debounce or render a JS-side preview from the same data shape the server returns.
- **SVG demo images** — fine for local dev. Production sites typically disallow SVG uploads; the activator scopes the MIME exception narrowly, but it's still a temporary capability widening.
- **`display: contents` accessibility** — modern browsers correctly preserve semantics through `display: contents` for the elements used here (divs, asides, headers). Verified in Chrome/Safari/Firefox 2024+. If supporting older Firefox were a hard requirement, the layout would fall back to a flex column.

---

## 🛣️ What I would do next

If this were a production plugin and not a one-week assessment, the priority list:

1. **Migrate the view scripts to the Interactivity API.** The architecture maps cleanly; it's mostly a syntax change. Buys SSR-friendly directives and a clear pattern for future state.
2. **Cache the REST responses.** Transient with a hashed args key, invalidated on `save_post` / `created_term` / `deleted_term`.
3. **Sort controls** — newest, oldest, most commented — exposed via Inspector Controls and reflected in `WP_Query` `orderby`.
4. **Move category + tag pickers to a typeahead component** for taxonomies that exceed a few dozen terms.
5. **E2E tests with Playwright** via `@wordpress/e2e-test-utils-playwright`. Cover the verification checklist above.
6. **Unit tests for the PHP renderer** with the WordPress core test suite (PHPUnit).
7. **Block variations / patterns** so editors can drop a "Posts Grid + Filter" pre-wired pattern from the inserter.
8. **Light theme toggle** — the dark shell is opinionated; a CSS-variable-only swap to light mode is trivial.

---

## 📄 License

GPL-2.0-or-later, matching WordPress core.

---

<div align="center">

### About the author

**Itay Haephrati** — engineer and product builder.

[🌐 itaycode.com](https://itaycode.com) &nbsp;·&nbsp; [💻 github.com/itay1313](https://github.com/itay1313)

*Built as a technical assessment.*

</div>
