=== Community Supporters Directory ===
Contributors: coreyhall
Tags: community, airtable, directory, shortcode, block
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display a filterable directory of Community Event & Program Supporters from an Airtable base, with WordPress.org profile photos and role/region badges.

== Description ==

Community Supporters Directory turns an Airtable table of Make WordPress Community
Event Supporters and Program Supporters into a clean, filterable directory on your
site. It pulls records through Airtable's official REST API, caches them for speed,
and renders them as a responsive card grid or a table via the
`[community_supporters]` shortcode or the bundled block.

This plugin is a reverse-engineered, retargeted fork of Maciej Pilarski's
[Credits Program Mentors](https://github.com/maciejpilarski/credits-program-mentors)
plugin, built for the WP Credits mentor program. The data-fetching, caching,
avatar, and mapping mechanics are unchanged; only the field schema and copy have
been adapted from "mentors" to "Community Event & Program Supporters." See
FUTURE_COREY.md in this project for what's still needed before this goes live.

= Features =

* **Shortcode and block** — `[community_supporters]`, or the "Community Supporters
  Directory" block with a live preview and sidebar controls.
* **Card grid or table** layout, with the detail fields aligned across cards.
* **Active supporters only** — shows supporters whose Airtable `Status` is `Active`.
* **Role Type and Region badges** — surfaces whether someone supports Events,
  Programs, or both, and which region they own (matches the Comet regional
  ownership model: Southeast US, ANZ, India, LatAm, etc.).
* **Sponsor badge** — supporters whose community work is sponsored by an outside
  company (not done as an Automattician) get a small "Sponsored" badge naming the
  company — the same mechanic Karen asked about for freelancer-vs-Automattician.
* **Profile photos** — each supporter's WordPress.org profile avatar, loaded
  directly in the visitor's browser via WordPress.org's official avatar redirect.
* **Front-end filters** — visitors can narrow the list by Sponsorship, Language,
  and Country, entirely in the browser (works on cached pages).
* **Optional country map** — an interactive Leaflet map showing where supporters
  are based, with a marker per country sized by supporter count; enable or disable
  it from the plugin settings. Visitors can zoom and pan, and click a country to
  filter the list.
* **Caching** — Airtable records are stored in transients; a "Refresh data"
  button and a configurable cache lifetime are provided.
* **Local JSON fallback** — if no Airtable token is configured, the plugin reads
  `data/supporters.json` in the plugin folder instead, so the directory can be
  previewed and tested with real pulled data before an Airtable base exists. This
  fallback is new in this fork; Maciej's original always required Airtable.
* **Privacy-aware, escaped output, and translation-ready.**

= Configuration =

Records are requested with Airtable's `cellFormat=string`, so single-selects,
multi-selects, linked records, and URL fields all render cleanly. The Base ID,
Table ID, optional View, cache lifetime, and country map are all configurable
from the **Community Supporters Directory** menu in the admin sidebar. Until a
Base ID and Table ID are entered, the plugin renders from the bundled
`data/supporters.json` file instead (see "Local JSON fallback" above).

The proposed Airtable schema (columns the data-gathering side should produce) is
documented in `AIRTABLE-SCHEMA.md` alongside this plugin's source.

== External services ==

This plugin connects to the following third-party services. It only does so with
data you configure; it does not transmit personal data about your site visitors
beyond what any embedded remote image would.

**1. Airtable API (api.airtable.com)**
Used to fetch the supporter records that the plugin displays, once a Base ID,
Table ID, and Personal Access Token are configured. No site-visitor data is sent.
Airtable: https://airtable.com/ — Terms: https://www.airtable.com/company/tos — Privacy: https://www.airtable.com/company/privacy

**2. WordPress.org avatar redirect (wordpress.org)**
Used to display each supporter's profile photo. The plugin outputs an `<img>`
whose source is WordPress.org's official avatar redirect,
`https://wordpress.org/grav-redirect.php?user=USERNAME`, which redirects to that
user's Gravatar. The image is requested by the visitor's browser, so the
visitor's IP address and user agent are sent to WordPress.org (and to Gravatar,
below) when the image loads. Photos can be disabled with `photos="no"`.
WordPress.org: https://wordpress.org/ — Privacy: https://wordpress.org/about/privacy/

