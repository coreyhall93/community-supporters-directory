# Community Event & Program Supporters

A filterable map and directory of the people supporting Make WordPress community
events and programs. Shows where supporters are based, what languages they work
in, and whether their contribution is sponsored and by whom.

**Live:** https://coreyhall93.github.io/community-supporters-directory/

> **First pass, rough working data.** This is a fast pull put together to look at
> in the channel, not a verified roster. Some entries are incomplete and some are
> unconfirmed. Corrections welcome.

## Why

Coming out of a Comet team discussion about being able to see, rather than guess,
where our event and program supporters actually are. The point is proximity. Who
is near a given community, who is near each other, and therefore where a nudge or
an in-person reconnect would actually land.

## Credit

This is built on the pattern established by
[**Credits Program Mentors**](https://github.com/maciejpilarski/credits-program-mentors)
by Maciej Pilarski, which does the same job for the WP Credits mentor program.
The Airtable client, WordPress.org avatar resolution, Leaflet country map, filter
bar and card grid all come from that plugin. It has been renamed and retargeted
from mentors to community event and program supporters, with `Role Type` and
`Region` added. Forked with his blessing.

The [WordPress Community Events Dashboard](https://marutim.github.io/wp-events-dashboard/)
by Maruti Mohanty covers meetup groups, WordCamps and the application pipeline.
Layering its meetup activity data onto this map is the intended next step, so a
dormant group with supporters nearby becomes visible at a glance.

## How it's built

The source of truth is a real WordPress plugin in [`plugin/`](plugin/), so this can
move onto a WordPress site later without a rewrite. Because a plugin can't be
hosted on GitHub Pages, [`build/export-static.py`](build/export-static.py) renders
the plugin's own shortcode through a local WordPress and wraps the output in a
standalone page, reusing the plugin's real CSS, JS and vendored Leaflet untouched.
The static page and the plugin therefore cannot drift apart.

```sh
python3 build/export-static.py     # -> docs/
```

GitHub Pages serves `docs/`.

## Data

[`plugin/data/supporters.json`](plugin/data/supporters.json) is a flat array, one
object per person. The field contract is documented in
[`plugin/AIRTABLE-SCHEMA.md`](plugin/AIRTABLE-SCHEMA.md).

Compiled from WordPress.org profiles and Make WordPress community channels. Roughly
two thirds of the entries have not been independently verified, and about half have
no country recorded yet, so they appear in the list but not on the map.

If you are listed here and would rather not be, or something about your entry is
wrong, say so and it comes out or gets fixed.

The plugin reads this file only while no Airtable connection is configured. Once a
Base ID, Table ID and read-only token are set in the admin screen, Airtable becomes
the source and this file is ignored.

## Status

| | |
|---|---|
| Supporters map, filters, country map | Done |
| Meetup activity layer (from the events dashboard) | Planned |
| Verified roster | Not yet, this is a first pull |

## License

GPL-2.0-or-later, matching the upstream plugin.
