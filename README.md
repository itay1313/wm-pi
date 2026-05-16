# WalkMe Posts Blocks

A WordPress plugin that registers two Gutenberg blocks — a dynamic **Posts Grid** and a companion **Posts Filter** — and seeds all demo content automatically on activation.

Built for the WalkMe Web Development Team Lead technical assessment.

---

## Quick start

### Prerequisites
- Node.js ≥ 18
- Docker Desktop (running)
- npm

### Setup
```bash
# 1. From inside the plugin directory
npm install

# 2. Build the blocks
npm run build

# 3. Start WordPress (wp-env spins up Docker containers)
npm run env start
```

Then open:
- Site: http://localhost:8888
- Admin: http://localhost:8888/wp-admin (user: `admin`, pass: `password`)

The plugin auto-activates and seeds demo content. Visit **Pages → WalkMe Posts Demo** to see both blocks in action, or visit the page directly: http://localhost:8888/?page_id=… (the slug is `walkme-posts-demo`).

### Other commands
```bash
npm run start         # webpack watch mode for development
npm run env stop      # stop containers (preserves data)
npm run env destroy   # destroy containers (wipes DB)
npm run lint:js
npm run format
```

---

## What's in the box

### Blocks
| Block | Type | Purpose |
|---|---|---|
| `walkme/posts-grid` | Dynamic | Grid of posts, configurable columns (2/3/4) + posts per page. Renders server-side. |
| `walkme/posts-pagination` | Dynamic, inner block | Pagination control. Constrained to `walkme/posts-grid` via the `ancestor` field. |
| `walkme/posts-filter` | Dynamic | Frontend UI with category + tag checkboxes. Broadcasts filter changes. |

### Seeded content (on activation)
- 4 categories: Product News, Tutorials, Case Studies, Opinion
- 8 tags: WordPress, React, PHP, JavaScript, CSS, Performance, Accessibility, DevOps
- 12 posts with deterministic but overlapping category/tag assignments — picked so filter combinations always return meaningful subsets
- Featured images: locally-generated colored SVG attachments (no network dependency — works offline)
- 1 demo page (`/walkme-posts-demo/`) with both blocks already placed

All seeded entities are tagged with `_walkme_demo = 1` post-meta / term-meta. Seeding is idempotent: a `walkme_pb_seeded` option prevents re-seeding on reactivation.

---

## Architectural decisions

### Inter-block communication: custom events + REST
The two blocks can be placed anywhere on the page, in any order, and there can be multiple grids per page. They communicate via:

1. **`window` CustomEvents.** The Posts Filter dispatches `walkme:filter-change` with `{ categories, tags, targetQueryId }`. The Posts Grid view script listens, then refetches.
2. **REST endpoint `/wp-json/walkme/v1/posts`.** Returns the rendered HTML for a grid given query parameters (page, per_page, columns, categories, tags, query_id). The grid swaps in the new HTML.

#### Alternatives considered

| Approach | Why not chosen |
|---|---|
| **Nest filter inside grid** | The brief explicitly requires the blocks to work when placed independently. Nesting also makes "filter controls multiple grids" impossible. |
| **WordPress Interactivity API** | The modern "correct" answer for 2025+. Skipped because (a) it adds significant cognitive overhead for a single round-trip, (b) the brief doesn't require it, and (c) the custom-event approach demonstrates the same architectural insight (server-rendered HTML, no template duplication) with less coupling to a still-evolving API. Easy upgrade path if needed. |
| **Redux / `@wordpress/data` store** | Overkill — only one slice of state (current filter selections) and one consumer. Adds a runtime dependency for no real benefit. |
| **URL query string as source of truth** | Tempting (shareable filtered views, browser back button) but breaks the "multiple grids on one page" requirement: a single URL can't address multiple independent grids. Would also require a full page reload or aggressive history manipulation. |
| **JS-only filtering on already-rendered DOM** | Doesn't honor `posts_per_page` or pagination; would also fight the dynamic-block contract. |

#### Why server-rendered HTML over JSON
The REST endpoint returns HTML, not raw post objects. This is deliberate:

- **One template, one place.** `Walkme_PB_Renderer::render_grid()` is used for both the initial SSR and AJAX refresh — markup can never drift between the two.
- **No client-side templating engine.** No React-on-the-frontend, no Handlebars, no `template` literals. Zero JS payload for templating.
- **Identical accessibility / SEO surface** on initial load and after refresh.

The tradeoff: response is larger than JSON (probably 2–3×). For a posts grid on a marketing page, irrelevant. If this were a SaaS dashboard with 1000+ items/page, JSON + client templating would win.

### Pagination as inner block
The pagination is its own block (`walkme/posts-pagination`) with `ancestor: ["walkme/posts-grid"]` in `block.json` — Gutenberg enforces it can only live inside a Posts Grid. The grid uses `InnerBlocks` with `templateLock: "all"` and a fixed template so the pagination block is always present when the grid is inserted.

