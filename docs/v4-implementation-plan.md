# UCF Degree CPT v4.0.0: Implementation Plan

Draft · `rc-v4.0.0` · November 20, 2026

## What changes in v4

Moving degree pages to blocks is only part of v4. The bigger change is **where degree data lives**. Today, every import copies the Search Service into WordPress. By our count of the ACF export, that is about 30 imported values per degree, and most of them are written by Main-Site-Theme's `ucf_degree_get_post_metadata` filter, not by this plugin. Alongside those sit about 35 editorial ACF fields, which are really page content stored as metadata. A degree page is a snapshot of the Search Service as of the last import, put together by theme templates that read both kinds of field back out.

In v4 this plugin provides the whole degree experience: the post type, a block template, blocks that render Search Service data, and patterns for everything editors write. Postmeta shrinks to the few keys we need to identify, list and search degrees. Search Service data is read when a page renders, through a cache. Editorial content moves into `post_content` as core blocks, and ACF stops being a dependency.

The rest of this document covers how we get there without losing the curated content editors have built up on existing degree pages.

## Decisions

- **Custom blocks are for data only.** A custom block exists only when it renders Search Service or taxonomy data. Everything an editor writes uses core blocks and patterns the plugin registers.
- **Blocks render on the server.** Every custom block is dynamic (`render.php`), gets the degree from `postId` block context, and saves no markup. A Search Service change appears on the next cache refresh without anyone re-saving a post, and the blocks also work inside a Query Loop on landing pages.
- **The Search Service is the source of truth, and postmeta is a cache of it.** We cache in postmeta rather than transients (reasons below), and a page render never waits on the Search Service when a cached copy exists.
- **The theme owns presentation.** A theme can override any block's render template with a file at `ucf-degree/blocks/{block}.php`. The plugin's defaults use Athena markup, so Main-Site-Theme works without overrides, and the Online and Colleges themes can keep their own markup.
- **The block template is the post type's `template` argument.** New degrees start with the standard blocks, and editors can rearrange them. Main-Site-Theme is a classic theme, so a site-level `single-degree` block template is a later option and not part of 4.0.

## Data model

### What stays in postmeta

- **Identity.** `degree_id`, `degree_api_id`, `degree_plan_code` and `degree_subplan_code` tie a post to its Search Service program, and the importer and tuition-exception calls rely on them.
- **Listing and search.** `degree_name_short`, `degree_hours` and `degree_online` feed the degree search API and lists, which query hundreds of degrees at once and can't fetch each one from the Search Service.
- **Import controls.** `degree_import_ignore`, `degree_tuition_skip` and `degree_lock_excerpt` stay as they are.
- **Cache.** `_ucf_degree_program` holds the normalized Search Service data, and `_ucf_degree_program_fetched` records when we fetched it. Both are underscore-prefixed so they stay hidden in the custom fields UI.

All of these are registered with `register_post_meta()`, with a type, `single`, `show_in_rest` and an `edit_post` auth callback, so the editor sidebar can read and set them. Title, slug, excerpt and parent (for subplans) stay post fields. The taxonomies `program_types`, `colleges`, `departments`, `interests` and `career_paths` stay too.

Four things the theme filter does today move into the plugin: `degree_hours`, the excerpt (still respecting the lock), interests from `subarea_of_interest`, and career paths from the careers endpoint. Today a site without Main-Site-Theme gets none of them.

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

A fetch calls `programs/{degree_api_id}/`, then follows the program's `outcomes`, `projection_totals`, `application_deadlines` and `careers` URLs, plus `marketing/quotes/{id}/` for each quote. That is about six requests per degree. The class only defines these fetches; the importer already makes the same calls through the theme filter. Storing raw numbers and formatting them at render also lets us drop the regex that the JSON-LD code uses to parse tuition back out of a formatted string. A `ucf_degree_program_data` filter runs on the normalized array.

### Cache policy

