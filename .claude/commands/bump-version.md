# Bump Version

Bump the plugin version number across all required files.

## Arguments
- `$ARGUMENTS` - The new version number (e.g., "1.8.5")

## Instructions

1. Update the version number in these three files:
   - `mayo-events-manager.php`: Update both the `Version:` comment header and `MAYO_VERSION` constant
   - `readme.txt`: Update the `Stable tag:` line
   - `package.json`: Update the `version` field

2. Add a new changelog entry in `readme.txt` after `== Changelog ==`:
   ```
   = $ARGUMENTS =
   *
   ```

3. Show the user what was changed and remind them to fill in the changelog entry.

4. Do NOT commit the changes - let the user review and commit manually.
