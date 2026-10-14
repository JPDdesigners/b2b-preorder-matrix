# B2B Order List Matrix 3.6.1

## Type-to-confirm Empty List and sticky size header — 3.6.0

**Empty list** in My List is now a two-step, Vercel-style guard instead of a single confirmation popup. Step 1 shows exactly what will be removed (codes, pieces, value and, when present, the customer note) with a red "cannot be undone" warning; the safe **Ακύρωση** button has initial focus. Step 2 requires the user to type `delete` and press Enter (or the red **Οριστικό άδειασμα** button, which stays disabled until the word matches). Matching ignores case and surrounding spaces. Paste and drag-and-drop are blocked so the word has to be typed, and a Greek keyboard layout (`δελετε`) is detected with a hint to switch to Latin characters. Escape, backdrop and **Πίσω** cancel without any request.

The server re-checks the same word: the AJAX action `b2b_empty_custom_list` / `b2b_empty_preorder_list` and REST `POST /b2b/v1/preorder/empty` now require `confirm=delete` and otherwise return `400 confirmation_required` without writing. The legacy `DELETE /b2b/v1/preorder/items` API is unchanged.

The size header of the matrix stays visible while scrolling through many colours (revised in 3.6.1, see below).

## Docked size header, sticky footer and plain-text notes — 3.6.1

3.6.0 put the matrix in its own scroll box. That created a nested scroller: on iPad a swipe on the partly visible table moved the table instead of the page, and on desktop the wheel over the cells changed quantities instead of scrolling. 3.6.1 removes the nested box. Each screen now has exactly one vertical scroller.

- **Product page:** the window scrolls. When the size row reaches the top of the screen, it locks directly under the theme's sticky header (and the WP admin bar). It stays there until the last colour row passes, then leaves together with the table, and the page continues normally. The header height is measured live, including headers that appear or shrink on scroll. Themes can force a value with `--b2b-sticky-offset: 90px;` on the matrix container. The footer (totals + Save) sticks to the bottom of the window while the matrix is on screen and returns to the card after it. It is static on screens shorter than 500px.
- **My List editor:** the top bar (Back/X) and the footer are fixed, the editor body scrolls, and the size row locks at the top of the body. On desktop the wheel over the side margins, the top bar or the footer also scrolls the colours.
- **Implementation:** the docked header is an `aria-hidden` copy (`MatrixHeadRow.vue`, shared with the real table header) laid exactly over the in-table header. It is sticky inside a full-height track and follows horizontal scrolling both ways. The real `<thead>` keeps table semantics, and the table box scrolls horizontally only. Shadows appear only while the header or footer is docked. A focused cell (keyboard or hold-to-edit) is scrolled clear of the docked header and the footer.
- **Wheel scroll intent:** a wheel gesture that starts as a scroll (page, margins, colour column) keeps scrolling while it passes over quantity cells, until the wheel is idle for about 450ms (trackpad inertia included). Then wheel ↑/↓ over a cell changes it by ±1 as before.
- **Customer note:** stored as plain text exactly as typed. `< 40 τεμ.`, `5<10` and `10%20` no longer become `&lt;` or lose characters. Only invisible control characters are removed, line endings are normalized, and outer spaces are trimmed when the note is frozen into the order. Every output escapes it (Vue text binding, WooCommerce `wp_kses`, PDF `esc_html`). Drafts saved by older versions show `&lt;` as `<`.
- **Product-page footer label:** now reads «Σύνολο επιλογής · συνολική αξία» instead of «ενδεικτική αξία».

Reliability fix: when a customer's list is locked (a running submission/finalize, a season reset or a database read error), the product-page matrix read and save and the REST items read now return a JSON `503 list_busy` with a Greek retry message. Before, they threw an uncaught exception, which caused a PHP fatal and a WordPress recovery-mode email.

## Customer draft note and submission stages — 3.5.0