We cache in postmeta, not transients. Postmeta is per-post, survives an object-cache flush, is easy to purge one degree at a time, and lets us keep serving the last good copy when the Search Service is down.

- **Fresh copy.** It is served as-is. The default lifetime is 24 hours, which is an estimate we should tune once we see how often deadlines and tuition actually change. The lifetime is a setting.
- **Stale copy.** It is served anyway, and a `ucf_degree_refresh_program` single event is scheduled for that post. A short lock transient keeps a traffic spike from queuing duplicate refreshes.
- **No copy at all.** We fetch synchronously with a 5-second timeout, under the same lock. This should be rare, because the importer and the migration both fill the cache.
- **Failed fetch.** The previous copy stays in place. We record the error in `_ucf_degree_program_error` and wait 15 minutes before trying that post again.
- **Schema change.** A copy whose `schema_version` doesn't match the code is treated as stale, so changing the normalized shape never requires a manual purge.
- **Background sweep.** An hourly cron job refreshes the oldest stale copies in batches of 50 (configurable), so pages nobody visits don't fall far behind.

### Access points

- **REST.** `GET ucf-degree/v1/degrees/<id>/program` returns the normalized data for editor previews, and `POST …/program/refresh` forces a refresh. Both require `edit_post`. A read-only `program` field on `/wp/v2/degrees` keeps the data available to REST consumers once `degree_meta` shrinks.
- **WP-CLI.** `wp degrees cache refresh [--post=<id>] [--all]` and `wp degrees cache status` report how old the copies are and which posts have errors.
- **Block Bindings.** A `ucf-degree/program` bindings source has a server-side `get_value_callback` and a matching JS `registerBlockBindingsSource`. It exposes single values (short name, credit hours, catalog URL, apply URL, tuition, outcome and projection figures) to core Paragraph, Heading, Button and Image blocks. We use it wherever a value sits inside editorial layout and nothing needs to hide when the value is empty.

### Search

