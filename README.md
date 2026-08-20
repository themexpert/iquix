# iQuix

iQuix (`com_iquix`) is a small Joomla component that installs the [Quix](https://www.themexpert.com) page
builder (`com_quix`) on sites whose PHP upload limits are too small for the normal Joomla
installer to handle the Quix package in one request. It downloads and unpacks Quix on the
server side, in small steps, instead of relying on a single large browser upload.

## Requirements

- Joomla 4, 5 or 6
- PHP 8.1+

## License flow (FluentCart)

Quix's paid edition is licensed through a FluentCart store. The wizard's `LicenseGate`
(`setup/lib/License/LicenseGate.php`) decides whether the user needs to be asked for a key:

- If a `license_key` or `activation_hash` is already stored, iQuix asks the license server
  to re-check it (`LicenseClient::check()`/`isActivated()`) and reuses it if it is still valid.
- If no key is stored but a legacy DigiCom auth key (`key`) is present — carried over from an
  old iQuix install — it is tried once as a FluentCart license key before falling back to the
  free edition.
- Otherwise the wizard prompts for a license key and activates it against the store.

All license requests go to `https://my.converslabs.com/?fluent-cart=<action>` by default (see
`setup/lib/Config.php`). No `themexpert.com` API is contacted.

### Overriding the endpoints for testing

`Config::storeUrl()` and `Config::itemId()` both check the `#__quix_configs` table (via
`Store::get()`) before falling back to the built-in constants. To point a test install at a
different store or product without touching code, insert rows into that table:

```sql
INSERT INTO `#__quix_configs` (`name`, `params`) VALUES
    ('store_url', 'https://staging.example.test'),
    ('item_id', '999');
```

`#__quix_configs` is shared with `com_quix`; iQuix only ever creates it with
`CREATE TABLE IF NOT EXISTS` and never migrates or alters an existing copy.

## Development

```bash
composer install
vendor/bin/phpunit --testsuite unit
```
