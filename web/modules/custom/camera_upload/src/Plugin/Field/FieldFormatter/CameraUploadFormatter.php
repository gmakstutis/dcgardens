<?php

namespace Drupal\camera_upload\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'camera_upload' formatter.
 */
#[FieldFormatter(
  id: 'camera_upload',
  label: new TranslatableMarkup('Camera Upload'),
  field_types: [
    'camera_upload',
  ],
)]
class CameraUploadFormatter extends ImageFormatter {
}