The customer note now autosaves to a third site-scoped InnoDB table, `b2b_preorder_drafts`, with one indexed row per account (`user_id`, `customer_note`, `revision`, `updated_at`). This keeps an order-level draft separate from variation quantities and submission history. No note text is stored in localStorage. The new table is created automatically on upgrade, preserving existing lists and batches.

My List debounces note saves by 800 ms and flushes them on blur and before submission. Writes use the existing fresh nonce/account checks and account lock. Revision checks prevent silent overwrites from another tab/device, including the interval between saving the note and starting an order. On window focus, one small note request refreshes clean text or reports a conflict while preserving local edits; there is no polling or full-list transfer. Conflict resolution explicitly reloads the saved note or replaces it with the customer's text. Failed saves remain visible, warn on leaving, and prevent a new submission. Notes accept up to 4,000 Unicode characters, without byte truncation.

Submission freezes the stored note in its snapshot and saves it as the WooCommerce customer note. Successful completion or **Empty list** clears the draft atomically with quantities; cancellation retains it. Empty revision rows prevent stale clients from resurrecting cleared notes. The seasonal admin reset and health check include all three tables; a transactional reset epoch also invalidates drafts open across a reset, even empty drafts and repeated resets in the same second. Uninstall removes this table and its epoch option while preserving WooCommerce orders.

My List labels use **Κωδικοί σχεδίου** for the list card, **Κωδικοί** for counts and **Τεμάχια** for quantities. Submission shows preparation, order-line creation, totals/completion and navigation as separate stages. The batch percentage counts variation rows, with batches of 150 rows, not pieces. Preparation and finalization have a themed animated indicator; the acknowledged line percentage remains visible during finalization. No fabricated percentage is shown for the single finalization request. Interrupted submissions retain the existing resume behavior.

A WooCommerce catalog matrix and dedicated **Pre-Order List / Cart System** powered by custom normalized database tables, Vue 3, and plugin-scoped CSS.

## Install

Requires WordPress 6.5+, PHP 7.4+ and WooCommerce. The repository contains the PHP plugin, Vue source, tests and the current production JavaScript/CSS in `b2b-matrix/dist`. Local release ZIPs are not committed.

To install from a checkout, copy the runtime files listed below into `wp-content/plugins/b2b-preorder-matrix/`, then activate **B2B Pre-Order Matrix API** in WordPress. The committed build is ready to use; Node.js is needed only to develop or rebuild the frontend. Keep the existing plugin folder when replacing an older installation; existing `_b2b_preorder_items_{product_id}` and `_b2b_custom_order_list` user metadata automatically migrate to the custom database tables on first read/write.

The deployable plugin contains exactly:

```
b2b-preorder-matrix/
  b2b-matrix-api.php
  uninstall.php
  includes/class-b2b-matrix-db.php
  includes/class-b2b-matrix-settings.php
  includes/class-b2b-matrix-maintenance.php
  includes/class-b2b-matrix-catalog.php
  includes/class-b2b-order-channel.php
  includes/class-b2b-preorder-list.php
  includes/class-b2b-preorder-submission.php
  assets/admin-settings.css
  b2b-matrix/dist/matrix-app.js
  b2b-matrix/dist/matrix-style.css
  README.md
  LICENSE
  licenses/vue-MIT.txt
  licenses/pinia-MIT.txt
```

## Build from source

Requires Node.js 22.12+ and npm for the Vue build, plus Python 3.9+ for packaging. WordPress runtime requirements remain PHP 7.4+, WordPress 6.5+ and WooCommerce. Clone this repository and run:

```sh
git clone https://github.com/JPDdesigners/b2b-preorder-matrix.git
cd b2b-preorder-matrix/b2b-matrix
npm ci
npm test
npm run build
cd ..
python tools/build_release.py
```

The uploadable ZIP is `releases/b2b-preorder-matrix-3.6.1.zip`, with a single `b2b-preorder-matrix/` root. The packaging script checks every ZIP entry against its source bytes and excludes tests, dependencies and local files. Use `--force` to replace a previously built ZIP. Rebuild Vue after editing `.vue` or JavaScript source; packaging by itself uses the current committed `dist` assets. The automatic GitHub source archive contains the development repository; use the named plugin ZIP attached to the release for WordPress upload.

