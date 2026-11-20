# UCF Degree CPT v4.0.0: Implementation Plan

Draft · `rc-v4.0.0` · November 20, 2026

## What v4 is

v4 is not an upgrade path for the current site. It is a clean break: a degree plugin for the new website, which runs on a block theme. Nothing in v4 has to keep working on Main-Site-Theme. We will not migrate existing degree content into it, and any later migration will be a separate project that follows the rules this release sets.

That frees us from the parts of v3 that only exist for history. Today an import copies about 30 Search Service values per degree into ACF fields, most of them written by Main-Site-Theme's import filter rather than by this plugin. About 35 more ACF fields hold editorial content, and theme templates put the page together from both. In v4 the plugin provides the whole degree experience: the post type, a default layout that every new degree starts from, the blocks that render Search Service facts, and patterns for editorial content. Postmeta shrinks to what we need to identify, list and search degrees. Facts such as tuition and deadlines are read from the Search Service when a page renders, through a cache. Program text such as the description is copied into the page once, when the degree is created, and belongs to WordPress from then on. ACF is no longer a dependency.

The rest of this document describes that end state and the order we build it in.

## Decisions

- **Every degree owns its layout.** When a degree is created, whether by the importer or by Add New, a default layout is written into its `post_content`. From then on, editors can reorder, remove, duplicate or add any block on that degree. The site template is only a frame around the content.
- **Facts stay live, and program text is content.** The Search Service holds two kinds of information. Facts are credit hours, tuition, deadlines, application requirements, outcomes and projections. Program text is the description, highlights, testimonials and careers. Facts render through dynamic blocks that keep showing current Search Service data wherever an editor moves them. Program text is written into the degree as ordinary core blocks when the degree is created. From then on it's edited like any other page content, and the Search Service never updates it again.
- **The layout is copied once.** Imports after the first never touch `post_content`, so nothing an editor writes or rearranges is overwritten.
- **Some facts can be overridden.** Credit hours, tuition and deadlines accept a value entered in WordPress, which replaces the Search Service value on that degree until someone clears it. Some programs need a range of credit hours, or a deadline exception that the Search Service data can't express. An override covers those cases without turning the whole block into static text.
- **Custom blocks are for data only.** A custom block exists only when it renders Search Service facts or taxonomy data. Each data block renders its own section heading, so a degree with no outcomes or deadlines drops the whole section and doesn't leave an empty heading behind.
- **Presentation follows block-theme conventions.** Blocks declare block supports (color, spacing, typography, border) and are styled through `theme.json` `styles.blocks`. The plugin ships only structural CSS. Interactive pieces, such as the request-info dialog and deadline tabs, use the Interactivity API with a native `<dialog>` element rather than Bootstrap.
- **The Search Service is the source of truth for facts, and postmeta is a cache of it.** A page render never waits on the Search Service when a cached copy exists. WordPress is the source of truth for program text and for overrides.
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
- **Embedded related data.** An `expand` parameter on that endpoint (for example `?expand=outcomes,projections,deadlines,careers,quotes`) would return the related resources inline. That cuts a refresh from about four requests to one, and the one-time fetch at creation from about six, and it also makes the linked-host check above unnecessary.

`find_program()` tries the exact-lookup endpoint once per host and remembers whether it exists. If it does, the plugin switches over without a release or a setting. Until then it uses search-and-verify, and when `expand` is missing it falls back to following links.

What follows from this section: the Search Service changes are an improvement we can request now and pick up whenever they ship; nothing in this plan waits on them. `UCF_Degree_Search_Service` and `find_program()` are built in Phase 1, before the data layer, so that no later code can bypass them.

## Data model

### Postmeta

