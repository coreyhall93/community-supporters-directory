#!/usr/bin/env python3
"""
Export the Community Supporters Directory plugin to a standalone static site.

Why this exists: the plugin is the source of truth and is meant to end up on a
real WordPress site, but a WordPress plugin can't be hosted on GitHub Pages.
This renders the plugin's own shortcode through a local WordPress (Studio) and
wraps the resulting markup in a self-contained page, reusing the plugin's real
CSS and JS untouched. Static page today, plugin still valid later, and because
both read the same front-end files they cannot drift apart.

Usage:
    python3 build/export-static.py [--site PATH] [--out DIR]
"""

import argparse
import json
import re
import shutil
import subprocess
import sys
from datetime import date
from pathlib import Path

PROJECT = Path(__file__).resolve().parent.parent
PLUGIN = PROJECT / "plugin"
DEFAULT_SITE = Path.home() / "Studio" / "coreyhall93-claude-code"
# GitHub Pages serves from the repo root or /docs. Building straight into docs/
# means the published copy is the build output, with nothing to keep in sync.
DEFAULT_OUT = PROJECT / "docs"

# Front-end files the rendered markup depends on, copied verbatim from the
# plugin so the static page and the plugin stay byte-identical in appearance.
ASSETS = [
    "assets/css/community-supporters.css",
    "assets/js/filters.js",
    "assets/js/map.js",
    "assets/vendor/leaflet/leaflet.css",
    "assets/vendor/leaflet/leaflet.js",
    "assets/vendor/leaflet/images/layers-2x.png",
    "assets/vendor/leaflet/images/layers.png",
    "assets/vendor/leaflet/images/marker-icon-2x.png",
    "assets/vendor/leaflet/images/marker-icon.png",
    "assets/vendor/leaflet/images/marker-shadow.png",
]


def run_wp(site: Path, php: str) -> str:
    """Render PHP through the Studio site's WP-CLI and return stdout.

    Only for short values. Anything large must use run_wp_to_file(): stdout
    through this path truncates somewhere above ~60KB, which silently dropped
    19 of 65 supporter cards from the first build with no error anywhere.
    """
    proc = subprocess.run(
        ["studio", "wp", f"--path={site}", "eval", php],
        capture_output=True,
        text=True,
    )
    if proc.returncode != 0:
        sys.exit(f"WP-CLI failed:\n{proc.stderr}")
    out = proc.stdout
    # WP-CLI's phar emits a PHP 8.5 deprecation banner on this machine; it is
    # noise from the tool itself, not from the plugin, so strip it.
    out = re.sub(r"^\s*(PHP )?Deprecated:.*$", "", out, flags=re.MULTILINE)
    return out.strip()


def run_wp_to_file(site: Path, php_expr: str) -> str:
    """Evaluate a PHP expression and return its value, via a temp file.

    Bypasses stdout entirely so large markup survives intact. Studio sandboxes
    WordPress at /wordpress/, so the file has to be written using WordPress's
    own uploads path from inside and read back from the host's bind-mounted
    copy of it. Passing a host path into file_put_contents() silently fails.
    """
    name = "comsup-export.tmp"
    host_tmp = site / "wp-content" / "uploads" / name
    run_wp(
        site,
        f"file_put_contents( wp_upload_dir()['basedir'] . '/{name}', {php_expr} );",
    )
    if not host_tmp.exists():
        sys.exit(f"WP-CLI did not write {host_tmp}.")
    out = host_tmp.read_text()
    host_tmp.unlink()
    return out


def sync_plugin(site: Path) -> None:
    """Push the current plugin source into the local site before rendering."""
    dest = site / "wp-content" / "plugins" / "community-supporters"
    if dest.exists():
        shutil.rmtree(dest)
    shutil.copytree(PLUGIN, dest)


