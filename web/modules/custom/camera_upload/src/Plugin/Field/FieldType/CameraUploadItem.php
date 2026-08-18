<?php

namespace Drupal\camera_upload\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * Plugin implementation of the 'camera_upload' field type.
 *
 * Extends the core Image field type. The data storage is identical to an
 * image field; the difference is the widget which exposes a camera capture
 * button and the watermarking applied on entity presave.
 */
#[FieldType(
  id: "camera_upload",
  label: new TranslatableMarkup("Camera Upload"),
  description: [
    new TranslatableMarkup("Captured images taken from a mobile phone camera"),
    new TranslatableMarkup("Photos are watermarked with a timestamp and the site location of the referencing Site Record."),
  ],
  category: "file_upload",
  default_widget: "camera_upload",
  default_formatter: "camera_upload",
  list_class: \Drupal\file\Plugin\Field\FieldType\FileFieldItemList::class,
  constraints: ["ReferenceAccess" => [], "FileValidation" => []],
  column_groups: [
    "file" => [
      "label" => new TranslatableMarkup("File"),
      "columns" => [
        "target_id",
        "width",
        "height",
      ],
      "require_all_groups_for_translation" => TRUE,
    ],
    "alt" => [
      "label" => new TranslatableMarkup("Alt"),
      "translatable" => TRUE,
    ],
    "title" => [
      "label" => new TranslatableMarkup("Title"),
      "translatable" => TRUE,
    ],
  ]
)]
class CameraUploadItem extends ImageItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = parent::defaultFieldSettings();
    // Camera uploads should only ever accept photographs. Restrict the
    // default extensions to JPEG which is what mobile cameras produce.
    $settings['file_extensions'] = 'jpg jpeg';
    return $settings;
  }

}