# Behat Fixtures — mod_graphitoubb

## axe-core (accessibility testing)

The `@a11y` Behat tag injects axe-core into the browser to detect WCAG 2.1 A/AA
violations. The step definition in `behat_mod_graphitoubb.php` tries to load
axe-core from CDN first (`cdnjs.cloudflare.com`) and falls back gracefully with a
clear error message.

### Vendoring axe-core for offline / CI environments

If your CI environment has no CDN access, vendor the file manually:

```bash
curl -L \
  https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.0/axe.min.js \
  -o tests/behat/fixtures/axe.min.js
```

Verify the download:

```bash
sha256sum axe.min.js
# Compare against the checksum published at:
# https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.0/axe.min.js.sri
```

### Licensing

axe-core is published by Deque Systems under the MPL-2.0 license.
Source: https://github.com/dequelabs/axe-core
License text: https://github.com/dequelabs/axe-core/blob/master/LICENSE

The `axe.min.js` file is **not committed to this repository**. Add it to
`.gitignore` (or keep it as a CI artifact) and re-vendor it when upgrading.

### axe-core version

This project targets axe-core **4.10.0**. To upgrade:
1. Update the `curl` URL above.
2. Update the CDN fallback URL inside `behat_mod_graphitoubb.php`
   (`the_page_should_pass_a11y_with_no_critical()`).

### Gate

`@a11y` scenarios fail when axe-core reports any violation with `impact` =
`critical` or `serious`. Violations of `impact` = `moderate` or `minor` are
logged but do not fail the step.
