# iQuix installer modernization — design

Date: 2026-08-20
Branch: `fix/installation`
Status: approved, ready for implementation planning

## Problem

`com_iquix` installs Quix on sites whose PHP upload limits are too small to accept
the Quix package through Joomla's own installer. It downloads the package
server-side and installs each bundled extension one at a time.

Three things have since broken it:

1. **The server it talks to is gone.** Every API constant points at
   `https://www.themexpert.com/index.php?option=com_digicom&task=responses`
   (DigiCom). Licensing moved to FluentCart at `https://my.converslabs.com`.
2. **It does not run on Joomla 6, and its install step does not run on Joomla 4 or 5.**
   J6 ships no `libraries/classmap.php`, so `JFile`, `JFolder`, `JText`, `JFactory`,
   `JLog`, `JURI`, `JRequest`, `JUpdate` and `JUpdater` resolve to nothing — the
   failures are silent, not fatal. Independently,
   `JModelLegacy::getInstance('Install', 'InstallerModel')` loads from
   `administrator/components/com_installer/models/`, a directory removed in J4, so
   every `install*` task is already broken on J4 and J5.
3. **It has exploitable holes.** Enumerated under "Security" below.

## Goals

- Talk to the FluentCart license server and the current package sources.
- Run correctly on Joomla 4, 5 and 6.
- Close the security holes.
- Never ask for a license key on a site that already has a valid one.

## Non-goals

- Joomla 3 support. Quix 6.x requires J4+; the compat branches go.
- The `full` (pre-bundled package) installer mode. `launcher` is the whole point
  of the extension; `full` exists only to be toggled by a routine that rewrites
  `bootstrap.php`'s own source at runtime.
- Database backup. A dead `backupDatabase` JS function survives with no PHP
  behind it and is already spliced out of the call chain. Backups happen before
  upgrade, outside this tool.

## Architecture

The outer shell stays: the `com_iquix` manifest, the four-step wizard, and the
`controller=<name>&task=<name>` AJAX contract the existing JS speaks. Everything
behind it is replaced.

New `setup/lib/`, one responsibility per class. `setup/controllers/*` become thin
adapters over it so the JS contract does not change.

| Class | Responsibility |
| --- | --- |
| `Config` | Every URL, product id and timeout, in one file |
| `Router` | Whitelisted controller→task map; ACL and CSRF gate; JSON responses |
| `Store` | Read/write `#__quix_configs` |
| `LicenseClient` | FluentCart `activate_license` / `check_license` / `deactivate_license` |
| `PackageSource` | Resolve a download URL and expected sha256 |
| `Downloader` | Streamed download: TLS verification, size cap, hash check, tmp storage |
| `PackageInstaller` | Extract, then install each sub-extension in turn |

### Endpoints

| Purpose | URL |
| --- | --- |
| License API | `https://my.converslabs.com/?fluent-cart=<action>` |
| Pro update XML | `https://my.converslabs.com/?fcdc_joomla=update&pid=116` |
| Pro download | `<downloadurl>` from that XML + license credentials |
| Free update XML | `https://raw.githubusercontent.com/themexpert/quix-free/main/jed.xml` |
| iQuix self-update | `https://raw.githubusercontent.com/themexpert/iquix/master/mainfest.xml` (unchanged; GitHub, not a ThemeXpert API) |

Product id 116 is the legacy DigiCom pid, sent as `item_id`. The license server
translates 116 to FluentCart product 662 internally. The client must keep sending
116.

No `themexpert.com` API endpoint remains anywhere in the codebase after this
change, including the `#__update_sites` row the installer writes.

## Licensing

`LicenseClient` mirrors `QuixHelperLicense` from `com_quix`
(`src/com_quix/admin/helpers/license.php`) and writes **the same
`#__quix_configs` row names** it reads: `license_key`, `activation_hash`,
`license_status`, `license_expires`, `license_checked`, `activated`. The
freshly-installed Quix is therefore already activated when the wizard finishes;
the user never enters the key twice. This shared table is the integration point
between the two codebases.

The `store_url` and `item_id` override rows are honoured, matching `com_quix`, so
a dev install can be pointed at `converslab.test`.

Requests carry a browser-style `User-Agent`. Cloudflare's Browser Integrity Check
403s unrecognised agents at the edge, and an IP allow rule does not bypass that
layer. Real client identity travels in `X-Quix-Client`.

### Skipping the key prompt

On entering the Validation step:

1. A `license_key` row exists → call `check_license`.
   - `valid` → skip the form. Show the masked key and enable Next.
   - anything else → show the form, pre-filled, with the server's reason.
2. No `license_key`, but legacy `username` / `key` rows from old iQuix exist →
   try that `key` as a FluentCart license key before asking. The DigiCom→FluentCart
   migrator carried customer auth keys over verbatim, so an old key is very often
   the current license key.