Own plugin code is licensed under GPL-2.0-or-later; see [LICENSE](LICENSE). Bundled Vue and Pinia retain their MIT licenses, included in `licenses/`.

No Node dependencies, demo HTML, mocks, tests or development server are deployed.

## Admin dashboard and seasonal data — 3.4.0

The native WordPress dashboard has three sections: Overview, Appearance, and Tables & Season. It requires `manage_options`; no additional Vue app or polling is loaded in admin.

Tables & Season displays table engines, estimated row counts and allocated data/index space. An explicit read-only health check verifies required columns and indexes (including unique indexes), InnoDB, exact counts, invalid row values, active batch counts, and completed/failed/cancelled history and date range. It does not parse customer snapshots or run on frontend visits. This is an application/schema check, not a filesystem-level database corruption checker.

The manual **Clear season** action requires a POST, a nonce and typed `CLEAR`. It removes all users' My List rows and customer draft notes and all batch records, snapshots and error history for the current site. It preserves table structures, appearance settings and WooCommerce orders. There is no automatic six-month deletion or per-season archive. Take a database backup before clearing if historical table data is needed for comparison. InnoDB can reuse freed space; DELETE does not promise immediate filesystem shrinkage.

Reset refuses active batches and in-flight account operations, including a first save with no table rows. A site maintenance named lock blocks new account callbacks; all three table deletions use one transaction and roll back together on failure. The WordPress options table must also be InnoDB, so legacy retirement is committed in the same transaction. Normal account operations make one small `IS_USED_LOCK` query after acquiring their existing account lock; they do not globally serialize different users. The manual reset scans account lock names only during reset. Run it at a quiet seasonal boundary, and refresh existing customer tabs afterward; product-quantity cross-tab synchronization is not implemented; note drafts refresh on window focus.

After a successful reset, legacy metadata imports are permanently retired for that site so old lists cannot repopulate the cleared table. Legacy keys are not site-scoped and are preserved for other multisite sites; current site's cancellation notices are removed. The last successful reset time is recorded.

For optional integration, `B2B_Matrix_Settings::design_tokens()` exposes validated accent, readable foreground, focus color and corner radii. JPD Order Documents 1.1.0 can opt into these without a framework dependency.

The maintenance integration test uses a disposable MariaDB bound to localhost on a dedicated port, with fixed `codex_b2b_maintenance_test` database and `audit_` prefix. Never point it at a store database. On this workstation initialize a temporary directory with `mysql_install_db.exe --datadir=<temporary-directory> --port=3317`, start its `mysqld.exe --defaults-file=<temporary-directory>/my.ini --bind-address=127.0.0.1 --port=3317`, then run `php tests/maintenance.php 3317`. Stop the disposable server after testing. The test covers guards, concurrent connections, rollback and preservation of order data.

## Frontend development

Use Node.js 20.19+ or 22.12+ with the frontend's Vite 8 toolchain. From the repository root:

```sh
cd b2b-matrix
npm ci
npm test
npm run build
```

Commit source changes together with the rebuilt `b2b-matrix/dist/matrix-app.js` and `matrix-style.css`, so a checkout always includes the corresponding production build. `package-lock.json` pins frontend dependencies. For a local preview, run `npm run dev`; `/tests/list.html` provides a simulated My List without connecting to a WooCommerce store.

The `snippets/my-list-load-generator.php` utility is optional development tooling. See [its usage instructions](snippets/my-list-load-generator-usage.md). It is not loaded by the plugin.

On supported single variable-product pages the matrix appears after WooCommerce's product tabs and summary meta, before upsells and related products. PHP emits product-specific CSS before the purchase form is rendered, so the WooCommerce/Flatsome form is hidden on first paint while Vue displays the matrix loading skeleton. The form hooks remain registered. Unsupported or access-denied products retain their purchase form. Use the `b2b_matrix_auto_inject_single_product` filter to opt out of automatic injection when placing the shortcode manually. Supported attributes are `pa_color`/`pa_size` or local `color`/`size`; each combination must refer to one specific variation. Wildcards and duplicate combinations are rejected.

