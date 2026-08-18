<?php

namespace Drupal\camera_upload\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;

/**
 * Plugin implementation of the 'camera_upload' widget.
 *
 * Adds a "Take Photo" button to the standard image widget. The button simply
 * clicks the managed_file element's own native file input, which already has
 * the core upload/AJAX handlers bound to it. A #process callback adds the
 * HTML `capture="environment"` attribute to that input so mobile browsers
 * open the rear camera directly when it is clicked.
 */
#[FieldWidget(
  id: 'camera_upload',
  label: new TranslatableMarkup('Camera Upload'),
  field_types: ['camera_upload'],
)]
class CameraUploadWidget extends ImageWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'capture_method' => 'getUserMedia',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['capture_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Camera capture method'),
      '#default_value' => $this->getSetting('capture_method'),
      '#options' => [
        'getUserMedia' => $this->t('In-browser camera (getUserMedia)'),
        'capture_attribute' => $this->t('Native capture input (capture attribute)'),
      ],
      '#description' => $this->t('The in-browser method shows a live camera preview in the page. The native method uses the device\'s built-in capture chooser and tends to be more reliable on older devices.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $method = $this->getSetting('capture_method');
    $label = $method === 'capture_attribute' ? $this->t('Native capture input') : $this->t('In-browser camera');
    $summary[] = $this->t('Capture method: @method', ['@method' => $label]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Add a "Take Photo" button rendered as a <label>. On mobile (especially
    // iOS Safari) calling input.click() from JS within a button handler is
    // blocked because it is not treated as a user gesture. A <label for="...">
    // pointing at the file input is a native user gesture, so iOS opens the
    // camera reliably. The `for` attribute is set in processCaptureAttribute()
    // once the upload input's ID is known.
    $element['camera_capture'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#value' => $this->t('Take Photo'),
      '#attributes' => [
        'class' => ['camera-upload-capture-button', 'button'],
        'data-camera-upload-delta' => $delta,
      ],
      '#weight' => -20,
    ];

    // Mark the managed_file element so our #process callback can find it and
    // so the JS behaviour can locate the file input to click.
    $element['#attributes']['class'][] = 'camera-upload-managed-file';
    $element['#attached']['library'][] = 'camera_upload/capture';

    // Append a #process callback that runs after the parent managed_file
    // processing so we can add the HTML capture="environment" attribute to
    // the real file input that core produced.
    $element['#process'][] = [static::class, 'processCaptureAttribute'];

    return $element;
  }

  /**
   * Form API #process callback: adds capture="environment" to the upload input
   * and hides the "Take Photo" button on rows that already have a file.
   *
   * Runs after the parent managed_file processing so the final FIDs are known.
   */
  public static function processCaptureAttribute(array $element, FormStateInterface $form_state, array &$form) {
    if (isset($element['upload'])) {
      $element['upload']['#attributes']['capture'] = 'environment';
      // The core file/image widget sets #multiple on the upload input for
      // unlimited cardinality fields. iOS Safari does not fire a change
      // event reliably on a multiple+capture input, which prevents the AJAX
      // upload from running after a photo is taken. Force single-file mode
      // here so the browser opens the camera for one photo at a time and
      // fires change correctly.
      $element['upload']['#multiple'] = FALSE;

      // Link the "Take Photo" label to this input so clicking it is a native
      // user gesture that opens the camera on iOS.
      if (isset($element['camera_capture']) && !empty($element['upload']['#id'])) {
        $element['camera_capture']['#attributes']['for'] = $element['upload']['#id'];
      }
    }

    // Hide the "Take Photo" button when this row already has an uploaded file.
    $fids = $element['#value']['fids'] ?? ($element['fids']['#value'] ?? []);
    if (!empty($fids) && isset($element['camera_capture'])) {
      $element['camera_capture']['#access'] = FALSE;
    }

    return $element;
  }

}