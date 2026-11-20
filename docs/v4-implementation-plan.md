# UCF Degree CPT v4.0.0: Implementation Plan

Draft · `rc-v4.0.0` · November 20, 2026

## What v4 is

v4 is not an upgrade path for the current site. It is a clean break: a degree plugin for the new website, which runs on a block theme. Nothing in v4 has to keep working on Main-Site-Theme. We will not migrate existing degree content into it, and any later migration will be a separate project that follows the rules this release sets.

That frees us from the parts of v3 that only exist for history. Today an import copies about 30 Search Service values per degree into ACF fields, most of them written by Main-Site-Theme's import filter rather than by this plugin. About 35 more ACF fields hold editorial content, and theme templates put the page together from both. In v4 the plugin provides the whole degree experience: the post type, the site templates that lay out a degree page, the blocks that render Search Service data, and patterns for editorial content. Postmeta shrinks to what we need to identify, list and search degrees. Search Service data is read when a page renders, through a cache. ACF is no longer a dependency.

The rest of this document describes that end state and the order we build it in.

## Decisions

- **The site template owns the layout and the data.** The plugin registers a `single-degree` block template that places the data blocks in a fixed order. Because the data blocks pull everything from the Search Service, a freshly imported degree with empty `post_content` already renders a complete page. Changing the layout once in the Site Editor changes it for every degree.
- **`post_content` is only for editorial content.** The template includes one `core/post-content` slot. Editorial sections such as course overview, skills, admission copy and news go there, built from core blocks and starter patterns. Editors only touch a degree when they have something to add.
- **A second template covers fully custom pages.** `single-degree-custom` renders only the title and `post_content`, which takes over the job of v3's `template-degree-custom.php`.
- **Custom blocks are for data only.** A custom block exists only when it renders Search Service or taxonomy data. Each data block renders its own section heading, so a degree with no outcomes or deadlines drops the whole section and doesn't leave an empty heading behind.
- **Presentation follows block-theme conventions.** Blocks declare block supports (color, spacing, typography, border) and are styled through `theme.json` `styles.blocks`. The plugin ships only structural CSS. Interactive pieces, such as the request-info dialog and deadline tabs, use the Interactivity API with a native `<dialog>` element rather than Bootstrap.
- **The Search Service is the source of truth, and postmeta is a cache of it.** A page render never waits on the Search Service when a cached copy exists.
- **Meta and option names get a clean namespace.** Since nothing upgrades in place, every key moves to a `ucf_degree_` prefix and v3's names are not carried over.
- **The Search Service is whatever the environment says it is.** Nothing in the plugin assumes production. Every request (import, render-time fetch, refresh and writeback) goes through one resolved base URL, so pointing a site at a non-production service is a configuration change, not a code change.

## Search Service configuration

The configuration question isn't whether the URL is a setting, since it already is in v3. It is whether one site can end up talking to two services at once. v3 hardcodes `https://search.cm.ucf.edu/api/v1/` as the default and lets `wp degrees import --api_base_url` override it for a single run. That was harmless when the import was the only time the plugin talked to the Search Service. In v4 the cache refreshes on its own schedule, so a one-off import from staging would leave degrees cached from one service and refreshed from another.

A single `UCF_Degree_Search_Service` class owns the connection. Every other class asks it for URLs and never builds one itself. It resolves the base URL and key in this order:

1. **Constants:** `UCF_DEGREE_SEARCH_SERVICE_URL` and `UCF_DEGREE_SEARCH_SERVICE_KEY` in `wp-config.php`, or environment variables of the same names. When a constant is set, the matching settings field is shown read-only, along with where the value came from.
2. **Options:** `ucf_degree_search_service_url` and `ucf_degree_search_service_key` from the settings screen.
3. **Nothing.** There is no built-in default. A site with no service configured imports and fetches nothing, and both the settings screen and Site Health say so.

Constants come first because databases get copied between environments. When production's database is cloned to staging, the option comes along with it, but the staging `wp-config.php` constant still wins. That keeps a refreshed staging site on the staging service without anyone remembering to change a setting.

Each environment's Search Service is a completely separate system. Program IDs differ between them, while plan and subplan codes are the same everywhere, because those codes come from a system outside the Search Service. The rest of this section follows from that.