- **Identity.** `ucf_degree_plan_code` and `ucf_degree_subplan_code` identify a degree, and `ucf_degree_id` holds the two joined together for fast lookups. These codes are the same in every environment. The Search Service program ID is not stored as meta, because each environment assigns its own. It lives only inside the cache entry from the service that issued it.
- **Listing and search.** `ucf_degree_name_short`, `ucf_degree_hours` and `ucf_degree_online` feed the degree search API and Query Loops, which work across hundreds of degrees and can't fetch each one from the Search Service.
- **Import controls.** `ucf_degree_import_ignore` and `ucf_degree_tuition_skip` keep v3's behavior. v3's excerpt lock goes away, because the excerpt is now program text: it is set once at creation and never updated by an import.
- **Overrides.** `ucf_degree_hours_override` (a string, so it can hold a range such as "120–126"), `ucf_degree_tuition_override` (`resident`, `nonresident` and `unit`, in the cache's shape) and `ucf_degree_deadlines_override` (a list of `{ type, term, display }`, in the cache's shape). Each one is stored per degree rather than as a block attribute, so every place the value appears agrees: every block instance, bindings, JSON-LD and the degree search API. `_ucf_degree_override_basis` records the Search Service value each override replaced (see "Overrides").
- **Per-degree editorial values.** `ucf_degree_undergraduate_slate_id` is the only one today. The Search Service only provides graduate Slate IDs, and a degree can show `request-info` in more than one place, so the ID is a single value per degree rather than an attribute on each block. Any other value shared by several blocks on one degree is added the same way and exposed through the core `core/post-meta` bindings source.
- **Layout tracking.** `_ucf_degree_layout_version` and `_ucf_degree_layout_hash` record which version of the default layout a degree was given, and a hash of the markup that was written (see "Changing the default layout later").
- **Cache.** `_ucf_degree_program` holds the normalized Search Service data and `_ucf_degree_program_fetched` records when we fetched it. A failed fetch writes `_ucf_degree_program_error`.

