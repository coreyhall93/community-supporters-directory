# Data schema — Community Supporters Directory

The contract between this plugin and whatever produces the data: an Airtable
base (the real path, matching how Maciej Pilarski's upstream
`credits-program-mentors` plugin works), or the local `data/supporters.json`
fallback (fastest, no Airtable account needed). Field **names are
case-sensitive** and must match exactly, or the constants in
`includes/class-comsup-shortcode.php` (`DEFAULT_FIELDS`, `STATUS_FIELD`,
`SPONSOR_FIELD`, `COUNTRY_FIELD`, `LANGUAGE_FIELD`) need updating to match.

| Field | Type | Notes |
|---|---|---|
| `Full Name` | Single line text | Required. Becomes the card title. |
| `Role Type` | Single or multi select | `Event Supporter`, `Program Supporter`, or both — this is Karen's own distinction from the 2026-07-30 Comet Weekly Sync ("event supporters, program managers, or both"). Renders as a badge. |
| `Region` | Single select | Matches the Comet regional-ownership model raised on the same call: Southeast US, Australia / New Zealand, India, Latin America — extend as the real program covers more regions. Renders as a badge. |
| `Country` | Single line text or select | Same mechanic as the upstream mentor map: used for the country filter and the Leaflet map markers. Country names need to resolve via `includes/data-countries.php`'s aliases (e.g. "USA" / "United States" both map to the same marker) — add new aliases there if a country doesn't show on the map. |
| `Languages` | Multi select or comma-separated text | Renders as tag pills; powers the Language filter. |
| `WordPress profile` | URL | Must be a `https://profiles.wordpress.org/{username}/` link — that's what resolves the WordPress.org/Gravatar avatar photo. Leave blank for no photo (shows initials). |
| `Sponsor Company Name` | Single line text | Leave **blank** for Automatticians. Fill in a company name for anyone doing this community work as a sponsored/paid role for an outside company rather than as an Automattician — this is the same "freelancer vs. Automattician" axis Karen asked about, generalized to "vs. any sponsoring company." Non-empty triggers the "Sponsored" badge. |
| ~~`Slack Channels`~~ | — | **Removed 2026-07-31, do not reintroduce in published data.** It held the names of the Slack channels each person belongs to, which for almost every record meant naming a *private* channel. The field was never displayed, so it carried disclosure risk and no product value. Channel membership can still be used as a sourcing signal while gathering, it just does not belong in a file that ships. See `FUTURE_COREY.md` → Open issues. |
| `Status` | Single select | `Active` / `Inactive`. Only records with `Status = Active` render on the public directory — same gating as upstream. |

## Two ways to feed data in

**1. Quick (no Airtable account needed):** replace the contents of
`data/supporters.json` with a flat JSON array, one object per person, using the
field names above as keys:

```json
[
  { "Full Name": "...", "Role Type": "Event Supporter", "Region": "Southeast US",
    "Country": "United States of America", "Languages": "English",
    "WordPress profile": "https://profiles.wordpress.org/username/",
    "Sponsor Company Name": "", "Slack Channels": "community-team",
    "Status": "Active" }
]
```

The file currently holds 4 rows clearly marked `SAMPLE —` — used only to verify
the plugin renders end to end. Replace them entirely; don't append real people
alongside the samples.

**2. Real (once this is more than a prototype):** create an Airtable base with a
table matching this schema, generate a read-only Personal Access Token at
https://airtable.com/create/tokens (scope `data.records:read`), and paste the
token, Base ID, and Table ID into the **Community Supporters Directory** admin
menu. The plugin ignores `data/supporters.json` once a real connection is
configured.