Deleting the plugin from WordPress Plugins runs `uninstall.php`, which drops the three plugin-owned `b2b_preorder_items`, `b2b_preorder_batches` and `b2b_preorder_drafts` tables for each site and removes the plugin settings/version options. Deactivation and upgrades preserve them. Existing WooCommerce orders are unaffected.

Shortcodes:
- `[b2b_product_matrix]` or `[b2b_product_matrix product_id="123"]`: Renders the single-product matrix table.
- `[b2b_preorder_list]` (or alias `[b2b_custom_order_list]`): Renders the dedicated B2B Pre-Order Cart / List page with dual search, brand cards, matrix modal editor, and batch order submission.

## Pre-Order List & Batch Order Architecture

The Pre-Order List shortcode (`[b2b_preorder_list]`) powers the customer's draft order review page:
- **Dual Search Architecture:**
  - **Quick Add Search (Top Right):** Live catalog search shares the page heading card, with an ABC/123 keyboard button for tablets and barcode scanners. On tablets, results open in a panel to the left and slightly above the field when space permits. On narrower screens and desktops the panel uses the available space above or below. It follows `visualViewport` resize/scroll events so the on-screen keyboard does not cover the results. The panel has a close button and its own scrolling area; selecting a product opens the matrix modal. Escape, moving focus away or clicking outside dismisses the panel without clearing the query. Saving or returning from the matrix preserves the query; when opened from Quick Add, focus returns to the search field with the caret at the end and results dismissed, ready to change the last SKU digit. The query is kept only in the current page, not in browser storage.
  - SKU results come from WooCommerce's existing `wc_product_meta_lookup` table: exact matches first, then prefixes, then other SKU matches and product names. Exact and prefix queries are separate to use the SKU index; wider searches stop when 8 accessible results have been found. Variations are grouped by published parent before the candidate limit, and matching variation SKUs remain visible. No extra index, Flatsome integration or Relevanssi dependency is required. Numeric-only queries start at 4 digits; names and mixed SKUs start at 2 characters, regardless of keyboard toggle. A 250 ms trailing debounce consolidates rapid typing. Result thumbnails use native lazy loading and asynchronous decoding; browsers may preload nearby images.
  - Enter opens only a unique exact SKU, including a variation SKU mapped to its parent. Enter can start the lookup immediately instead of waiting for the typing debounce. Changing the query, dismissing results or moving focus away cancels pending automatic opening; partial codes and names retain the tap-to-select results. The input has `enterkeyhint="go"` for supported tablet keyboards. ABC/123 changes only the keyboard; names and SKUs remain searchable in either mode.
  - The results panel is teleported to `body`, preserving the plugin's font and appearance. Supported browsers show it as a non-modal manual popover in the browser's top layer, above theme headers and outside ancestor clipping/stacking contexts. Older browsers use a fixed panel with a high z-index. Outside click, focus moving outside the search/panel, Escape and the close button still dismiss results.
  - Results display only «Ήδη στη λίστα» and an edit icon for saved products. Membership comes from a reactive set of parent product IDs in the already-loaded list, including additions and removals. Search does not read or calculate list quantities, or query the custom items table. Changes made on another device appear after refreshing the list. Matrix reads and writes continue to validate against the backend.
  - **In-List Filter (Bottom):** Filters only the items currently present in the customer's draft list by title or SKU.
