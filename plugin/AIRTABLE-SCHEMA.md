# Data schema — Community Supporters Directory

The contract between this plugin and whatever produces the data: an Airtable
base (the real path, matching how Maciej Pilarski's upstream
`credits-program-mentors` plugin works), or the local `data/supporters.json`
fallback (fastest, no Airtable account needed). Field **names are
case-sensitive** and must match exactly, or the constants in
`includes/class-comsup-shortcode.php` (`DEFAULT_FIELDS`, `STATUS_FIELD`,
`EMPLOYER_FIELD`, `COUNTRY_FIELD`, `LANGUAGE_FIELD`) need updating to match.

| Field | Type | Notes |
|---|---|---|
| `Full Name` | Single line text | Required. Becomes the card title. |
| `Role Type` | Single or multi select | `Event Supporter`, `Program Supporter`, or `Program Manager` — **sourced from the official [Community Team Supporters and Managers page](https://make.wordpress.org/community/community-deputies/)**, not from Slack titles (self-reported, provably stale) and not from behaviour inference. Renders as a badge and powers the Role filter. `Program Manager` added 2026-07-31 when the official page surfaced the 11 PMs. |
| `Region` | Single select | Matches the Comet regional-ownership model raised on the same call: Southeast US, Australia / New Zealand, India, Latin America — extend as the real program covers more regions. Renders as a badge. |
| `Country` | Single line text or select | Powers the Country filter. Names must resolve via `includes/data-countries.php`'s aliases (e.g. "USA" / "United States" both map to the same id) — add new aliases there if a country doesn't resolve. **No longer what places a marker** — see `Latitude`/`Longitude`. |
| `City` | Single line text | Free text, as self-reported on the person's `.org` profile ("Pune, India", "Braintree, MA", "Massachusettes" — their spelling, kept verbatim). Shown under the name on the card, and geocoded to `Latitude`/`Longitude`. Never invent one. |
| `Latitude` / `Longitude` | Number | **What actually puts a dot on the map.** Populated by geocoding `City` (+ `Country`) through Photon, the open OSM geocoder the reference Programs Map plugin uses. When only a country is known, the country's centroid from `data-countries.php` is used instead. |
| `Location Precision` | Single select | `city` or `country`. `city` renders a solid dot, `country` renders a **hollow** dot so a centroid never reads as "this person is here". Anything else renders hollow. |
| `Languages` | Multi select or comma-separated text | Renders as tag pills; powers the Language filter. |
| `WordPress profile` | URL | Must be a `https://profiles.wordpress.org/{username}/` link — that's what resolves the WordPress.org/Gravatar avatar photo. Leave blank for no photo (shows initials). ⚠️ **Verify the username resolves to the right person.** Three URLs in the first pull 404'd and others in this program have been confirmed to point at unrelated people, which would render a stranger's avatar. |
| `Sponsored By` | Single line text | **Who pledges this person's contribution time under Five for the Future** — parsed from the structured pledge block (`wp-p2-sponsors`) on their own `.org` profile, which links the sponsor's official pledge page. This is the fact base for the "Sponsored contributor" chip and the Contribution filter. Distinct from `Employer`: Lidia Arroyo Vargas is sponsored by Automattic and employed by no company on record; Juan Hernando is employed by *and* sponsored by Weglot. Automattic employees typically show Automattic here too (the company pledges its staff). |
| `Employer` | Single line text | **Who the person works for.** Powers the Employer filter and shows on the card. <br><br>⚠️ **Renamed from `Sponsor Company Name` 2026-07-31, at Corey's call.** The old field conflated an employer with a Five-for-the-Future sponsor, so a contributor sponsored by Automattic but employed elsewhere read as Automattic staff — his words: *"she's not an employee at Automattic so that is confusing. We need their employer."* The old "Sponsored" badge was removed with it; a yes/no sponsorship flag answered nothing useful, and the real question from the 2026-07-30 sync was *who do they work for*. <br><br>Values come from the person's own `.org` profile jobline where available. **That is self-reported and can be stale** — Hari Shanker's Slack title still read "Global Community Program Manager" a year after he left for Reddit. Treat it as a claim, not a verified fact. Freelance/self-employed strings name no company and are left blank. <br><br>The employee-vs-sponsored question is answered by reading this **together with `Sponsored By`** — resolved 2026-07-31 when that field landed. |
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
    "Employer": "", "Sponsored By": "",
    "Status": "Active" }
]
```

The file holds the real roster (80 records as of 2026-07-31).

**2. Real (once this is more than a prototype):** create an Airtable base with a
table matching this schema, generate a read-only Personal Access Token at
https://airtable.com/create/tokens (scope `data.records:read`), and paste the
token, Base ID, and Table ID into the **Community Supporters Directory** admin
menu. The plugin ignores `data/supporters.json` once a real connection is
configured.