On the frontend, the grid's PHP render embeds pagination markup directly (rather than running the inner block's own render callback). This keeps pagination state (current page, total pages) co-located with the query that produced it — otherwise the inner block would need to know about its parent's query, which is awkward in WordPress's block-rendering model.

### Featured images
Demo posts use locally-generated SVG attachments rather than `media_sideload_image` from a public CDN. Reasons:
- **No network dependency** — works on first activation even offline.
- **Deterministic** — every activation produces the same images.
- **Tiny** — ~300 bytes each vs. 30–80 KB JPEGs.

Tradeoff: SVG MIME type isn't allowed by default on some installs. The activator temporarily enables it (via `upload_mimes` filter) only for the duration of its own attachment inserts.

### `queryId` per grid instance
Each Posts Grid block gets a stable, short `queryId` (e.g. `q-1a2b3c4d5e`) derived from its `clientId` on first render and persisted to attributes. The filter can optionally target a specific `queryId` to control just one grid; if blank, it broadcasts to all grids on the page.

### Idempotency + cleanup
- Activation seeding sets a `walkme_pb_seeded` option so deactivate→reactivate does not re-seed.
- All seeded entities carry a `_walkme_demo` meta marker.
- `uninstall.php` (runs on plugin delete, not deactivate) removes everything tagged with that marker. User-created posts/categories/tags are untouched.

---

## Coding standards
- PHP follows WordPress Coding Standards conventions (tabs, Yoda for sensitive comparisons where natural, `esc_*` on all output, `wp_kses_post` for HTML excerpts, nonce-less GET endpoint declared public-read explicitly).
- JavaScript follows `@wordpress/scripts` ESLint config (run `npm run lint:js`).
- All user-facing strings are i18n-wrapped with the `walkme-posts-blocks` text domain.

---

## Known limitations / tradeoffs

- **No nonce on the REST endpoint.** It's intentionally public-read (anyone reading the site can already see these posts via `WP_Query`). If posts were private, you'd want `permission_callback` + `current_user_can`.
- **No caching layer.** Every filter change hits the DB. For high-traffic sites you'd want to wrap the renderer in `wp_cache_set`/`wp_cache_get` keyed by the query args hash, or move to a Transient.
- **The filter UI fetches all terms with `hide_empty => true` at render time** — fine for the demo (12 posts / 4 cats / 8 tags). For sites with thousands of terms, you'd swap to a typeahead or paginated taxonomy picker.
- **Filter "Clear" is the only way to reset all checkboxes.** No "Apply" button — every checkbox change refetches immediately. If filters were expensive, you'd want a debounce or a manual apply.
- **The pagination inner block has no own attributes** (variant, label customization, ellipsis style, etc.). The brief didn't ask for it; trivial to add.
- **SVG demo images** — fine for local dev / assessment. Production sites typically disallow SVG uploads.
- **The block uses `ServerSideRender` in the editor**, which is convenient but does an extra HTTP roundtrip per attribute change. For a production block you'd debounce or switch to a JS-side preview.

---

## File layout
```
walkme-posts-blocks/
├── walkme-posts-blocks.php       Plugin bootstrap + block registration
├── uninstall.php                 Demo cleanup on delete
├── .wp-env.json                  Local WP environment config
├── package.json
├── includes/
│   ├── helpers.php               Sanitization helpers
│   ├── class-renderer.php        Shared SSR for grid + filter
│   ├── class-rest-api.php        /walkme/v1/posts endpoint
│   └── class-activator.php       Demo seeding (idempotent)
├── src/
│   ├── posts-grid/               Edit + save + view + styles + block.json
│   ├── posts-pagination/         Inner block (ancestor-locked)
│   └── posts-filter/             Edit + view (event dispatch)
└── build/                        Generated by wp-scripts (gitignored)
```

---

## Verification checklist
1. `npm install && npm run build && npm run env start`
2. Login at `/wp-admin` (admin/password). Plugin is active by default via `.wp-env.json`.
3. **Posts** screen: 12 demo posts visible, each with a colored SVG featured image and assigned categories + tags.
4. **Pages → WalkMe Posts Demo**: open. The block editor shows the filter on top, grid + pagination below.
5. Inspector controls on the grid: change columns to 4 and posts-per-page to 4. Preview updates.
6. View the page on the frontend (`/?page_id=…`):
   - Default: 6 posts in 3 columns + pagination at the bottom.
   - Click a category → grid refetches, only matching posts show. Pagination updates.
   - Add a tag → AND with category. Add another tag → OR within tags.
   - Use pagination → page changes, filters preserved.
7. Add a second Posts Grid block on a new page with a different queryId — both grids respond to the filter independently (or only the targeted one if `targetQueryId` is set).
8. Deactivate → reactivate plugin: no duplicate posts created.
9. Delete plugin: demo posts, pages, attachments, and terms are removed.