- **Authentic Matrix Modal:**
  - Clicking «Αλλαγή» or selecting a product from Quick Add opens `MatrixTable.vue` inside `MatrixModal.vue`, with touch increment, one size-curve editor, price inspection, and live subtotal calculations. On every screen size it opens as a full-page editor with a fixed header and save bar, a prominent back button and a separate close button. Desktop content matches the My List maximum width (80rem including 12px side gutters); only the matrix content scrolls. The My List heading card uses a subtle strip in the soft accent color.
  - The parent list scrolls within a fixed-height window and renders only nearby cards. Scrolling continues into the page at either end. No virtualization package is required. The ABC/123 keyboard choice is stored as a browser preference.
  - Saving from My List requests a compact parent summary in the same Save acknowledgement. The server uses the canonical saved quantities and reads catalog details only for that parent. Vue replaces, inserts or removes that row and recalculates totals, brands and facets without another list-data request. The full list loads on page entry; errors and other list actions may still refresh it. Single-product page saves retain their existing acknowledgement.
- **List filters and totals:**
  - Customer details, the optional order note and the full summary share one sidebar card with three accent section headings and no subtitles. The summary has a dark total panel with an accent border. On tablets the customer and note sections sit side by side; on phones all three stack.
  - Bordered product rows retain image, brand, title and SKU in the first column, followed by separate quantity and line-value columns and an edit action. Desktop headings and numeric values are centered, with a shared column gap keeping the value separate from Edit. The desktop window shows five complete rows; long titles and SKUs truncate with full-text tooltips. On phones the numeric columns move beneath the product details. Delete controls are neutral gray and become red with a soft red background on hover or keyboard focus; Edit uses the active accent color with a soft accent background.
  - Product-row layout classes are namespaced as `b2b-list-product-*`, with explicit internal margins and padding. Theme rules for single-product `.product-main` / `.product-info` no longer enlarge the grid contents or push the product title, image and numeric columns below the card's bounds.
  - Root product categories appear as tabs on their own row, with Empty List on the right above the filtered subtotal. Empty List always applies to the full list and requires the two-step typed `delete` confirmation (see 3.6.0), including when a category or search filter is active. The subcategory dropdown sits above the in-list search, while the wider filtered subtotal occupies both rows to the right with its own heading and divider. Section headings and borders follow the selected palette and corner style.
- **Sequential Batch Submission:**
  - On a full My List load, canceled/nonpublished/deleted products and variations are removed in one account-locked bulk delete. A canceled parent removes its selected variations; a canceled variation leaves its valid siblings in place. Totals, filters and quote digests reflect the remaining list. Missing prices, stock availability and access restrictions do not cause automatic deletion. Frozen active submissions retain their snapshot/list until resumed or canceled.
  - The optional site helper `jpd_get_canceled_items_bulk` is still called once for the selected variation IDs; native WooCommerce existence, type, status and parent checks also work without that helper. A notice journal is stored before deletion so failed deletion can retry and lost responses/refreshes retain the notification. A scrollable native modal lists the removed styles, identifies partial removals, and closes through «Ενημερώθηκα», Escape or a backdrop tap. Closing acknowledges the matching notice token through a nonce-protected POST; an old tab cannot dismiss newer cancellations. Notices are scoped by account and site. Hard-deleted products without recoverable catalog metadata use their product ID as a fallback label.
  - Large B2B orders (1,000–3,000+ line items) bypass the heavy standard WooCommerce cart/checkout.
  - When the customer clicks «Υποβολή λίστας», orders are created in chunks of 150 items (`wp_b2b_preorder_batches`) with an interactive progress modal and retry mechanism directly into a native WooCommerce order. The temporary shell remains `b2b-draft` while building; a new finalized order becomes `processing` (`wc-processing` in storage). Completion redirects to My Account → Orders, where the site's existing PDF/print action remains available per order. Historical custom order statuses remain registered for existing orders.
  - The `b2b-draft → processing` transition is registered with WooCommerce's transactional email pipeline and triggers its existing New Order (admin) and Processing Order (customer) email classes. Their enabled settings, recipients, templates and deferred email configuration remain in control. No manual duplicate send is added; retrying finalization after saving `processing` completes list cleanup without repeating the transition. Existing already-submitted legacy batches retain their historical status on recovery. Email delivery must still be verified with the site's real mail configuration.
  - Processing ordinarily invokes WooCommerce stock reduction. Filters limited to orders created via `b2b-preorder` with a batch token preserve this plugin's existing policy of not reducing stock, including direct stock-reduction calls. Ordinary WooCommerce orders keep their normal stock behavior.
  - The server compares the list's product IDs, variation IDs, quantities and prices with the version the customer confirmed. A change rejects submission before creating an order and refreshes the list for review.
  - The list includes an independent confirmation digest per parent. Save replaces only that parent's digest; submission validates the complete manifest against current server quantities and prices. This preserves stale-price and other-tab change detection without rebuilding the full list after every matrix save. Legacy single-digest callers remain supported.
  - Each retryable chunk checks only its variation IDs with one read-only SQL lookup. New order lines are still saved through WooCommerce CRUD; finalization verifies the complete order once.
  - The submission dialog has an accent status pill, keep-open instructions and batch progress. Once every variation is written it switches to an indeterminate finalization state: «Περιμένετε, ολοκληρώνουμε την παραγγελία και υπολογίζουμε τα σύνολα…». The finalization request allows up to three minutes of client-side waiting, subject to the hosting server's own limits. The dialog remains open through navigation and disappears with the departing page; there is no completion prompt or unload warning. Close/Continue controls appear only after failure; retries resume the existing batch and can retry finalization without repeating completed chunks.