- **Every cache entry records its source.** Each one stores the host it came from and the program ID that host assigned. If the configured service changes, entries from another host are treated as stale and refetched from the new service by plan and subplan code, so a site never shows a mix of data from two systems for long.
- **Linked URLs must point at the configured host.** The program record links to outcomes, projections, deadlines, careers and writeback `update_url`s as absolute URLs. We follow a link only if its host matches the configured service. Otherwise we refuse it and log an error. Since each environment is self-contained, a mismatched host means something is misconfigured, and failing loudly is safer than quietly reading from (or writing to) another system.
- **Other caches are keyed by host.** The description-type and profile-type transients include the host in their key.
- **There is no per-command URL override.** `wp degrees import` uses the resolved service, just like rendering does. `wp degrees service` prints the active URL and its source, then tests the connection with the key. Anyone who needs a different service for a session sets the environment variable for that shell.

### Finding a program

Since plan and subplan codes are the only identifiers shared across environments, every lookup starts from them. A single `find_program( $plan_code, $subplan_code )` method on the data service is the only place that turns codes into a program record.

Today's API has no exact lookup, so `find_program()` queries `programs/search/` with `plan_code`, plus `subplan_code` or `subplan_code__isnull=True`, and then checks for an exact match on its side. Search can return near matches, so we can't skip that check. It's the same approach v3's writeback code takes in `return_verified_result()`. The program ID it finds is cached with the entry and reused on refreshes against the same host. If a request by cached ID returns a 404, we look the program up by code again.

Subplans find their parent by code as well. A subplan's parent is the degree with the same plan code and no subplan code. v3 matched parents by Search Service ID instead, which doesn't survive a change of service. We believe the shared plan code always holds for subplans, but we should confirm it against the data before relying on it.

We'd like the Search Service to add two things:

- **Exact lookup by code.** An endpoint such as `programs/code/{plan_code}/` and `programs/code/{plan_code}/{subplan_code}/` would return exactly one program or a 404. That removes the search-and-verify step and the near-match risk that comes with it.
- **Embedded related data.** An `expand` parameter on that endpoint (for example `?expand=outcomes,projections,deadlines,careers,quotes`) would return the related resources inline. That cuts a fetch from about six requests to one, and it also makes the linked-host check above unnecessary.

`find_program()` tries the exact-lookup endpoint once per host and remembers whether it exists. If it does, the plugin switches over without a release or a setting. Until then it uses search-and-verify, and when `expand` is missing it falls back to following links.

What follows from this section: the Search Service changes are an improvement we can request now and pick up whenever they ship; nothing in this plan waits on them. `UCF_Degree_Search_Service` and `find_program()` are built in Phase 1, before the data layer, so that no later code can bypass them.

## Data model

### Postmeta

- **Identity.** `ucf_degree_plan_code` and `ucf_degree_subplan_code` identify a degree, and `ucf_degree_id` holds the two joined together for fast lookups. These codes are the same in every environment. The Search Service program ID is not stored as meta, because each environment assigns its own. It lives only inside the cache entry from the service that issued it.
- **Listing and search.** `ucf_degree_name_short`, `ucf_degree_hours` and `ucf_degree_online` feed the degree search API and Query Loops, which work across hundreds of degrees and can't fetch each one from the Search Service.
- **Import controls.** `ucf_degree_import_ignore`, `ucf_degree_tuition_skip` and `ucf_degree_lock_excerpt` keep v3's behavior.
- **Editorial values the template needs.** `ucf_degree_undergraduate_slate_id` is the only one today. It feeds `request-info`, which lives in the template, and the Search Service only provides graduate Slate IDs. Any further per-degree values that a template slot needs are added the same way and exposed through the core `core/post-meta` bindings source.
- **Cache.** `_ucf_degree_program` holds the normalized Search Service data and `_ucf_degree_program_fetched` records when we fetched it. A failed fetch writes `_ucf_degree_program_error`.