Every key except the cache keys is registered with `register_post_meta()`, with a type, `single`, `show_in_rest` and an `edit_post` auth callback, so the editor sidebar can read and set them. Title, slug, excerpt (from the program's `excerpt`, written only when the post is created) and parent (for subplans) stay post fields.

The plugin keeps registering the `program_types`, `career_paths` and `interests` taxonomies. `colleges` and `departments` come from UCF-Colleges-Tax-Plugin and UCF-Departments-Tax-Plugin, and the importer already treats them as optional. Credit hours, excerpt, interests and career paths were all set by Main-Site-Theme's filter in v3. In v4 the plugin sets them itself.

### Program data service

A new `UCF_Degree_Program_Data` class (`includes/class-ucf-degree-program-data.php`) is the only code that talks to the Search Service for facts. The cache holds facts only, and the public helper `ucf_degree_get_program( $post = null )` returns them as a normalized array:

```
schema_version, fetched_at, source (service host), api_id (as issued by source)
name, name_short, credit_hours, online, catalog_url, graduate_slate_id
tuition       { resident, nonresident, unit }          raw numbers; formatting happens at render
deadlines     [ { type, term, display } ]
requirements  [ string ]
outcomes      { academic_year, avg_annual_earnings, employed_full_time, continuing_education }
projections   { begin_year, end_year, begin_employment, end_employment,
                change, change_percentage, openings }
overridden    [ key ]                                  which values came from an override
```

The helper applies overrides before it returns, so every caller gets the value the page should show. `overridden` says which values those are, and the editor reads the raw cached value alongside so it can show both.

A refresh gets the program record by its cached ID, or through `find_program()` when there's no cached ID for the current host. It then follows the program's `outcomes`, `projection_totals` and `application_deadlines` URLs. That is about four requests per degree. A `ucf_degree_program_data` filter runs on the normalized array.

Program text is fetched separately, once, when a degree is created. `get_program_text()` returns the excerpt, the description, highlights, quotes (following `marketing/quotes/{id}/` for each) and careers. Nothing caches or refreshes it, because the page holds the only copy that matters after that.

### Cache policy

We cache in postmeta rather than transients. Postmeta is per-post, survives an object-cache flush, is easy to purge one degree at a time, and lets us keep serving the last good copy when the Search Service is down.

- **Fresh copy.** It is served as-is. The default lifetime is 24 hours, and the lifetime is a setting. The Search Service only takes in new program data once a month, so a daily re-read happens far more often than the data changes. We keep it anyway because it's cheap. It also picks up anything corrected directly in the Search Service between imports, if direct corrections turn out to be possible, which we haven't confirmed.
- **Stale copy.** It is served anyway, and a `ucf_degree_refresh_program` single event is scheduled for that post. A short lock transient keeps a traffic spike from queuing duplicate refreshes.
- **No copy at all.** We fetch synchronously with a 5-second timeout, under the same lock. This should be rare, because the importer fills the cache.
- **Failed fetch.** The previous copy stays in place, the error is recorded, and we wait 15 minutes before trying that post again.
- **Schema or source change.** A copy whose `schema_version` doesn't match the code, or whose `source` doesn't match the configured service, is treated as stale.
- **Refresh after each import.** A full refresh of every degree runs right after each monthly Search Service import, so new data reaches every page within hours of the import, not one degree at a time as copies happen to expire. The refresh runs on a schedule set to match the Search Service's import dates, or when the Search Service signals that an import finished, if it can.
- **Background sweep.** An hourly cron job refreshes the oldest stale copies in batches of 50 (configurable). It catches pages nobody visits and anything the post-import refresh missed.

### Access points

- **REST.** `GET ucf-degree/v1/degrees/<id>/program` returns the normalized data for editor previews, and `POST …/program/refresh` forces a refresh. Both require `edit_post`. A read-only `program` field on `/wp/v2/degrees` exposes the same data publicly.
- **WP-CLI.** `wp degrees cache refresh [--post=<id>] [--all]` refreshes copies. `wp degrees cache status` reports how old the copies are, which service they came from, and which posts have errors. `wp degrees overrides` lists every override, and flags the ones whose Search Service value has changed since the override was set.
- **Block Bindings.** A `ucf-degree/program` source has a server-side `get_value_callback` and a matching JS `registerBlockBindingsSource`. It binds single values (short name, credit hours, catalog URL, apply URL, tuition, outcome and projection figures, with overrides applied) to core Paragraph, Heading, Button and Image blocks in the default layout and in patterns. It is the right tool wherever a value sits inside editorial layout and nothing needs to hide when the value is empty.

What follows from this section: Phase 2 builds the data layer in full before any block depends on it.

## Blocks

Blocks use the `ucf-degree` namespace and `block.json` apiVersion 3. They are built with `@wordpress/scripts` from `src/blocks/<name>/` into `build/`, and registered from a manifest with `wp_register_block_types_from_metadata_collection()`. Every block uses `postId` and `postType` context, sets `supports.html: false`, and previews its data in the editor through the REST endpoint. When no degree is in context, such as in a pattern preview or the Site Editor, a block shows labeled placeholder content instead. Unless the table says otherwise, a block renders nothing, heading included, when its data is empty. A `render_block_ucf-degree/{name}` filter is available when a site needs to change markup.

| Block | Data | Key attributes |
|---|---|---|
| `program-details` | `program_types`, `colleges`, `departments`, `ucf_degree_hours` or its override, `ucf_degree_online` | `show` (which facts), `linkTerms` |
| `tuition` | cache `tuition` or its override, `ucf_degree_tuition_skip` | `heading`, `disclaimer` (default from settings) |
| `deadlines` | cache `deadlines` or its override | `heading`, `groupOrder`, `layout` (tabs or stacked), `disclaimer`; inner blocks render when there are no deadlines |
| `application-requirements` | cache `requirements` | `heading`; inner blocks render when there are no requirements |
| `outcomes` | cache `outcomes` | `heading`, `showAcademicYear` |
| `projections` | cache `projections` | `heading`, `showYearRange` |
| `subplans` | child degree posts | `heading`, `showExcerpt` |
| `apply-button` | program type + settings | `text` |
| `request-info` | Slate IDs + settings | `text`; renders the button and an Interactivity API `<dialog>` containing the form |

A few notes on specific blocks:

- **Deadlines and requirements are separate blocks.** Both come from the same `application_deadlines` resource, so one fetch serves both. Pages show them in different sections, so each gets its own block.
- **Outcomes and projections keep v3's number formatting.** Earnings render as currency, rates as whole percentages, the change percentage to two decimals, and counts with thousands separators. They stay blocks, rather than bound paragraphs, because a degree without that data has to drop the whole section.
- **The fallback content inside `deadlines` and `application-requirements` is a shared synced pattern.** The default layout gives each of these blocks a reference to the synced pattern chosen in settings. That keeps the copy the same on every degree and editable in one place, unless an editor replaces it on a single degree. This replaces v3's per-level fallback sections.
- **Program text has no blocks.** v3's description, highlights, quote and careers sections become core blocks in each degree's content (see "Seeding program text"), so v4 doesn't ship `description`, `highlights`, `quote` or `careers` blocks.
- **Program length is content, and credit hours are a fact.** The default layout shows credit hours through `program-details`. When a program needs a completion time such as "1–2 years", an editor adds it as page content next to the credit hours. A credit-hours override is for correcting or qualifying the number itself, such as a range for a program with variable hours, not for replacing it with a duration. The override also feeds the search listing and JSON-LD, and those expect hours.
- **JSON-LD moves into the plugin.** It is printed in `wp_head` from the same values the blocks show, overrides included, and a `ucf_degree_output_json_ld` filter lets a site turn it off.

### Overrides

An override is a value entered in WordPress that replaces one Search Service fact on one degree. It is set from the block that shows the fact. `program-details` has a credit-hours field. `tuition` has resident and nonresident amounts. `deadlines` can switch to custom deadline entries, which an editor adds in the block, row by row, in the same type, term and display shape the Search Service uses, so tabs and ordering keep working. Clearing the override puts the Search Service value back.

An override carries the risk we otherwise avoid by keeping facts live: it doesn't update. A deadline that was right in March looks just as authoritative in October. So overrides are built to stay visible:

- **The editor shows both values.** Wherever an override is set, the block's sidebar shows the current Search Service value next to it, along with a "Clear override" button.
- **We know when the source moves.** When an override is saved, `_ucf_degree_override_basis` records the Search Service value it replaced. If a later refresh brings a different value, the degree is flagged in its editor panel and in `wp degrees overrides`, because the reason for the override may no longer hold.
- **Overrides are listed in one place.** `wp degrees overrides` reports every overridden degree, so the content team can review them all at once, for instance at the start of each admissions cycle.

What follows from this section: each block ships on its own, in the order set in Phase 4, and overrides ship with the three blocks that take them.

## Template, default layout and patterns

The plugin registers one template, `ucf-degree//single-degree`, with `register_block_template()` from `templates/single-degree.html`. It contains the theme's `header` template part, `core/post-content` and the `footer` template part, and nothing else, because the page itself lives in each degree. A block theme can replace it by shipping its own `single-degree.html`, and changes made in the Site Editor take precedence over both. v4 has no equivalent of v3's custom degree template, because every degree can already be edited completely.

### The default layout

The default layout is defined once, as the pattern `ucf-degree/default-layout` in `patterns/default-layout.php`. Its sections run in today's order:

1. Hero: post title, a `program-details` summary, `apply-button` and `request-info`
2. Program at a Glance: `program-details`, `tuition` and default badges
3. Description and highlights (program text), with `subplans`
4. Deadlines: `deadlines`, `application-requirements` and `apply-button`
5. Start Your Application
6. `outcomes` and `projections`
7. Careers (program text)
8. Testimonial (program text)
9. UCF Online callout

A degree gets the layout in one of two ways:

- **Add New.** A `default_content` filter, limited to the `degree` post type, returns the pattern's markup, so the editor opens a new degree with the layout's real blocks already in it. The post type sets no `template` argument. That argument only accepts an array of block names and attributes, which can't carry the text inside static blocks such as headings and paragraphs. And if we used a single Pattern block as a placeholder, a degree could end up saving a reference to the shared layout instead of its own copy.
- **Import.** When the importer creates a degree, it writes the same pattern markup to `post_content`, with the program text filled in. Both paths take the markup from the pattern registry, so there is one definition of the layout. After creation, the importer never writes `post_content` again.

### Seeding program text

The layout pattern marks each program-text section (description, highlights, careers and testimonial) as a slot, a core Group with a `ucf-degree-slot-{name}` class. When the importer creates a degree, it calls `get_program_text()` and fills each slot with core blocks:

- **Description.** Paragraphs from the description type chosen in settings, falling back to the catalog description.
- **Highlights.** An Image and a Paragraph for each highlight.
- **Careers.** A core List.
- **Testimonial.** A core Quote with its image, from the program's first quote.

A slot with nothing to fill it, such as a program with no highlights, is dropped from the markup, heading included, the same way a data block drops out when its data is empty. On Add New, there's no program to read from yet, so the slots keep the pattern's placeholder blocks for an editor to write in. Once written, the content is the degree's own. The Search Service never updates it, and v4 has no way to push it back (see "Importer and writebacks").

### Shared content

Some content is the same on every degree: default badges, Start Your Application, the UCF Online callout, and the deadline and requirement fallback copy. If it were plain blocks in the default layout, every degree would get its own copy, and a wording change would mean editing hundreds of pages. **So each of those slots is a synced pattern (`core/block`) instead.** Synced patterns are site content with IDs that differ between environments, so the plugin can't ship them. Instead, the settings screen lets each site choose which synced pattern fills each slot, and the copying step inserts a `core/block` reference to it. Editing a synced pattern updates every degree that still references it, and an editor can detach it on one degree to customize it there. If no pattern is chosen for a slot, that slot is left out of the layout. On a new site, a "Create starter patterns" button builds a synced pattern for each slot from the plugin's default content.

### Changing the default layout later

Copying the layout into each degree is one-time, so changing the default layout only affects degrees created afterward. That is the cost of letting editors own each page, and we accept it. To keep it manageable:

- **The copy is recorded.** When the layout is written into a degree, `_ucf_degree_layout_version` and `_ucf_degree_layout_hash` record which version was written and a hash of the markup, seeded text included. The hash is per degree, so it tells us whether anyone has edited that degree since.
- **`wp degrees layout status`** reports how many degrees are untouched, how many have been edited, and how many are on an older layout version. With `--missing=<block>`, it lists the degrees that no longer contain a given block, such as `ucf-degree/application-requirements`, so we can find every page that dropped a section.
- **`wp degrees layout reset [--post=<id>] [--only-untouched] [--dry-run]`** writes the current default layout into degrees again, seeding the program text again from the Search Service. With `--only-untouched`, it skips any degree whose content no longer matches its stored hash and lists those degrees so a person can update them by hand. Re-seeding an untouched degree is safe, because nobody has edited its text.

### Guardrails

Editors can remove any block, including ones we would rather every degree kept. Where that matters, the default layout can give a block `lock: { remove: true }`, so it can still be moved but can't be deleted without unlocking it first. We should start with no locks and add them only where a real problem shows up. `request-info` and `apply-button` are the likely candidates.

### Editorial patterns

Patterns for editorial sections are registered from `patterns/` with `postTypes: [ 'degree' ]`, and appear in the inserter under a "Degree" category:

- **Course Overview:** headings and copy, plus a catalog button bound to `catalog_url`
- **Skills:** a heading and a core List
- **Admission Requirements:** two columns plus `request-info`
- **Testimonial:** a core Quote with an image, for curated quotes beyond the Search Service ones
- **News and Spotlight:** `ucf-news/news-feed` with the degree's topic, plus a core Shortcode block for `[ucf-spotlight]`, since the Spotlights plugin has no block

What follows from this section: the default layout has to look right in the new theme, so Phase 5 is done jointly with the theme work.

## Settings

The Settings › UCF Degrees screen is rebuilt around the new options:

- **Search Service.** Base URL and API key (read-only when set by a constant, with the source shown), a "Test connection" button, and an optional import filter.
- **Application and RFI.** Undergraduate and graduate application URLs, plus RFI base URLs and form IDs.
- **Disclaimers and ordering.** The tuition disclaimer, the undergraduate and graduate deadline disclaimers, and the deadline group order for each level.
- **Program text.** Which Search Service description type seeds a new degree's description.
- **Shared content.** Which synced pattern fills each shared slot (default badges, Start Your Application, UCF Online callout, deadline fallback and requirements fallback), plus the "Create starter patterns" button for a new site.
- **Writebacks.** The profile (permalink) writeback and the tuition-exception call, both **off by default** (see the next section).
- **Cache.** Cache lifetime and sweep batch size.

## Importer and writebacks

- **Lean meta only.** The importer writes the keys above with `update_post_meta()`, fills the cache from the program object and its linked resources in the same pass, and reuses the careers response for the `career_paths` terms. New posts are inserted with the default layout and seeded program text as their `post_content`, plus the program's excerpt. The importer never changes the content or excerpt of an existing post. The importer matches existing posts on `ucf_degree_id`, and it finds a subplan's parent by plan code, not by Search Service ID.
- **The filters get a documented contract.** `ucf_degree_get_post_metadata`, `ucf_degree_get_post_terms`, `ucf_degree_formatted_post_data` and `ucf_degree_api_results` stay as extension points. They operate on the v4 keys, and the docs say so.
- **The description writeback is removed.** It PUT `post_content` to the Search Service as the program's description. In v4, `post_content` is the whole page in block markup, not a description. WordPress now owns descriptions, so the Search Service's copy stops at whatever it held when each degree was created. Whether anything else needs our descriptions back is an open question.
- **Writebacks are only as safe as the service they target.** Pointing a development site at a non-production service, which is the reason the URL is configurable, makes writebacks safe to test there. The risk that remains is a non-production WordPress writing to the production service, which would point a program's profile URL at a development address or change shared tuition exceptions. So both writebacks default to off, and the settings screen shows the target host next to each switch. A `UCF_DEGREE_ALLOW_WRITEBACKS` constant must also be `true` before either one runs, which means a database cloned from production can't carry writebacks into another environment.
- **The metabox becomes an editor panel.** The classic "Degree Import Options" metabox is replaced by a `PluginDocumentSettingPanel` with ignore, skip tuition, undergraduate Slate ID, cache status with a refresh button, a list of the degree's overrides with any that need review flagged, and read-only identity fields.

## What v4 removes

v4 removes everything that only existed for the classic site:

- **ACF.** That includes every `update_field()` and `get_field()` call and the dependency itself.
- **Shortcodes, layouts and widgets.** `[ucf-degree-list]`, `[career-paths]`, the classic and two-column list layouts with their CSS, and the empty widget file all go. On the new site, degree lists are Query Loops showing `program-details`.
- **The gulp toolchain.** It is replaced by `@wordpress/scripts`.
- **The `degree_meta` REST field.** It returned every postmeta row, and the `program` field replaces it.
- **The description writeback and its `update_desc` and `desc_type` options.**
- **The excerpt lock.** Imports no longer update the excerpt, so there is nothing to lock.

The degree search REST routes (`/degrees`, `/program-types`, `/colleges`, `/interests`, `/tags` and their counts) stay, updated to the new meta names, because a degree search on the new site will need them.

The current site and the other properties (Online, Colleges) stay on 3.x, pinned with `^3` in Composer. We keep a `v3` maintenance branch for security fixes until those sites move.

## Phases

1. **Foundation.** Strip out the items listed under "What v4 removes", set up `@wordpress/scripts`, build `UCF_Degree_Search_Service` with its constant and option resolution and `wp degrees service`, register the new meta, make REST unconditional for the post type, and build the sidebar panel and settings screen. Bump the minimum to WordPress 6.8. *Done when:* a degree opens in the block editor on a block theme, the panel saves the import flags, and `wp degrees service` reports the right service and source for both a constant and an option.
2. **Data layer.** Build the program data service, cache, cron sweep, override resolution, REST routes, WP-CLI commands and bindings source. *Done when:* `wp degrees cache refresh --all` fills every degree, `cache status` reports no errors, and an override set through REST comes back from `ucf_degree_get_program()` in place of the cached value.
3. **Importer.** Switch to lean meta, move the theme's former responsibilities into the plugin, fill the cache during import, write the default layout with seeded program text into new posts, and remove the description writeback. The layout pattern starts as a stub with the program-text slots here, and is filled out in Phase 5. *Done when:* a fresh import from a non-production service into an empty site produces degrees that carry only v4 keys, a full cache from that host, and the default layout with each program's own text in it. A second import, after the Search Service text changes, must leave `post_content` and the excerpt unchanged. Switching the configured service then refetches every degree from the new host and re-matches them by plan and subplan code.
4. **Blocks.** Build them in this order: `program-details`, `tuition`, `deadlines`, `application-requirements`, `apply-button`, `request-info`, `outcomes`, `projections`, `subplans`, then JSON-LD. The first three ship with their override controls. *Done when:* each block renders from the cache in the post editor and on the front end, shows placeholder content when no degree is in context, drops out entirely when its data is empty, and takes styles from `theme.json`. An override must show on the page, in the search API and in JSON-LD, sit next to the Search Service value in the editor, and clear back to it.
5. **Template, layout and patterns.** Add the frame template, the full default layout, the shared-content settings and starter synced patterns, the `wp degrees layout` commands and the editorial patterns. This phase is done with the new theme in hand. *Done when:* an imported degree and an Add New degree both open with the full default layout, and an editor can reorder deadlines and outcomes on one degree without affecting any other. Editing a shared synced pattern must update every degree that references it, and `layout reset --only-untouched` must rewrite only degrees nobody has edited.
6. **Release.** Update the readme, Composer version and changelog, and document the meta keys, filters and the `ucf_degree_get_program()` helper for other teams.

## Open questions

- **Which source owns tuition?** The Search Service provides tuition, but UCF-Tuition-Fees-Plugin also writes it into postmeta on the current site. We need to know whether the new site runs that plugin before the `tuition` block reads only from the cache.
- **What do the new theme's template parts look like?** We need the `header` and `footer` slugs, and whether the theme's `theme.json` will style the `ucf-degree/*` blocks or expects the plugin to ship more than structural CSS.
- **Are careers and application requirements drawn on the right side of the line?** This plan treats careers as program text, seeded once, and application requirements as a live fact with no override. Either could reasonably go the other way. Requirements in particular could take an override like deadlines, if programs hit the same kind of exceptions.
- **Does anything else read descriptions from the Search Service?** With the writeback gone, the Search Service keeps the catalog text while our pages carry our own. If another site or service expects our edited descriptions there, we need a replacement for the writeback that sends text rather than block markup.
- **Should any blocks be locked against removal?** We plan to start with none (see "Guardrails"). Content leads may already know of blocks every degree must keep.
- **Will the new site use Relevanssi?** If it does, the `/degrees/relevanssi` route stays. Program text now lives in `post_content`, so Relevanssi indexes it without extra work.
- **What are the new platform's WordPress and PHP versions?** They set the minimums we declare.
- **When should the writebacks turn on?** We need a launch-day plan for switching the Search Service's profile URLs from the current site to the new one.
- **When does the monthly Search Service import run, and can it tell us when it finishes?** The post-import refresh depends on one or the other. We also need to know whether program data can be corrected directly in the Search Service between imports, or only by fixing the source system and waiting for the next import.
- **Will the Search Service add exact lookup by code and `expand`, and when?** The plugin doesn't wait on either. Knowing the timeline tells us how long the search-and-verify fallback has to carry production.
- **Does every subplan share its parent's plan code?** Parent matching depends on it. We should check it against the full program list before Phase 3.
- **Is the `career_paths` taxonomy needed on the new site beyond display?** If not, the taxonomy can go, and careers exist only as page content.