- **Customer Identity & Billing:**
  - Displays Company (Επωνυμία) from `billing_last_name`, VAT Number (ΑΦΜ) from `billing_first_name`, and email from `billing_email`, with the older `billing_company`/`billing_vat` fields as fallbacks.

## Order origin — 3.3.0

My List shows **Παραγγελία από live δειγματισμό** only to a current user with `manage_woocommerce`, or a switched customer whose original User Switching user has that capability and a valid authenticated original-user cookie. A direct manager defaults to unchecked; a switched seller defaults to checked. Ordinary customers never see the field and cannot forge a live order or seller ID. The checkbox can be changed before submitting; refreshes preserve the choice for the same account.

New My List submissions store `_jpd_order_channel` (`live` or `online`) using WooCommerce order CRUD. Staff submissions also store `_jpd_order_actor_id`, identifying the authenticated original seller; this actor remains recorded even when staff selects online. The server captures both values with the first batch snapshot, before order creation. Repeated requests, interrupted creation and resuming a batch preserve them. Order emails and the processing status continue through the existing workflow. No ACF, cron or analytics extension is required.

Both HPOS and legacy Orders screens have a **Προέλευση** column (including the seller when available) and an **Όλες οι προελεύσεις** filter with live, online and unknown options. Existing orders, legacy unfinished batches and orders created by other checkout/import tools without this metadata remain **Μη καταγεγραμμένη προέλευση**. They are never automatically counted as online. An authorized order editor can correct the channel in the order details, including mark it unknown again; an internal order note records the previous/new channel and editor. Corrections preserve the original submitting seller. Uninstall retains order origin history.

For CSV: filter Orders, select the desired orders, then choose **Εξαγωγή προέλευσης (CSV)** from bulk actions. This exports only the selected orders (up to 1,000 per request), with internal order ID, displayed order number, creation date, status, customer ID/company, saved order total/currency, channel code/label, seller ID/name. The UTF-8 BOM supports Greek text; spreadsheet formula cells are treated as text. Totals come from the order, not current product prices. The CSV contains no product line items or photographs; match `order_id` to another order export if needed. Other exporters can use the two canonical metadata keys, but adding them to a third-party export template depends on that exporter. Filtering admin Orders does not add a WooCommerce Analytics report. Filter/CSV results include each order's status; exclude void/draft/cancelled orders according to the revenue calculation you need.

The local `/tests/list.html?staff&two` and `?switched&two` fixtures exercise the two defaults; omit either flag to test a customer. `?switched&two&retry` tests frozen source after interrupted submission. Backend contracts cover verified switching, forged fields, snapshot recovery, corrections/notes, nested admin filter composition, selected CSV access and saved totals. Actual WordPress/WooCommerce HPOS and legacy admin integration still requires an upload test on the shop.

## Catalog and private data

