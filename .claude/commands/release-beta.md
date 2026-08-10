# Release Beta

Cut a **beta pre-release** from the current changelog version, then ask every reporter
whose ticket shipped in it to test their change — with a link to the built plugin zip.

This automates the flow of: tag `main` → let the release pipeline publish a GitHub
prerelease + zip → comment on each `[#NNN]` ticket in the top changelog section.

## Arguments
- `$ARGUMENTS` - (Optional) The full beta tag to create, e.g. `1.9.2-beta1`. If omitted,
  derive it from the top `readme.txt` changelog version as `<version>-beta1` (bump to
  `-beta2`, `-beta3`, … if an earlier beta tag for that version already exists).

## Background (how the pipeline behaves — see `.github/workflows/release.yml`)
- The `release` workflow triggers on **any** tag push (`on: push: tags: ['*']`).
- A tag containing `beta` is published as a **prerelease** and the WordPress.org stable
  deploy step is **skipped** — so a beta only produces a GitHub prerelease + zip, it does
  not ship to the WordPress.org stable channel.
- The release asset is always named `mayo-events-manager.zip`. For tag `<tag>` the URLs are:
  - Zip: `https://github.com/bmlt-enabled/mayo/releases/download/<tag>/mayo-events-manager.zip`
  - Release page: `https://github.com/bmlt-enabled/mayo/releases/tag/<tag>`

> All `git push`, `gh`, and `curl` calls in this flow require `dangerouslyDisableSandbox: true`
> (they hit the network and fail with TLS errors in the sandbox).

## Instructions

1. **Determine the tag.** Use `$ARGUMENTS` if provided. Otherwise read the top
   `= X.Y.Z =` section under `== Changelog ==` in `readme.txt` — that is the unreleased
   version — and form `<version>-beta1` (checking existing tags with
   `git tag --list "<version>-beta*"` to pick the next beta number). Follow the project's
   existing tag style: **no `v` prefix** (tags look like `1.9.1`, `1.9.2-beta1`).

2. **Point the tag at the latest `main`.** `git fetch origin --tags`. Confirm the tickets you
   intend to release are already merged to `origin/main`; the tag must reference
   `origin/main` (not a feature branch). Abort and tell the user if the tag already exists.

3. **Tag and push:**
   ```
   git tag -a <tag> origin/main -m "<tag>"
   git push origin <tag>
   ```

4. **Wait for the release to publish — don't post links to a build that isn't ready.**
   Poll the run to completion, then verify the release:
   ```
   gh run list --repo bmlt-enabled/mayo --workflow release.yml --limit 3
   gh run view <run-id> --repo bmlt-enabled/mayo --json status,conclusion
   gh release view <tag> --repo bmlt-enabled/mayo --json isPrerelease,assets
   ```
   Confirm `isPrerelease: true` and that `mayo-events-manager.zip` is attached, then check the
   zip URL resolves: `curl -sI -o /dev/null -w "%{http_code}\n" <zip-url>` (200, or 302 to the
   GitHub asset CDN, both mean live). If the run failed, report the failure and stop — do not
   comment on any tickets.

5. **Collect the tickets and reporters.** Extract every `#NNN` reference from the top changelog
   section (`sed -n '/== Changelog ==/,/^= [0-9]/p' readme.txt | grep -oE '#[0-9]+'`, deduped).
   For each ticket get the reporter and title:
   `gh issue view <n> --repo bmlt-enabled/mayo --json number,title,author -q '.author.login'`.

6. **Comment on each ticket** (one comment per ticket, even if the same person filed several —
   each is about that ticket's specific change) with `gh issue comment <n> --repo bmlt-enabled/mayo --body ...`.
   @-mention the reporter, summarize their change in one line drawn from its changelog entry,
   and include the zip + release-page links. Template:
   ```
   Hi @<reporter> 👋 — the change you requested here is in the **<tag>** pre-release.
   Would you mind installing it and confirming it works for you?

   <one-line summary of this ticket's change, from the changelog>

   📦 Plugin zip: https://github.com/bmlt-enabled/mayo/releases/download/<tag>/mayo-events-manager.zip
   🔖 Release notes: https://github.com/bmlt-enabled/mayo/releases/tag/<tag>

   Thanks for the report!
   ```
   Comments post as the authenticated GitHub user. If the operator filed some of the tickets
   themselves, confirm whether to include those self-authored tickets before posting.

7. **Report a summary** to the user: the tag, the release/prerelease page, the verified zip URL,
   and a list of each ticket commented on with its reporter and comment link.
