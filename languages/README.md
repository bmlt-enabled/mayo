# Translations

The Mayo Events Manager plugin is internationalized and ready for translation.

- **Text domain:** `mayo-events-manager`
- **Domain path:** `/languages`

## Translation files

This directory holds:

- `mayo-events-manager.pot` — the translation template (English source strings).
- `mayo-events-manager-{locale}.po` — translator-edited message files per locale (e.g. `mayo-events-manager-es_ES.po`).
- `mayo-events-manager-{locale}.mo` — compiled message catalogs that WordPress loads at runtime.
- `mayo-events-manager-{locale}-{handle}.json` — JSON message catalogs for translatable strings used in JavaScript bundles. The `{handle}` matches the registered script handle (`admin-bundle`, `mayo-admin`, `mayo-public`).

WordPress automatically loads `.mo` files via `load_plugin_textdomain()`, and JavaScript translations via `wp_set_script_translations()`. Both are wired up in `mayo-events-manager.php` and the relevant component initializers.

## Creating or updating the POT file

The recommended approach is [WP-CLI](https://developer.wordpress.org/cli/commands/i18n/make-pot/):

```bash
# From the plugin root
wp i18n make-pot . languages/mayo-events-manager.pot --slug=mayo-events-manager
```

This walks the PHP and built JavaScript files and extracts every `__()`, `_e()`, `_x()`, `_n()`, `esc_html__()`, etc. call into a fresh template.

If you don't have WP-CLI installed, you can use [`makepot.php`](https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/dotorg/makepot/) or the standard `xgettext` tool with appropriate keyword flags.

## Translating to a new locale

1. Copy `mayo-events-manager.pot` to `mayo-events-manager-{locale}.po` (for example `mayo-events-manager-fr_FR.po`).
2. Open the new file in [Poedit](https://poedit.net/) or your editor of choice and translate the `msgstr` entries.
3. Save it. Poedit will produce a `.mo` automatically; otherwise compile with `msgfmt`:

   ```bash
   msgfmt mayo-events-manager-fr_FR.po -o mayo-events-manager-fr_FR.mo
   ```

4. Generate per-script JSON files for the JavaScript translations (requires WP-CLI):

   ```bash
   wp i18n make-json languages/ --no-purge
   ```

5. Drop the resulting `.po`, `.mo`, and `.json` files in this `languages/` directory.

## Translating on WordPress.org

Once the plugin is hosted on WordPress.org, translations are also coordinated through [translate.wordpress.org](https://translate.wordpress.org/). Locale packages installed by users will live in `wp-content/languages/plugins/` and take precedence over files shipped here.

## Submitting translations

Pull requests adding or updating `.po` / `.mo` files for a locale are welcome. Please regenerate the POT via WP-CLI before submitting so the translator comments and source line references stay current.