The public catalog is visible to visitors and signed-in users. Only signed-in users may save a personal list. Visitors can explore quantities and estimated totals, but Save is disabled with a sign-in message/link. Guest edits are temporary and are not transferred through login.

The old `require_login` appearance option is no longer used. A separate site privacy plugin owns whole-site login gating. Verify that its privacy policy also covers the desired API paths; hiding a page alone is not API authentication. Do not enable public catalog access if the entire catalog must remain confidential without configuring that site-level policy.

Existing `b2b_matrix_user_can_access` and `b2b_matrix_product_can_access` filters remain available for site-specific restrictions. The former applies to signed-in accounts; product access applies on reads and saves. No caller may select another user's storage identity.

Catalog structure is shared, so it assumes the same published color/size catalog for authorized viewers of a product. If a separate extension changes individual variation visibility by role, integrate that policy explicitly and partition/disable shared structure caching; do not assume all third-party storefront filters are supported.

## Cache and loading

- The shortcode embeds only catalog structure: title, SKU, color/size labels, variation IDs and image URLs/dimensions. No price, quantity, user identity or nonce is placed in page HTML.
- Structure uses a one-hour WordPress transient, keyed by product, locale and catalog generation. Product/variation updates, status changes, term edits and attachment edits invalidate the generation. The cache contains no customer state or prices.
- The UI renders the cached structure immediately. Only quantity fields, prices and summary values show loading placeholders; editing is disabled until fresh data arrives.
- On the My List page, a small in-memory cache reuses only matrix structure when reopening a product; prices and saved quantities are fetched afresh. The private My List payload, including customer details, list membership and totals, is never stored in browser storage or shared cache. Its first load uses a matching skeleton. WordPress and WooCommerce continue to cache ordinary catalog objects on the server.
- One cookie-authenticated `admin-ajax.php?action=b2b_get_matrix_data` request bootstraps identity, nonce, fresh catalog prices and the current customer's saved quantities. If the HTML structure version is outdated, the response replaces it before enabling editing.
- Dynamic reads, session bootstrap and saves use private/no-store responses and frontend `cache: 'no-store'`. REST errors receive these headers too. Browser back/forward snapshot restoration reloads the page to re-establish the session.
- The REST GET/POST endpoint `/wp-json/b2b/v1/matrix` remains supported. Full GET responses support old frontend clients; the new client supplies `structure_version` for compact responses. AJAX compatibility save is also supported.

Exclude these API paths/actions from CDN or server rules that override origin cache headers. Verify with two accounts and warm page cache. A cached page shell does not make customer requests cacheable.

**Upgrades still require page/CDN/asset cache invalidation when an upstream cache retains old HTML or JS.** The plugin versions asset URLs and fresh data overrides appearance settings, but cannot force arbitrary third-party caches to obey. A stale client bundle can keep old behavior even while quantities are fresh. Purge once after installing this release, then test normal warm-cache visits.

## Catalog prices and eligibility

The plugin reads published child variation objects directly; it does not call `get_available_variations()`. Out-of-stock status, purchasability, checkout quantity rules and the WooCommerce hide-out-of-stock option do not control this draft list. Deleted, disabled/private or unrelated variations cannot be saved.

Prices come from the current variation `get_price()` getter, without tax-display or checkout calculations. The value is used as configured in the catalog; the plugin does not convert tax-inclusive stored prices to net prices or relabel them as net. Site-specific getter filters can still provide customer prices, which are fetched privately. Rules that exist only in the cart require a separate integration. Missing prices display a dash and block submission. The list displays the sum as «Γενικό σύνολο» under this store's no-tax/no-shipping workflow.

Only IDs and quantities are saved in the draft list. At submission the server verifies that quantities and current prices match the confirmed list, then snapshots those prices into the WooCommerce order. If either changed, the user must review the refreshed total and submit again.

## Touch and keyboard

