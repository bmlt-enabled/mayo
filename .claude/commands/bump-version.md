# Bump Version

Bump the plugin version number across all required files.

## Arguments
- `$ARGUMENTS` - (Optional) The new version number (e.g., "1.8.5"). If not provided, increment the patch version.

## Instructions

1. Read the current version from `package.json`

2. Determine the new version:
   - If `$ARGUMENTS` is provided and not empty, use that version
   - Otherwise, increment the patch version (e.g., 1.8.4 → 1.8.5)

3. Update the version number in these three files:
   - `mayo-events-manager.php`: Update both the `Version:` comment header and `MAYO_VERSION` constant
   - `readme.txt`: Update the `Stable tag:` line
   - `package.json`: Update the `version` field

4. Add a new changelog entry in `readme.txt` after `== Changelog ==`:
   ```
   = {new version} =
   *
   ```

5. Show the user what was changed and remind them to fill in the changelog entry.

6. Do NOT commit the changes - let the user review and commit manually.