Every key except the cache keys is registered with `register_post_meta()`, with a type, `single`, `show_in_rest` and an `edit_post` auth callback, so the editor sidebar can read and set them. Title, slug, excerpt (from the program's `excerpt` unless it is locked) and parent (for subplans) stay post fields.

The plugin keeps registering the `program_types`, `career_paths` and `interests` taxonomies. `colleges` and `departments` come from UCF-Colleges-Tax-Plugin and UCF-Departments-Tax-Plugin, and the importer already treats them as optional. Credit hours, excerpt, interests and career paths were all set by Main-Site-Theme's filter in v3. In v4 the plugin sets them itself.

### Program data service

A new `UCF_Degree_Program_Data` class (`includes/class-ucf-degree-program-data.php`) is the only code that talks to the Search Service for display data. The public helper `ucf_degree_get_program( $post = null )` returns a normalized array:

```
schema_version, fetched_at, source (service host), api_id (as issued by source)
name, name_short, credit_hours, online, catalog_url, graduate_slate_id, excerpt
tuition       { resident, nonresident, unit }          raw numbers; formatting happens at render
descriptions  { catalog, full, custom, by_type_id{} }
highlights    [ { icon, text } ]
quotes        [ { text, source, image, image_alt } ]
deadlines     [ { type, term, display } ]
requirements  [ string ]
outcomes      { academic_year, avg_annual_earnings, employed_full_time, continuing_education }
projections   { begin_year, end_year, begin_employment, end_employment,
                change, change_percentage, openings }
careers       [ string ]
```

A fetch gets the program record by its cached ID, or through `find_program()` when there's no cached ID for the current host. It then follows the program's `outcomes`, `projection_totals`, `application_deadlines` and `careers` URLs, plus `marketing/quotes/{id}/` for each quote. That is about six requests per degree. A `ucf_degree_program_data` filter runs on the normalized array.

### Cache policy

We cache in postmeta rather than transients. Postmeta is per-post, survives an object-cache flush, is easy to purge one degree at a time, and lets us keep serving the last good copy when the Search Service is down.

- **Fresh copy.** It is served as-is. The default lifetime is 24 hours, which is an estimate we will tune once we see how often deadlines and tuition actually change. The lifetime is a setting.
- **Stale copy.** It is served anyway, and a `ucf_degree_refresh_program` single event is scheduled for that post. A short lock transient keeps a traffic spike from queuing duplicate refreshes.
- **No copy at all.** We fetch synchronously with a 5-second timeout, under the same lock. This should be rare, because the importer fills the cache.
- **Failed fetch.** The previous copy stays in place, the error is recorded, and we wait 15 minutes before trying that post again.
- **Schema or source change.** A copy whose `schema_version` doesn't match the code, or whose `source` doesn't match the configured service, is treated as stale.
- **Background sweep.** An hourly cron job refreshes the oldest stale copies in batches of 50 (configurable).

### Access points

- **REST.** `GET ucf-degree/v1/degrees/<id>/program` returns the normalized data for editor previews, and `POST …/program/refresh` forces a refresh. Both require `edit_post`. A read-only `program` field on `/wp/v2/degrees` exposes the same data publicly.
- **WP-CLI.** `wp degrees cache refresh [--post=<id>] [--all]` refreshes copies. `wp degrees cache status` reports how old the copies are, which service they came from, and which posts have errors.
- **Block Bindings.** A `ucf-degree/program` source has a server-side `get_value_callback` and a matching JS `registerBlockBindingsSource`. It binds single values (short name, credit hours, catalog URL, apply URL, tuition, outcome and projection figures) to core Paragraph, Heading, Button and Image blocks in the template. It is the right tool wherever a value sits inside editorial layout and nothing needs to hide when the value is empty.

What follows from this section: Phase 2 builds the data layer in full before any block depends on it.

## Blocks

Blocks use the `ucf-degree` namespace and `block.json` apiVersion 3. They are built with `@wordpress/scripts` from `src/blocks/<name>/` into `build/`, and registered from a manifest with `wp_register_block_types_from_metadata_collection()`. Every block uses `postId` and `postType` context, sets `supports.html: false`, and previews its data in the editor through the REST endpoint. In the Site Editor, where no degree is in context, a block shows labeled placeholder content instead. Unless the table says otherwise, a block renders nothing, heading included, when its data is empty. A `render_block_ucf-degree/{name}` filter is available when a site needs to change markup.

| Block | Data | Key attributes |
|---|---|---|
| `program-details` | `program_types`, `colleges`, `departments`, `ucf_degree_hours`, `ucf_degree_online` | `show` (which facts), `linkTerms` |
| `tuition` | cache `tuition`, `ucf_degree_tuition_skip` | `heading`, `disclaimer` (default from settings) |
| `description` | cache `descriptions` | `source`: catalog / full / custom / preferred, with fallback; `expandFull` shows the full catalog description in a `<details>` |
| `highlights` | cache `highlights` | `heading` |
| `deadlines` | cache `deadlines` | `heading`, `groupOrder`, `layout` (tabs or stacked), `disclaimer`; inner blocks render when there are no deadlines |
| `application-requirements` | cache `requirements` | `heading`; inner blocks render when there are no requirements |
| `outcomes` | cache `outcomes` | `heading`, `showAcademicYear` |
| `projections` | cache `projections` | `heading`, `showYearRange` |
| `careers` | cache `careers` | `heading`, `limit` (20), `randomize` |
| `quote` | cache `quotes` | `index` |
| `subplans` | child degree posts | `heading`, `showExcerpt` |
| `apply-button` | program type + settings | `text` |
| `request-info` | Slate IDs + settings | `text`; renders the button and an Interactivity API `<dialog>` containing the form |

A few notes on specific blocks:

- **Deadlines and requirements are separate blocks.** Both come from the same `application_deadlines` resource, so one fetch serves both. Pages show them in different sections, so each gets its own block.
- **Outcomes and projections keep v3's number formatting.** Earnings render as currency, rates as whole percentages, the change percentage to two decimals, and counts with thousands separators. They stay blocks, rather than bound paragraphs, because a degree without that data has to drop the whole section.
- **The fallback content inside `deadlines` and `application-requirements` is shared.** It lives in the site template, so it is the same for every degree and editable in one place. That replaces v3's per-level fallback sections.
- **JSON-LD moves into the plugin.** It is printed in `wp_head` from the cache, and a `ucf_degree_output_json_ld` filter lets a site turn it off.

What follows from this section: each block ships on its own, in the order set in Phase 4.

## Templates and patterns

The plugin registers two templates with `register_block_template()` from HTML files in `templates/`:

- **`ucf-degree//single-degree`.** It is chosen automatically for every degree through the template hierarchy. The sections run in this order: header (post title, `program-details` summary, `apply-button`, `request-info`), Program at a Glance (`program-details`, `tuition`, plus shared badges), `description` with `highlights` and `subplans`, the `core/post-content` slot, `deadlines` with `application-requirements` and `apply-button`, `outcomes` and `projections`, `careers`, and `quote`.
- **`ucf-degree//single-degree-custom`.** It shows the post title and `core/post-content` only, and is registered with `post_types => [ 'degree' ]` so editors can pick it for an individual degree.

Both templates reference the theme's `header` and `footer` template parts. A block theme can replace either template by shipping a file with the same slug, and changes made in the Site Editor take precedence over both. **Content that is the same on every degree lives directly in the template.** That covers default badges, the Start Your Application section, shared deadline fallback copy and the UCF Online callout. Editing the template once updates every degree, which is the job v3's theme mods did.

Editorial patterns are registered from `patterns/` with `postTypes: [ 'degree' ]` and `blockTypes: [ 'core/post-content' ]`, so they appear in the starter-pattern picker when someone creates or opens an empty degree. They are:

- **Course Overview:** headings and copy, plus a catalog button bound to `catalog_url`
- **Skills:** a heading and a core List
- **Admission Requirements:** two columns plus `request-info`
- **Testimonial:** a core Quote with an image, for curated quotes beyond the Search Service ones
- **News and Spotlight:** `ucf-news/news-feed` with the degree's topic, plus a core Shortcode block for `[ucf-spotlight]`, since the Spotlights plugin has no block

The post type sets no `template` argument. Imported degrees are created with empty `post_content` and render fully through the site template.

What follows from this section: the plugin's templates have to look right in the new theme, so Phase 5 is done jointly with the theme work.

## Settings

The Settings › UCF Degrees screen is rebuilt around the new options:

- **Search Service.** Base URL and API key (read-only when set by a constant, with the source shown), a "Test connection" button, and an optional import filter.
- **Application and RFI.** Undergraduate and graduate application URLs, plus RFI base URLs and form IDs.
- **Disclaimers and ordering.** The tuition disclaimer, the undergraduate and graduate deadline disclaimers, and the deadline group order for each level.
- **Writebacks.** The profile (permalink) writeback and the tuition-exception call, both **off by default** (see the next section).
- **Cache.** Cache lifetime and sweep batch size.

## Importer and writebacks

- **Lean meta only.** The importer writes the keys above with `update_post_meta()`, fills the cache from the program object and its linked resources in the same pass, and reuses the careers response for the `career_paths` terms. New posts are inserted with empty `post_content`. The importer matches existing posts on `ucf_degree_id`, and it finds a subplan's parent by plan code, not by Search Service ID.
- **The filters get a documented contract.** `ucf_degree_get_post_metadata`, `ucf_degree_get_post_terms`, `ucf_degree_formatted_post_data` and `ucf_degree_api_results` stay as extension points. They operate on the v4 keys, and the docs say so.
- **The description writeback is removed.** It PUT `post_content` to the Search Service, which in v4 would be block markup, and descriptions now come from the Search Service anyway.
- **Writebacks are only as safe as the service they target.** Pointing a development site at a non-production service, which is the reason the URL is configurable, makes writebacks safe to test there. The risk that remains is a non-production WordPress writing to the production service, which would point a program's profile URL at a development address or change shared tuition exceptions. So both writebacks default to off, and the settings screen shows the target host next to each switch. A `UCF_DEGREE_ALLOW_WRITEBACKS` constant must also be `true` before either one runs, which means a database cloned from production can't carry writebacks into another environment.
- **The metabox becomes an editor panel.** The classic "Degree Import Options" metabox is replaced by a `PluginDocumentSettingPanel` with ignore, skip tuition, lock excerpt, undergraduate Slate ID, cache status with a refresh button, and read-only identity fields.

## What v4 removes

v4 removes everything that only existed for the classic site:

- **ACF.** That includes every `update_field()` and `get_field()` call and the dependency itself.
- **Shortcodes, layouts and widgets.** `[ucf-degree-list]`, `[career-paths]`, the classic and two-column list layouts with their CSS, and the empty widget file all go. On the new site, degree lists are Query Loops showing `program-details`.
- **The gulp toolchain.** It is replaced by `@wordpress/scripts`.
- **The `degree_meta` REST field.** It returned every postmeta row, and the `program` field replaces it.
- **The description writeback and its `update_desc` and `desc_type` options.**

The degree search REST routes (`/degrees`, `/program-types`, `/colleges`, `/interests`, `/tags` and their counts) stay, updated to the new meta names, because a degree search on the new site will need them.

The current site and the other properties (Online, Colleges) stay on 3.x, pinned with `^3` in Composer. We keep a `v3` maintenance branch for security fixes until those sites move.

## Phases

1. **Foundation.** Strip out the items listed under "What v4 removes", set up `@wordpress/scripts`, build `UCF_Degree_Search_Service` with its constant and option resolution and `wp degrees service`, register the new meta, make REST unconditional for the post type, and build the sidebar panel and settings screen. Bump the minimum to WordPress 6.8. *Done when:* a degree opens in the block editor on a block theme, the panel saves the import flags, and `wp degrees service` reports the right service and source for both a constant and an option.
2. **Data layer.** Build the program data service, cache, cron sweep, REST routes, WP-CLI commands and bindings source. *Done when:* `wp degrees cache refresh --all` fills every degree and `cache status` reports no errors.
3. **Importer.** Switch to lean meta, move the theme's former responsibilities into the plugin, fill the cache during import, and remove the description writeback. *Done when:* a fresh import from a non-production service into an empty site produces degrees that carry only v4 keys and a full cache from that host. Switching the configured service then refetches every degree from the new host and re-matches them by plan and subplan code.
4. **Blocks.** Build them in this order: `program-details`, `tuition`, `description`, `deadlines`, `application-requirements`, `apply-button`, `request-info`, `highlights`, `careers`, `outcomes`, `projections`, `quote`, `subplans`, then JSON-LD. *Done when:* each block renders from the cache in the post editor and on the front end, shows placeholder content in the Site Editor, drops out entirely when its data is empty, and takes styles from `theme.json`.
5. **Templates and patterns.** Add both templates and the starter patterns, done with the new theme in hand. *Done when:* an untouched imported degree renders a complete page, and an editor can add a starter pattern to a new degree.
6. **Release.** Update the readme, Composer version and changelog, and document the meta keys, filters and the `ucf_degree_get_program()` helper for other teams.

## Open questions

- **Which source owns tuition?** The Search Service provides tuition, but UCF-Tuition-Fees-Plugin also writes it into postmeta on the current site. We need to know whether the new site runs that plugin before the `tuition` block reads only from the cache.
- **What do the new theme's template parts look like?** We need the `header` and `footer` slugs, and whether the theme's `theme.json` will style the `ucf-degree/*` blocks or expects the plugin to ship more than structural CSS.
- **Do we still need program length and per-degree badges?** v3 lets editors set both per degree. If the new design keeps them, they become editorial meta bound into the template. If not, we drop them.
- **Will the new site use Relevanssi?** If it does, we add cached descriptions, highlights and careers to the index through `relevanssi_content_to_index`, because they no longer live in postmeta.
- **What are the new platform's WordPress and PHP versions?** They set the minimums we declare.
- **When should the writebacks turn on?** We need a launch-day plan for switching the Search Service's profile URLs from the current site to the new one.
- **Will the Search Service add exact lookup by code and `expand`, and when?** The plugin doesn't wait on either. Knowing the timeline tells us how long the search-and-verify fallback has to carry production.
- **Does every subplan share its parent's plan code?** Parent matching depends on it. We should check it against the full program list before Phase 3.
- **Is the `career_paths` taxonomy needed on the new site beyond display?** If not, careers can come only from the cache and the taxonomy can go.