- Quick touch: apply +1 immediately at finger release. No single-tap timeout and no double-tap recognition.
- Hold at least 500 ms without moving: focus/select the numeric input on release, synchronously with the user event. No increment or zeroing occurs. This avoids relying on asynchronous focus to open a mobile keyboard.
- Scrolling, multitouch and cancelled touches never change quantities. Touching an already focused input leaves native caret/selection behavior intact. Tap outside it to finish editing.
- Mouse click/keyboard navigation enter the numeric input normally. On a desktop with a fine pointer, the wheel over a quantity input adds one when scrolled up and removes one when scrolled down, clamped to zero/max quantity. Scrolling elsewhere remains native; zoom modifiers and horizontal scrolling do not edit quantities. Loading, readonly and saving states disable wheel edits.
- The curve editor has one ratio view. A dedicated curve column applies +/− to one color at a time, following the selected size ratio and clamping at zero/max. Custom curves are a browser preference, not a saved order.
- Curve ratio cells use the same touch gesture as matrix quantities: tap to add one, hold for 500 ms to select the number and open the numeric keyboard. A saved curve from a different size system resets automatically to one per current size and shows a dismissible three-second notice beside the curve button.
- Color photos sit beside their names. Quantity changes are summarized in the parent product totals instead of appearing above every cell. No carton or packaging totals are shown.
- Tailwind has been removed from the source and build. The list uses plugin-scoped CSS; matrix components use scoped Vue styles.

An actual iPad/Android test is still required for native keyboard and touch-event behavior; a desktop viewport is not physical touch validation.

## Appearance and lightbox

WordPress Admin → B2B Matrix requires `manage_options` and saves through the native Settings API. Palettes, custom HEX, inherited font and square/rounded corners remain available.

Lightbox choices:

- **Site / WooCommerce (default):** reuse existing WooCommerce PhotoSwipe globals and markup with the selected color's full-size image. No gallery library is added. If unavailable/incompatible, use the plugin dialog.
- **Plugin:** always use the lightweight native dialog.

Themes using another lightbox can handle the cancelable DOM event `b2b-matrix:open-image` on `document`, read `event.detail` (`url`, `title`, `width`, `height`) and call `preventDefault()` only when they successfully own opening it. There is no universal WordPress lightbox API, so arbitrary theme galleries do not integrate automatically.

## Save integrity and remaining limit

REST saves require a valid WordPress nonce and authenticated user. AJAX saves require both as well. All IDs, parent/child relationships, variation publication status, duplicate entries, quantity bounds (0–99,999) and batch size (up to 2,000 changed cells) are validated before writing. Zero deletes only the specified quantity. Database failures and uncertain POSTs never silently report success or retry the write. Session refresh before save rejects account changes without replacing edits.

Unavailable saved entries are preserved outside visible totals. Quantities live in the plugin-owned `b2b_preorder_items` table and changes for one account are serialized with a database user lock. Revision-aware conflict handling remains a separate requirement before enabling collaborative sales-rep editing across stale browser sessions.

## Verification

```
cd b2b-matrix
node --test tests/*.test.js
node node_modules/vite/bin/vite.js build
cd ..
php -l b2b-matrix-api.php
php -l includes/class-b2b-matrix-catalog.php
php -l includes/class-b2b-matrix-settings.php
php tests/backend.php
php tests/settings.php
php tests/preorder-list.php
php tests/uninstall.php
```

On this workstation use `C:/xampp/php/php.exe`. PHP tests use WP/WC doubles, not a real database. JS tests cover store behavior, isolated touch/wheel input and lightbox selection/fallback. The local `/tests/loading.html` fixture holds a simulated private response so the skeleton can be inspected, and includes a guest mode. `/tests/list.html` supports `?many`, `?slow`, `?retry` and `?badredirect` for layout and submission checks. Its successful submission redirects to the local `/tests/orders.html` fixture. `/tests/appearance.html` and `php tests/settings.php --preview` provide appearance/admin fixtures. These are not deployed.

Before production, verify genuine persistence, session expiry, guest POST rejection, two-account cache isolation, out-of-stock visibility, real theme lightbox behavior, settings persistence, and physical tablet gestures. No live site was modified by this local release work.
