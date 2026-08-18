# DCGardens

A Drupal 11 site built with DDEV, based on the `drupal/recommended-project`
template. The codebase contains the standard Drupal layout (document root
relocated to `web/`) plus three custom field/widget modules grouped under
the **DCG** package.

## Requirements

- [DDEV](https://ddev.com/) (recommended) or any environment meeting the
  Drupal 11 requirements (PHP 8.3+, a web server, MySQL/MariaDB, etc.)
- Composer

## Quick start with DDEV

```bash
ddev config
ddev composer install
ddev drush site:install
ddev drush en camera_upload site_record_name resident_notes_name -y
ddev drush cr
```

The site is served from `web/`. Configuration, contrib modules and themes
live under `web/modules/contrib` and `web/themes/contrib` respectively.

## Project layout

```
.
├── composer.json            Project dependencies & installer paths
├── recipes/                 Drupal recipes
├── vendor/                  Composer-managed PHP dependencies
└── web/
    ├── core/                Drupal core
    ├── modules/
    │   ├── contrib/         Contrib modules (composer-installed)
    │   └── custom/          Custom modules (see below)
    ├── themes/
    │   ├── contrib/
    │   │   └── bootstrap5/  Bootstrap 5 base theme
    │   └── custom/
    │       └── b5_dcg/      Custom sub-theme of Bootstrap 5
    └── ...
```

## Custom modules

All three custom modules declare `package: DCG` so they appear together
on the Extend (`admin/modules`) page.

### Camera Upload

`web/modules/custom/camera_upload/`

Provides a new field type (`camera_upload`) based on the core `image`
field, with a widget that adds a **Take Photo** button to capture images
directly from a mobile phone camera.

- **Field type** — `CameraUploadItem` (extends `ImageItem`); restricts
  uploads to JPEG.
- **Widget** — `CameraUploadWidget` (extends `ImageWidget`); adds the
  Take Photo button and a `capture="environment"` attribute on the file
  input. The accompanying JS library (`js/camera_upload.js`) opens the
  device camera using `getUserMedia`, snapshots the frame to a canvas and
  drops the resulting `File` into the managed_file element so Drupal's
  standard AJAX upload pipeline runs. A `capture_attribute` fallback is
  available for older devices.
- **Formatter** — `CameraUploadFormatter` (extends `ImageFormatter`).
- **Watermarking** — `hook_entity_presave()` watermarks each *newly added*
  photo with a single line `site/location - YYYY-mm-dd` using GD. The site
  location is resolved from `field_site_location` (an entity_reference to
  node) with a fallback to `field_site_location_other` when the value is
  `~Other...`. Existing photos are not re-watermarked when the node is
  edited — the hook compares current file IDs against the original
  entity's referenced files.

### Site Record Name

`web/modules/custom/site_record_name/`

Hides the standard `title` field on the **Site Record** content type and
auto-generates the title on node presave.

- Title format: `<site_location> - <arrival_date>` (date as `Y-m-d`).
- `field_site_location` is an entity_reference to node; the referenced
  node's label is used as the site location name. If the value is
  `~Other...`, `field_site_location_other` is used instead.
- `field_arrival` is a `datetime` field; its start value is formatted as
  `Y-m-d`.
- Falls back to `Site Record @id` when both fields are empty, and
  truncates the final title to 255 characters.

### Resident Notes Name

`web/modules/custom/resident_notes_name/`

Hides the standard `title` field on the **Resident Notes** content type
and auto-generates the title on node presave.

- Title format: `<resident_name> - <arrival_date>` (date as `d/m/Y`).
- `field_resident_name` is a plain `string` field; its value is used
  directly.
- `field_arrival` is a `datetime` field; its start value is formatted as
  `d/m/Y`.
- Falls back to `Resident Notes @id` when both fields are empty, and
  truncates the final title to 255 characters.

## Theme

The active default theme is **B5 DCG** (`web/themes/custom/b5_dcg`), a
sub-theme of the contrib Bootstrap 5 theme. Bootstrap 5's JS bundle
(`bootstrap.bundle.js`) is loaded via the base theme's
`bootstrap5-js-latest` library, so Bootstrap components (modal, collapse,
etc.) are available in front-end templates.

## Common Drush commands

```bash
ddev drush cr                 # Clear caches
ddev drush en <module> -y     # Enable a module
ddev drush pmu <module> -y    # Uninstall a module
ddev drush sqlc               # Open a MySQL client
ddev drush uli                # Generate a one-time login link
```

## Notes

- `getUserMedia` (used by the Camera Upload widget) requires a secure
  context (HTTPS or `localhost`). DDEV serves the site over HTTPS by
  default, so the camera works on both desktop and mobile browsers.
- Configuration for content types and fields (e.g. `site_record`,
  `resident_notes`, `field_site_location`, `field_arrival`,
  `field_resident_name`, `field_site_photos`) lives in the database, not
  in version-controlled config exports in this repository.