Degree search runs through Relevanssi. If the production index includes the description meta keys we are dropping (we haven't checked), search results would get worse after migration. To avoid that, we add the cached descriptions, highlights and careers to the index through `relevanssi_content_to_index` and reindex after migration.

What follows from this section: Phase 2 builds the data layer in full before any block depends on it.

## Blocks

Blocks use the `ucf-degree` namespace and `block.json` apiVersion 3. They are built with `@wordpress/scripts` from `src/blocks/<name>/` into `build/`, and registered from a manifest with `wp_register_block_types_from_metadata_collection()`. Every block uses `postId` and `postType` context and sets `supports.html: false`. Its edit component previews the data from the REST endpoint. Unless the table says otherwise, a block renders nothing when its data is empty. The `allowed_block_types_all` filter keeps them out of the inserter on other post types, but they render anywhere that provides `postId` context.

| Block | Data | Key attributes | Replaces |
|---|---|---|---|
| `program-details` | `program_types`, `colleges`, `departments`, `degree_hours`, `degree_online` | `show` (which facts), `linkTerms` | Program at a Glance details column |
| `tuition` | cache `tuition`, `degree_tuition_skip` | `disclaimer` (default from settings) | Glance tuition, `degree_*_tuition`, `tuition_disclaimer` theme mod |
| `description` | cache `descriptions` | `source`: catalog / full / custom / preferred, with fallback | `degree_description`, `degree_description_full`, imported `modern_description_copy`, catalog modal |
| `highlights` | cache `highlights` | `heading` | `highlights_imported` |
| `deadlines` | cache `deadlines` | `groupOrder`, `disclaimer`; inner blocks render when there are no deadlines | `degree_application_deadlines`, alternate and fallback sections, `disable_deadlines` |
| `application-requirements` | cache `requirements` | `heading`; inner blocks render when there are no requirements | `degree_application_requirements` |
| `outcomes` | cache `outcomes` | `heading`, `showAcademicYear` | `degree_outcome_academic_year`, `degree_avg_annual_earnings`, `degree_employed_full_time`, `degree_continuing_education` |
| `projections` | cache `projections` | `heading`, `showYearRange` | `degree_prj_*` |
| `careers` | cache `careers` | `limit` (20), `randomize` | `career_paths` list, `degree_career_list` |
| `quote` | cache `quotes` | `index` | `quotes_imported_id`, imported `degree_quotes` |
| `subplans` | child degree posts | `showExcerpt` | `show_subplan_list` |
| `apply-button` | program type + settings | `text` | `get_degree_apply_button()`, `degrees_*_application` theme mods |
| `request-info` | Slate IDs + settings | `slateId` (undergraduate), `formId` override, `text` | RFI ACF tab, `rfi_modal.php`, `degree_show_rfi()` |

A few notes on specific blocks:

- **Deadlines and requirements are separate blocks.** Both come from the same `application_deadlines` resource, so one fetch serves both. Pages show them in different sections, so each gets its own block.
- **Outcomes and projections keep today's number formatting.** Earnings render as currency, rates as whole percentages, the change percentage to two decimals, and counts with thousands separators, matching `main_site_format_degree_data()`. They stay blocks, rather than bound paragraphs, because a degree without outcome data has to drop the whole section.
- **`request-info` renders its own modal.** The default is a Bootstrap 4 modal (themes can override it), so the button no longer depends on a modal the theme prints separately.
- **JSON-LD moves into the plugin.** It is printed in `wp_head` from the cache, and a `ucf_degree_output_json_ld` filter lets a site turn it off.

What follows from this section: each block ships on its own, in the order set in Phase 4.

## Patterns and template

The plugin registers patterns from `patterns/` under a "Degree" category, one for each section of today's page:

- **Program at a Glance:** `program-details`, program length, `tuition` and badges
- **Description:** heading, `description`, `subplans`, plus an image or `highlights` column
- **Deadlines and Apply:** `deadlines`, `apply-button` and `application-requirements`
- **Start Your Application**
- **Course Overview:** headings and copy, plus a catalog button bound to `catalog_url`
- **Skills and Careers:** a core List for skills and the `careers` block
- **Outcomes and Projections**
- **Admission Requirements:** two columns plus `request-info`
- **UCF Online Callout**
- **News and Spotlight:** `ucf-news/news-feed` and a core Shortcode block for `[ucf-spotlight]`, since the Spotlights plugin has no block

**Synced patterns replace the theme-mod fallbacks** for default badges and the undergraduate and graduate sidebar promos. Editing one synced pattern updates every degree that uses it, which is what those theme mods do today.

The post type's `template` argument lists these patterns in the order `single-degree.php` uses now, with `template_lock` off. One catch: the editor only applies `template` to new auto-drafts, and imported degrees are created programmatically with empty content. So the importer writes the serialized template into `post_content` when it inserts a new degree, and the migration does the same for existing ones. Otherwise an imported degree would render a blank page.

What follows from this section: once every degree carries its own content, `template-degree-custom.php` becomes redundant and the theme can retire it.

## Settings

The Settings › UCF Degrees screen gains the values that currently live in Main-Site-Theme theme mods:

- **Application and RFI URLs.** Undergraduate and graduate application URLs, plus RFI base URLs and form IDs.
- **Disclaimers and ordering.** The tuition disclaimer, the undergraduate and graduate deadline disclaimers, and the deadline group order for each level.
- **Cache.** Cache lifetime and sweep batch size.

`update_desc` and `desc_type` are removed (see the next section). The migration copies the current values out of `theme_mods_Main-Site-Theme`, so nobody re-enters them by hand.

## Importer and writeback changes

- **Lean meta only.** The importer writes with `update_post_meta()`, and every `update_field()` call is removed. This is what drops the ACF dependency.
- **Theme responsibilities move into the plugin.** Credit hours, excerpt, interests and career paths are handled by the plugin, as described under the data model.
- **The import fills the cache.** The importer already has the program object, so it fetches the linked resources once, writes the cache, and reuses the careers response for the `career_paths` terms.
- **New degrees get the template.** Inserted posts receive the serialized template as `post_content`.
- **Existing filters stay.** `ucf_degree_get_post_metadata`, `ucf_degree_get_post_terms`, `ucf_degree_formatted_post_data`, `ucf_degree_api_results` and the rest stay in place for back-compat. We document that the metadata filter now applies only to the lean keys.
- **The description writeback goes away.** With `update_desc` on, every save PUTs `post_content` to the Search Service as a description. Once `post_content` is block markup, that would push serialized blocks into the Search Service, and descriptions now come from the Search Service anyway. The profile (permalink) writeback and the tuition-exception call stay.
- **REST is always on for the post type.** `show_in_rest` becomes unconditional because the block editor requires it. The REST setting only controls the plugin's custom routes.
- **The metabox becomes an editor panel.** The classic "Degree Import Options" metabox is replaced by a `PluginDocumentSettingPanel` with ignore, skip tuition, lock excerpt, cache status with a refresh button, and read-only identity fields.
- **Minimum versions go up.** The plugin will require WordPress 6.8 because of the metadata-collection registration. The PHP minimum still needs to be checked against production.

## Theme and other consumers

v4 is a breaking release. Sites on 3.x stay there, pinned with `^3` in Composer, until they migrate.

Main-Site-Theme needs a coordinated release. It removes `mainsite_degree_format_post_data`, `mainsite_degree_get_post_terms` and `mainsite_degree_formatted_post_data`; while they exist, they keep writing the keys we are dropping. `single-degree.php` shrinks to `the_content()` plus theme chrome (header, breadcrumbs, colleges grid), and most of `template-parts/degree/` goes away. The release also removes the theme's JSON-LD output, the Degree Fields ACF groups and the degree theme mods. Ideally it adds `add_editor_style()` so editor previews match the front end.

The Online-Child-Theme, Online-Utilities, Colleges-Theme and UCF-Tuition-Fees-Plugin all read or write keys v4 drops, including `degree_pdf`, tuition, outcomes, projections, deadlines and requirements. When they adopt v4, they switch to `ucf_degree_get_program()`.

We render application requirements on the Main Site today, but that rendering isn't in the Main-Site-Theme source: no branch of the theme reads `degree_application_requirements`. **Before the migration runs, we need to find where requirements come from now**, whether a Section post, another plugin or something else, so the new block replaces that source rather than duplicating it.

## Migration

`wp degrees migrate [--dry-run] [--post=<id>] [--limit=<n>]` converts each degree as follows:

1. Skip degrees whose `_ucf_degree_migrated` is already `4.0.0`.
2. Fill the cache.
3. For degrees on `template-degree-custom.php`, keep `post_content`, clear the page template, and carry `degree_custom_enable_rfi` over to a `request-info` block.
4. For all other degrees, build `post_content` from the template, filling in their ACF values according to the mapping below and leaving out any section that has no values.
5. Set `_ucf_degree_migrated` to `4.0.0`. Legacy meta is not deleted at this step.

With `--dry-run`, the command prints a per-degree summary and writes each degree's generated markup to a directory so we can diff it.

How existing fields map:

- **Program length and badges.** They become core Image and Paragraph blocks in the glance section. Degrees with no badges get the default-badges synced pattern.
- **Curated description copy.** If the local `modern_description_copy` matches the Search Service custom description, it becomes a `description` block with `source: custom`. If it differs, an editor wrote it locally, and it goes into a Classic block. Converting arbitrary WYSIWYG HTML into native blocks is lossy, so editors convert those by hand.
- **Highlights.** Manual highlights with images become a columns pattern, and imported ones become the `highlights` block.
- **Deadlines.** `disable_deadlines` leaves the block out. An alternate section becomes a `ucf-content/section` block, and manual deadlines become a core Table in place of the `deadlines` block.
- **Editorial sections.** Start your application, course overview, skills, admission copy and the Online callout become their patterns, filled in with the existing values.
- **Careers and quotes.** A manual career list becomes a core List, and otherwise we use the `careers` block. An imported quote becomes the `quote` block, and manual quotes become core Quote blocks.
- **News, spotlight and RFI.** News becomes `ucf-news/news-feed` with the existing topic, the spotlight becomes a Shortcode block, and the RFI fields become `request-info` attributes.
- **Page header and modals.** These belong to the theme and are not touched.

`wp degrees cleanup-legacy-meta [--dry-run]` deletes the old keys after sign-off. That includes ACF's `_field` reference rows and the flattened repeater rows such as `degree_application_deadlines_0_deadline`.

Before the production run, we check a set of degrees side by side on staging, comparing the old and new versions of each page. The set covers a bachelor's, a minor, an undergraduate certificate, a master's, a doctorate, a graduate certificate, a subplan, a custom-template degree, an import-ignored degree, a tuition-skip degree, one with manual deadlines, one with an alternate deadlines section, and one with locally curated description copy.

## Phases

1. **Foundation.** Set up `@wordpress/scripts` and the `src/blocks` layout, register meta, make REST unconditional, build the sidebar panel, and add the new settings. *Done when:* a degree opens in the block editor locally and the panel saves the import flags.
2. **Data layer.** Build the program data service, cache, cron sweep, REST routes, WP-CLI commands, bindings source and Relevanssi hook. *Done when:* `wp degrees cache refresh --all` fills every degree, `cache status` reports no errors, and the existing front end is unchanged.
3. **Importer.** Switch to lean meta, move the theme's responsibilities into the plugin, fill the cache during import, remove the description writeback, and write the template on insert. *Done when:* a fresh import into an empty database produces degrees that carry only lean keys and have a full cache.
4. **Blocks.** Build them in this order: `program-details`, `tuition`, `description`, `deadlines`, `application-requirements`, `apply-button`, `request-info`, `highlights`, `careers`, `quote`, `subplans`, `outcomes`, `projections`, then JSON-LD. *Done when:* each block renders from the cache in the editor and on the front end, renders nothing when its data is empty, and can be overridden by the theme.
5. **Patterns and template.** Add the section patterns, synced patterns and post type template. *Done when:* a new degree opens with the full page and renders like a current degree page.
6. **Migration and theme release.** Run the migration on staging, check the sample, then ship the Main-Site-Theme release and run the migration in production. Run `cleanup-legacy-meta` only after at least one full import cycle in production.
7. **Release.** Update the readme, Composer version and changelog, and write an adoption guide for the Online and Colleges teams.

The degree list shortcode, the `[career-paths]` shortcode and the list layouts are not changed in 4.0. We deprecate them in a later 4.x release, once we know which sites still use them.

## Open questions

- **Where do application requirements render today?** This must be answered before Phase 6 (see above).
- **Which source of truth owns tuition?** The Search Service provides tuition, but UCF-Tuition-Fees-Plugin also writes it into postmeta. We need to confirm which one is authoritative before the `tuition` block reads only from the cache.
- **Is `update_desc` enabled on any site?** If so, that site relies on WordPress-authored descriptions reaching the Search Service, and it needs another way to author them.
- **Does the production Relevanssi index include degree meta?** The answer decides how much indexing work Phase 2 needs.
- **What are the production WordPress and PHP versions?** They set the minimums we declare.
- **When do Online and Colleges adopt v4?** If it is the same window, we coordinate one release. If not, they stay on 3.x and we keep the v3 branch patched.
- **Is the `career_paths` taxonomy used beyond display?** If not, it can later be served from the cache like everything else.
