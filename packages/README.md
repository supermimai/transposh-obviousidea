# Installable WordPress candidate

[Download the tested plugin ZIP](transposh-obviousidea-1.0.14-candidate-06c7bcc.zip?raw=true)

This is the complete WordPress plugin package built from source commit
`06c7bccaff580a51ea3b098cca6e672a711135f9` on 15 October 2026. It contains native
WP Rocket support, dictionary-cache optimizations, and the reconciled mirror
features. The same archive was installed and tested on **www2.obviousidea.com**.

| Property | Value |
| --- | --- |
| Status | Development candidate; no GitHub release or tag published |
| Plugin version displayed | 1.0.14 (existing source version retained) |
| Archive size | 1,384,033 bytes |
| Runtime files | 354, including 27 PHP files |
| Plugin folder | `transposh-translation-filter-for-wordpress` |
| SHA-256 | `1c00cd6d63ff730c7e6cf62014fda9f4eaab535aacf480e757ad343055995852` |

The filename identifies this candidate separately from the original 1.0.14
sources. It is not an automatic update or a newly numbered stable release.
WP Rocket itself and its licence are **not** bundled. The ZIP includes Transposh
only. Site settings, credentials, development tools and installed backup copies
are excluded.

On a test WordPress site, use **Plugins → Add New Plugin → Upload Plugin**, select
the ZIP and follow WordPress's replacement/activation flow. Preserve a backup
of the installed plugin and its settings first. No uninstall or translation-table
deletion is required. This package has not been deployed to production.

See the [validation notes](../docs/cache-validation-2026-10-15.md) for the complete
package install/rollback test, real translation-editor/provider checks, cache
invalidation coverage and remaining compatibility limits.

- [SHA-256 checksum](transposh-obviousidea-1.0.14-candidate-06c7bcc.sha256)
- [Per-file manifest](transposh-obviousidea-1.0.14-candidate-06c7bcc.manifest.json)

To rebuild this exact candidate from its source commit:

```sh
npm ci --ignore-scripts
python build/package.py --build-date 2026-10-15
```