def page(markup: str, count: int, mapped: int) -> str:
    stamp = date.today().isoformat()
    # Be explicit that the map plots fewer people than the list holds. Roughly
    # half the first pull has no country yet, and a map quietly showing less
    # than the headline number reads as a bug or, worse, goes unnoticed.
    gap = (
        f' &middot; <strong>{mapped}</strong> of them have a country recorded'
        f" and appear on the map"
        if mapped < count
        else ""
    )
    return f"""<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Community Event &amp; Program Supporters</title>
<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="assets/css/community-supporters.css">
<style>
  :root {{
    --page-ink: #1e293b;
    --page-muted: #64748b;
    --page-ground: #f6f7f7;
    --page-line: #e5e9f2;
  }}
  html {{ background: var(--page-ground); }}
  body {{
    margin: 0;
    padding: 0 20px 64px;
    background: var(--page-ground);
    color: var(--page-ink);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
      Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    -webkit-font-smoothing: antialiased;
  }}
  .page {{ max-width: 1120px; margin: 0 auto; }}
  .page__head {{ padding: 56px 0 8px; }}
  .page__title {{
    margin: 0 0 12px;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(30px, 4vw, 44px);
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1.1;
  }}
  .page__intro {{
    margin: 0;
    max-width: 62ch;
    font-size: 17px;
    line-height: 1.6;
    color: var(--page-muted);
  }}
  .page__meta {{
    margin: 24px 0 0;
    padding-top: 16px;
    border-top: 1px solid var(--page-line);
    font-size: 13px;
    line-height: 1.6;
    color: var(--page-muted);
  }}
  .page__meta strong {{ color: var(--page-ink); font-weight: 600; }}
  .page__foot {{
    max-width: 62ch;
    margin: 48px auto 0;
    padding-top: 20px;
    border-top: 1px solid var(--page-line);
    font-size: 13px;
    line-height: 1.65;
    color: var(--page-muted);
  }}
  .page__foot p {{ margin: 0 0 10px; }}
  .page__foot a {{ color: #3858e9; }}
</style>
</head>
<body>
<div class="page">
  <header class="page__head">
    <h1 class="page__title">Community Event &amp; Program Supporters</h1>
    <p class="page__intro">Where the people supporting Make WordPress community
      events and programs are based, and what they can help with. Filter by
      sponsorship, language or country, or click a country on the map.</p>
    <p class="page__meta"><strong>{count} supporters</strong> in this pull{gap}
      &middot; first pass, {stamp} &middot; rough working data, not a verified roster</p>
  </header>

  {markup}

  <footer class="page__foot">
    <p>First pass at visualising who our event and program supporters are and
      where they are, so we can see who is near what. Names, countries and
      sponsors are drawn from public WordPress.org profiles and the community
      Slack channels, and some entries are incomplete or unconfirmed.</p>
    <p>Built on the same pattern as the
      <a href="https://github.com/maciejpilarski/credits-program-mentors">Credits
      Program Mentors</a> plugin. Meetup activity data is a planned second layer.</p>
  </footer>
</div>
<script src="assets/vendor/leaflet/leaflet.js"></script>
<script src="assets/js/filters.js"></script>
<script src="assets/js/map.js"></script>
</body>
</html>
"""


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--site", type=Path, default=DEFAULT_SITE)
    ap.add_argument("--out", type=Path, default=DEFAULT_OUT)
    args = ap.parse_args()

    if not args.site.exists():
        sys.exit(f"Studio site not found: {args.site}")

    sync_plugin(args.site)

    count = run_wp(
        args.site,
        "$c = new COMSUP_Airtable_Client( COMSUP_Settings::get() );"
        "$r = $c->get_records(); echo is_wp_error($r) ? 0 : count($r);",
    )
    mapped = run_wp(
        args.site,
        "$c = new COMSUP_Airtable_Client( COMSUP_Settings::get() );"
        "$r = $c->get_records(); $n = 0;"
        "if ( ! is_wp_error($r) ) { foreach ($r as $rec) {"
        "  $f = isset($rec['fields']) ? $rec['fields'] : array();"
        "  $v = isset($f['Country']) ? $f['Country'] : '';"
        "  if ( is_array($v) ) { $v = implode(',', $v); }"
        "  if ( '' !== trim( (string) $v ) ) { $n++; }"
        "} } echo $n;",
    )
    markup = run_wp_to_file(args.site, 'do_shortcode("[community_supporters]")')

    if "comsup-supporters" not in markup:
        sys.exit(f"Shortcode produced no directory markup:\n{markup[:400]}")

    # Guard the truncation bug from ever returning silently.
    rendered = markup.count("<article")
    if rendered != int(count):
        sys.exit(
            f"Rendered {rendered} cards but the data has {count} records. "
            "Output was truncated; do not publish this build."
        )

    out = args.out
    if out.exists():
        shutil.rmtree(out)
    out.mkdir(parents=True)

    for rel in ASSETS:
        src = PLUGIN / rel
        dst = out / rel
        dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dst)

    (out / "index.html").write_text(page(markup, int(count), int(mapped)))
    # GitHub Pages runs Jekyll by default, which ignores paths it considers
    # special. Opt out so assets/ is served as-is.
    (out / ".nojekyll").write_text("")

    print(f"wrote {out}/index.html ({count} supporters)")


if __name__ == "__main__":
    main()
