# Changelog

This changelog documents **only the two most recent published GitHub releases** of `local_forumreactions`, based on comparisons of each release tag against the immediately preceding published tag. Later commits on `master`, including the post-`1.0.3` JavaScript build, are not part of these release notes.

## [1.0.3] - 2026-10-19

Comparison: [1.0.2...1.0.3](https://github.com/EduardoKrausME/moodle-local_forumreactions/compare/1.0.2...1.0.3)

### Changed

- Updated the PHP 8.3 CI test target from Moodle `MOODLE_502_STABLE` to `MOODLE_503_STABLE` (Moodle 5.3).
- Updated CI database services from PostgreSQL 16 to **PostgreSQL 17** and from MariaDB 10 to **MariaDB 11.4**. Adjusted the MariaDB empty-root-password environment setting and replaced the `mysqladmin ping` check with `healthcheck.sh --connect --innodb_initialized`.
- Hardened the release workflow against shell injection from commit messages by assigning the message to the `RELEASE_MESSAGE` environment variable rather than interpolating the GitHub event expression directly into a shell command.
- Added release workflow concurrency configuration using a release-specific group with `cancel-in-progress: false` and `queue: max`.
- Added `pix/icon.png`, providing an icon asset for the plugin.
- Updated `version.php` metadata to version `2026101900`, release `1.0.3`.

### Scope

The comparison changes only `.github/workflows/ci.yml`, `.github/workflows/release.yml`, `pix/icon.png` and `version.php`. No runtime PHP or JavaScript source files changed between these release tags.

## [1.0.2] - 2026-10-05

Comparison: [1.0.1...1.0.2](https://github.com/EduardoKrausME/moodle-local_forumreactions/compare/1.0.1...1.0.2)

### Changed

- Rebuilt the distributable `amd/build/reactions.min.js` file into a minified form. The corresponding JavaScript source file is unchanged in the tag comparison; no new application behavior is established by this diff.
- Updated `version.php` metadata from version `2026091401` / release `1.0.1` to version `2026100500` / release `1.0.2`.

### Scope

Only `amd/build/reactions.min.js` and `version.php` changed between these release tags.

---

Changes committed to `master` after the latest published release tag are intentionally excluded. These notes describe verified tagged changes, not inferred features.
