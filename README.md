# ACF + Claude + WordPress MCP Demo

A demo WordPress site that pairs **Advanced Custom Fields (ACF) Pro** with the **WordPress MCP Adapter** so an AI agent (Claude) can query and manipulate structured WordPress content through natural language.

The example dataset is a set of Austin, TX vacation rental listings stored as a custom `property` post type.

## What's in this repo

| File / Folder | Purpose |
|---|---|
| `austin-properties.csv` | 15 sample property listings |
| `import-properties.php` | WP-CLI script that seeds the CSV into the `property` CPT |
| `wp-content/mu-plugins/site.php` | Enables ACF's AI / schema features and wires the Google Maps key |
| `wp-content/themes/twentytwentyfive-child/` | Minimal child theme used by the demo |
| `.env.local.sample` | Template for your Google Maps API key |
| `.mcp.json.sample` | Template MCP server config for Claude / other MCP clients |
| `.local-wp.sh.sample` | Template wrapper that lets MCP clients run `wp-cli` against a Local site |
| `.gitignore` | Blocks secrets, WP core, and the licensed ACF Pro plugin |

WordPress core, ACF Pro, and the MCP Adapter plugin are **not** included — you'll install them yourself (see below).

## Prerequisites

- [Local](https://localwp.com/) (the Flywheel desktop app) — or any other WordPress dev environment
- An **ACF Pro license** ([acf.com](https://www.advancedcustomfields.com/pro/))
- The **MCP Adapter** plugin ([github.com/WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter))
- [Claude Code](https://claude.com/claude-code) or another MCP-capable client
- A **Google Maps JavaScript API key** ([Google Cloud Console](https://console.cloud.google.com/google/maps-apis))

## Setup

### 1. Create a Local site

In Local, create a new WordPress site. Note its root directory — you'll drop this repo's files inside `app/public/`.

### 2. Clone this repo into your site

```bash
cd ~/Local\ Sites/<your-site-name>/app/public
git clone <this-repo-url> .
```

### 3. Install the plugins

Install ACF Pro and the MCP Adapter into `wp-content/plugins/`. Activate both in wp-admin.

### 4. Configure your Google Maps key

```bash
cp .env.local.sample .env.local
# Edit .env.local and paste your real key
```

Then add it to `wp-config.php`:

```php
define( 'ACF_GOOGLE_MAPS_API_KEY', 'YOUR_KEY_HERE' );
```

### 5. Create the `property` CPT and field group

Use ACF's UI (or its JSON import) to create:

- **Post type:** `property`
- **Taxonomies:** `property_type`, `region`, `amenity`
- **ACF fields on `property`:** `price_per_night` (number), `bedrooms` (number), `bathrooms` (number), `max_guests` (number), `address` (text), `city` (text), `location` (Google Map), `checkin_time` (time picker, `H:i:s`), `checkout_time` (time picker, `H:i:s`), `rating` (number)

### 6. Configure the WP-CLI wrapper (for MCP)

```bash
cp .local-wp.sh.sample .local-wp.sh
chmod +x .local-wp.sh
```

Edit `.local-wp.sh` and fill in:
- `<YOUR_USERNAME>` — your macOS username
- `<YOUR_LOCAL_SITE_ID>` — the site's run ID from `~/Library/Application Support/Local/run/`
- `<PHP_VERSION>` — your site's PHP version (e.g. `php-8.2.29+0`)

### 7. Configure the MCP server

```bash
cp .mcp.json.sample .mcp.json
```

Edit `.mcp.json` and replace `<ABSOLUTE_PATH_TO_PROJECT>` with your `app/public` path.

### 8. Import the property data

You have two options.

**Option A — Ask Claude to do it (the whole point of this demo):**

With MCP running, ask Claude:

> Read `austin-properties.csv` and import each row into the `property` CPT. For each row, create the post, assign the `property_type`, `region`, and `amenity` taxonomy terms (creating any that don't exist), and populate the ACF fields. Format the `location` field as an array with `address`, `lat`, `lng`, and `zoom: 14`, and the check-in/out times as `H:i:s`. Skip any rows where a property with that title already exists.

**Option B — Run the deterministic script:**

```bash
./.local-wp.sh eval-file import-properties.php
```

The script is idempotent — re-running it won't create duplicates.

## Verify

```bash
./.local-wp.sh post list --post_type=property --format=table
./.local-wp.sh post term list <POST_ID> property_type
```

Post IDs will differ between installs — use `post list` to find yours.

## License

Demo code in this repo is MIT. ACF Pro is licensed separately by WP Engine and is **not** included.