3. Otherwise → show the empty form.

A "use a different key" link is always available.

### Free fallback

No key, or a key the server rejects, offers Quix Free from the public GitHub
release. Its XML carries a `sha256`, which is verified. Pro requires a key; the
download endpoint returns `403 Missing license key.` without one.

## Download and install flow

1. `PackageSource::resolve()` — Pro: GET the update XML, read `<downloadurl>`,
   append `license_key`, `activation_hash`, `site_url`. Free: GET the GitHub XML,
   read `<downloadurl>` and `<sha256>`.
2. `Downloader::fetch()` — stream to Joomla's `tmp_path` in a randomly-named
   subdirectory, mode 0600.
3. If the body is not a zip, it is a server error string (e.g.
   `Missing license key.`). Surface it verbatim rather than reporting an
   extraction failure.
4. Verify sha256 when the source supplied one. A missing hash logs a warning; it
   does not pass silently.
5. Extract the package, read its `pkg_quix.xml`, and install each entry in
   `<files>` in turn via `Installer::getInstance()->install($path)`. Driving the
   list from the manifest instead of hardcoded arrays means adding an extension to
   Quix no longer requires an iQuix release.
6. `syncDb` still runs the package's own `pkg.script.php` postflight, guarded by
   an existence check.
7. Write the `#__update_sites` row: name `Quix Update Site` (matching
   `com_quix`), location the FluentCart update URL, `extra_query` carrying the
   license credentials. Delete any stale row pointing at `themexpert.com`.
8. Delete the downloaded archive and the extraction directory, on success and on
   failure alike.

## Security

Each item is a defect in the current code, not a hardening nice-to-have.

1. **Arbitrary method dispatch.** `bootstrap.php` and each controller's
   `execute()` call `$this->$task()` for any method that exists, and `require` a
   filename built from user input. Replaced by an explicit
   controller→task whitelist in `Router`; anything else is a 403.
2. **No CSRF token and no ACL check on any task.** Installation of arbitrary
   packages is reachable by CSRF against a logged-in Super User. Every task now
   requires `core.admin` and `Session::checkToken()`; the wizard views embed the
   token.
3. **Credential disclosure.** `installation&task=getAuthInfo` returns every
   `#__quix_configs` row as JSON, license key included. Deleted. Replaced by
   `license&task=status`, returning only `{hasLicense, maskedKey, status}`.
4. **Local file inclusion.** `installing.php` interpolates the `method` request
   parameter into an `include` path. Whitelisted to `network` and `directory`.
5. **TLS verification disabled.** `CURLOPT_SSL_VERIFYPEER` and
   `CURLOPT_SSL_VERIFYHOST` are `false` on the package download and the
   self-update check — a MITM can substitute the archive, which is then installed.
   Both enabled, with capped redirects restricted to https and a download size cap.
6. **Paid package left web-readable.** The archive is written to
   `administrator/components/com_iquix/setup/packages/pkg_quix.zip`, a guessable
   public URL, and `cleanupZipFiles()` begins with `return true;` — so it is never
   deleted. Downloads move to Joomla's `tmp_path` and cleanup actually runs.
7. **No integrity check.** The md5 comparison is commented out. sha256 verified
   when available, per the flow above.
8. **Secrets in logs.** `debug()` logs full API URLs with the auth key in the
   query string, and `downloadDebugLog` serves that file. Keys are masked in all
   log output; no key ever reaches a logged URL. `downloadDebugLog` stays — it is
   genuinely useful for support — behind the ACL and token gate.
9. **Self-modifying source.** `updatePackage()` rewrites `bootstrap.php` with
   `str_ireplace` at runtime to flip `QX_INSTALLER`. Deleted along with the
   `full`/`launcher` split it existed to toggle.

## Joomla 4/5/6 compatibility

`Joomla\CMS\Filesystem\*` does not exist in J6. The framework package
`Joomla\Filesystem\*` exists in J4, J5 and J6 and is the only safe choice; all
filesystem calls use it. `Factory::getConfig()`, `Factory::getDbo()`,
`Factory::getUser()`, `Session::checkToken()` and
`Installer::getInstance()->install($path)` are present in J6 and are used
unconditionally, with no version branching.

## Verification

There is no test runner in this repository. Verification is a real installation
against the local Joomla 6.1.2 site at `/Users/abu/Sites/Joomla6`
(`joomla6.test`), walking the wizard for three cases:

1. No license — the free package installs and its sha256 verifies.
2. A valid Pro key — activation succeeds, the Pro package installs, and
   `#__quix_configs` afterwards satisfies `com_quix`'s own activation check.
3. An already-licensed site — the key form is skipped entirely.

Each result is reported with the output that produced it.