**3. Gravatar (secure.gravatar.com)**
The avatar redirect above resolves to a Gravatar image, which the visitor's
browser loads directly from Gravatar (operated by Automattic). Supporters without
a Gravatar get a generated identicon.
Gravatar: https://gravatar.com/ — Privacy: https://automattic.com/privacy/

**4. CARTO basemap tiles (basemaps.cartocdn.com)**
When the optional country map is enabled, the visitor's browser loads map tiles
from CARTO's light "Positron" basemap (built on OpenStreetMap data). This sends
the visitor's IP address and the requested tile coordinates to CARTO as the map
renders. The map can be turned off in the plugin settings.
CARTO: https://carto.com/ — Terms: https://carto.com/legal/ — OpenStreetMap: https://www.openstreetmap.org/copyright

== Installation ==

1. Upload the `plugin` folder (as `community-supporters`) to `/wp-content/plugins/`,
   or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen.
3. Open the **Community Supporters Directory** menu in the admin sidebar.
4. Either drop real data into `data/supporters.json` (see AIRTABLE-SCHEMA.md for
   the shape), or create an Airtable base matching that schema, generate a
   read-only Personal Access Token at https://airtable.com/create/tokens with the
   `data.records:read` scope, and paste it in along with the Base ID and Table ID.
5. Add `[community_supporters]` to a page or post — or insert the
   **Community Supporters Directory** block.

== Frequently Asked Questions ==

= Do I need an Airtable account and token? =

No, not to start. Without one configured, the plugin reads from the bundled
`data/supporters.json` file. An Airtable base and read-only Personal Access Token
(scoped to `data.records:read`) are only needed once this moves from a prototype
to a maintained, editable data source.

= Which supporters are displayed? =

Supporters whose `Status` field equals `Active`. Supporters that also have a
value in `Sponsor Company Name` are marked with a "Sponsored" badge naming the
company.

= Where do the profile photos come from? =

Each photo is the supporter's WordPress.org profile avatar, resolved the same
way as the upstream Credits Program Mentors plugin: via WordPress.org's avatar
redirect (`grav-redirect.php?user=USERNAME`), loaded directly by the visitor's
browser. Supporters with no Gravatar get a generated identicon; supporters with
no WordPress.org profile URL show their initials.

= Can I show a map of where supporters are based? =

Yes. Enable **Country map** in the plugin settings to show an interactive map
above the list, with a marker per country sized by the number of supporters.
Visitors can zoom and pan, and click a country's marker to filter the list to
supporters from that country.

= What shortcode attributes are available? =

`layout` (grid|table), `limit`, `columns`, `country`, `language`, `search`,
`fields`, `view`, `photos` (yes|no), `photo_size`, `filters` (yes|no), and
`map` (yes|no; defaults to the setting).
Example: `[community_supporters layout="grid" columns="3" map="yes"]`

== Changelog ==

= 1.0.0 =
* Initial release of this fork: reverse-engineered and retargeted from Maciej
  Pilarski's Credits Program Mentors plugin (WP Credits mentors) to Community
  Event & Program Supporters. Renamed plugin slug, class prefix, shortcode, and
  text domain throughout; cleared the upstream's pre-filled Airtable base/table
  IDs (they pointed at the real Sponsored Mentors base); added Role Type and
  Region to the default field set and the field-role map; added a local
  `data/supporters.json` fallback so the directory renders before an Airtable
  base exists. Airtable client, transient caching, WordPress.org avatar
  resolution, Leaflet/CARTO country map, and front-end filter mechanics are
  otherwise unchanged from upstream.

See the upstream plugin's history at
https://github.com/maciejpilarski/credits-program-mentors for the mentor-specific
version history (1.0.0 through 1.5.0) this fork was cloned from.
