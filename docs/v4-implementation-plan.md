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

## Data model

### Postmeta

- **Identity.** `ucf_degree_id` (plan and subplan code), `ucf_degree_api_id`, `ucf_degree_plan_code` and `ucf_degree_subplan_code` tie a post to its Search Service program.
- **Listing and search.** `ucf_degree_name_short`, `ucf_degree_hours` and `ucf_degree_online` feed the degree search API and Query Loops, which work across hundreds of degrees and can't fetch each one from the Search Service.
- **Import controls.** `ucf_degree_import_ignore`, `ucf_degree_tuition_skip` and `ucf_degree_lock_excerpt` keep v3's behavior.
- **Editorial values the template needs.** `ucf_degree_undergraduate_slate_id` is the only one today. It feeds `request-info`, which lives in the template, and the Search Service only provides graduate Slate IDs. Any further per-degree values that a template slot needs are added the same way and exposed through the core `core/post-meta` bindings source.
- **Cache.** `_ucf_degree_program` holds the normalized Search Service data and `_ucf_degree_program_fetched` records when we fetched it. A failed fetch writes `_ucf_degree_program_error`.

Every key except the cache keys is registered with `register_post_meta()`, with a type, `single`, `show_in_rest` and an `edit_post` auth callback, so the editor sidebar can read and set them. Title, slug, excerpt (from the program's `excerpt` unless it is locked) and parent (for subplans) stay post fields.

The plugin keeps registering the `program_types`, `career_paths` and `interests` taxonomies. `colleges` and `departments` come from UCF-Colleges-Tax-Plugin and UCF-Departments-Tax-Plugin, and the importer already treats them as optional. Credit hours, excerpt, interests and career paths were all set by Main-Site-Theme's filter in v3. In v4 the plugin sets them itself.

### Program data service

A new `UCF_Degree_Program_Data` class (`includes/class-ucf-degree-program-data.php`) is the only code that talks to the Search Service for display data. The public helper `ucf_degree_get_program( $post = null )` returns a normalized array:

```
schema_version, fetched_at
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

A fetch calls `programs/{api_id}/`, then follows the program's `outcomes`, `projection_totals`, `application_deadlines` and `careers` URLs, plus `marketing/quotes/{id}/` for each quote. That is about six requests per degree. A `ucf_degree_program_data` filter runs on the normalized array.

### Cache policy

We cache in postmeta rather than transients. Postmeta is per-post, survives an object-cache flush, is easy to purge one degree at a time, and lets us keep serving the last good copy when the Search Service is down.

- **Fresh copy.** It is served as-is. The default lifetime is 24 hours, which is an estimate we will tune once we see how often deadlines and tuition actually change. The lifetime is a setting.
- **Stale copy.** It is served anyway, and a `ucf_degree_refresh_program` single event is scheduled for that post. A short lock transient keeps a traffic spike from queuing duplicate refreshes.
- **No copy at all.** We fetch synchronously with a 5-second timeout, under the same lock. This should be rare, because the importer fills the cache.
- **Failed fetch.** The previous copy stays in place, the error is recorded, and we wait 15 minutes before trying that post again.
- **Schema change.** A copy whose `schema_version` doesn't match the code is treated as stale.
- **Background sweep.** An hourly cron job refreshes the oldest stale copies in batches of 50 (configurable).

### Access points

- **REST.** `GET ucf-degree/v1/degrees/<id>/program` returns the normalized data for editor previews, and `POST …/program/refresh` forces a refresh. Both require `edit_post`. A read-only `program` field on `/wp/v2/degrees` exposes the same data publicly.
- **WP-CLI.** `wp degrees cache refresh [--post=<id>] [--all]` and `wp degrees cache status` report how old the copies are and which posts have errors.
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

- **Search Service.** Base URL, API key, and an optional import filter.
- **Application and RFI.** Undergraduate and graduate application URLs, plus RFI base URLs and form IDs.
- **Disclaimers and ordering.** The tuition disclaimer, the undergraduate and graduate deadline disclaimers, and the deadline group order for each level.
- **Writebacks.** The profile (permalink) writeback and the tuition-exception call, both **off by default** (see the next section).
- **Cache.** Cache lifetime and sweep batch size.

## Importer and writebacks

- **Lean meta only.** The importer writes the keys above with `update_post_meta()`, fills the cache from the program object and its linked resources in the same pass, and reuses the careers response for the `career_paths` terms. New posts are inserted with empty `post_content`.
- **The filters get a documented contract.** `ucf_degree_get_post_metadata`, `ucf_degree_get_post_terms`, `ucf_degree_formatted_post_data` and `ucf_degree_api_results` stay as extension points. They operate on the v4 keys, and the docs say so.
- **The description writeback is removed.** It PUT `post_content` to the Search Service, which in v4 would be block markup, and descriptions now come from the Search Service anyway.
- **The profile and tuition-exception writebacks change the shared Search Service.** The new site imports from the same Search Service as the current one. If a development or staging copy has the profile writeback on, saving a degree points the Search Service's profile URL for that program at a non-production address. The tuition-exception call changes shared data the same way. That is why both default to off: we turn them on only on production, and only once the new site is the one that should own those URLs.
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

1. **Foundation.** Strip out the items listed under "What v4 removes", set up `@wordpress/scripts`, register the new meta, make REST unconditional for the post type, and build the sidebar panel and settings screen. Bump the minimum to WordPress 6.8. *Done when:* a degree opens in the block editor on a block theme, and the panel saves the import flags.
2. **Data layer.** Build the program data service, cache, cron sweep, REST routes, WP-CLI commands and bindings source. *Done when:* `wp degrees cache refresh --all` fills every degree and `cache status` reports no errors.
3. **Importer.** Switch to lean meta, move the theme's former responsibilities into the plugin, fill the cache during import, and remove the description writeback. *Done when:* a fresh import into an empty site produces degrees that carry only v4 keys, have a full cache, and leave the writebacks off.
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
- **Is the `career_paths` taxonomy needed on the new site beyond display?** If not, careers can come only from the cache and the taxonomy can go.
