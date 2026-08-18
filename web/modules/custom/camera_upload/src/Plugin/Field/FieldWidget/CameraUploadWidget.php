<?php

namespace Drupal\camera_upload\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;

/**
 * Plugin implementation of the 'camera_upload' widget.
 *
 * Extends the core Image widget with a "Take Photo" control and an explicit
 * "Add another item" button. The add-more button uses the standard Drupal
 * WidgetBase AJAX mechanism so new empty rows appear reliably on both
 * desktop and mobile.
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
   *
   * Overrides FileWidget::formMultipleElements() to drive the number of
   * rendered rows from the field state's items_count (like WidgetBase) and
   * to add an "Add another item" button using WidgetBase's standard AJAX
   * submit/callback. This gives a reliable "add another" on both desktop
   * and mobile, independent of the file upload AJAX.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];

    // Ensure items_count is initialized in field state (WidgetBase::form()
    // does this, but only if the field state doesn't exist yet).
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    if (!isset($field_state['items_count'])) {
      $field_state['items_count'] = count($items);
      static::setWidgetState($parents, $field_name, $form_state, $field_state);
    }

    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $is_unlimited = $cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $is_multiple = $is_unlimited || $cardinality > 1;

    // Determine the number of widgets to display. For unlimited cardinality
    // use items_count from field state so the "Add another item" button can
    // grow the form; otherwise use the cardinality.
    if ($is_unlimited) {
      $max = $field_state['items_count'] ?? count($items);
    }
    else {
      $max = $cardinality - 1;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();

    $id_prefix = implode('-', array_merge($parents, [$field_name]));
    // The AJAX wrapper ID must match the one set by FileWidget::processMultiple(),
    // which uses $element['#id'] . '-ajax-wrapper'. The element #id is
    // 'edit-<id-prefix-with-underscores-replaced-by-hyphens>'.
    $wrapper_id = 'edit-' . str_replace('_', '-', $id_prefix) . '-ajax-wrapper';

    $elements = [];

    $delta = 0;
    foreach ($items as $item) {
      if ($delta > $max) {
        break;
      }
      $element = [
        '#title' => $title,
        '#description' => $description,
      ];
      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);

      if ($element) {
        if ($is_multiple) {
          $element['_weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
            '#title_display' => 'invisible',
            '#delta' => $max,
            '#default_value' => $item->_weight ?: $delta,
            '#weight' => 100,
          ];
        }
        // Hide the weight select on empty (no file) rows — it's only needed
        // for reordering rows that have uploaded files.
        if (empty($item->target_id)) {
          $element['_weight']['#access'] = FALSE;
        }
        $elements[$delta] = $element;
        $delta++;
      }
    }

    $empty_single_allowed = ($cardinality == 1 && $delta == 0);
    $empty_multiple_allowed = ($is_unlimited || $delta < $cardinality) && !$form_state->isProgrammed();

    if ($empty_single_allowed || $empty_multiple_allowed) {
      $items->appendItem();
      $element = [
        '#title' => $title,
        '#description' => $description,
      ];
      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);
      if ($element) {
        $element['#required'] = ($element['#required'] && $delta == 0);
        // Hide the weight select on the empty (upload) row.
        if ($is_multiple && isset($element['_weight'])) {
          $element['_weight']['#access'] = FALSE;
        }
        $elements[$delta] = $element;
      }
    }

    if ($is_multiple) {
      $elements['#file_upload_delta'] = $delta;
      $elements['#type'] = 'details';
      $elements['#open'] = TRUE;
      $elements['#theme'] = 'file_widget_multiple';
      $elements['#theme_wrappers'] = ['details'];
      $elements['#process'] = [[\Drupal\file\Plugin\Field\FieldWidget\FileWidget::class, 'processMultiple']];
      $elements['#title'] = $title;
      $elements['#description'] = $description;
      $elements['#field_name'] = $field_name;
      $elements['#language'] = $items->getLangcode();
      $field_settings = $this->getFieldSettings() + ['display_field' => NULL];
      $elements['#display_field'] = (bool) $field_settings['display_field'];
      $elements['#file_upload_title'] = $this->t('Add a new file');
      $elements['#file_upload_description'] = [
        '#theme' => 'file_upload_help',
        '#description' => '',
        '#upload_validators' => $elements[0]['#upload_validators'],
        '#cardinality' => $cardinality,
      ];
      // Provide #max_delta and #cardinality for the addMoreAjax callback.
      $elements['#max_delta'] = $delta;
      $elements['#cardinality'] = $cardinality;
    }

    // Add the "Add another item" button for unlimited cardinality fields,
    // using WidgetBase's standard AJAX submit/callback. The wrapper ID
    // matches the one FileWidget::processMultiple() sets via #prefix.
    if ($is_unlimited && !$form_state->isProgrammed()) {
      $elements['add_more'] = [
        '#type' => 'submit',
        '#name' => strtr($id_prefix, '-', '_') . '_add_more',
        '#value' => $this->t('Add another item'),
        '#attributes' => ['class' => ['field-add-more-submit']],
        '#button_type' => 'small',
        '#limit_validation_errors' => [],
        '#submit' => [[static::class, 'addMoreSubmit']],
        '#ajax' => [
          'callback' => [\Drupal\Core\Field\WidgetBase::class, 'addMoreAjax'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
        '#weight' => 1000,
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Add a "Take Photo" control rendered as a <label>. On mobile (especially
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

    $element['#attributes']['class'][] = 'camera-upload-managed-file';
    $element['#attached']['library'][] = 'camera_upload/capture';

    // Force single-file mode here (before valueCallback runs) so the
    // managed_file value callback handles a single UploadedFile rather
    // than trying array_filter() on it as if #multiple were TRUE.
    $element['#multiple'] = FALSE;

    $element['#process'][] = [static::class, 'processCaptureAttribute'];

    return $element;
  }

  /**
   * Submission handler for the "Add another item" button.
   *
   * Increments items_count like WidgetBase::addMoreSubmit, but also clears
   * stale file upload input for this field so the managed_file value
   * callback doesn't try to reprocess a previous upload (which causes a
   * TypeError when #multiple is FALSE).
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $field_name = $element['#field_name'];
    $parents = $element['#field_parents'];

    // Increment the items count.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    $field_state['items_count'] = ($field_state['items_count'] ?? 0) + 1;
    static::setWidgetState($parents, $field_name, $form_state, $field_state);

    // Clear stale file upload input for this field so the managed_file
    // value callback does not try to reprocess a previous upload during
    // the form rebuild. The uploaded files are already stored as FIDs in
    // the field state and will be preserved.
    $user_input = $form_state->getUserInput();
    $field_input = NestedArray::getValue($user_input, array_merge($parents, [$field_name]));
    if (is_array($field_input)) {
      foreach ($field_input as $delta => $value) {
        if (isset($value['upload']) && $value['upload'] === '') {
          // Already empty, skip.
          continue;
        }
        // Clear the upload key to prevent reprocessing.
        $field_input[$delta]['upload'] = '';
      }
      NestedArray::setValue($user_input, array_merge($parents, [$field_name]), $field_input);
      $form_state->setUserInput($user_input);
    }

    $form_state->setRebuild();
  }

  /**
   * Form API #process callback: adds capture="environment" to the upload input,
   * forces single-file mode, links the "Take Photo" label, and hides the
   * "Take Photo" control on rows that already have a file.
   */
  public static function processCaptureAttribute(array $element, FormStateInterface $form_state, array &$form) {
    if (isset($element['upload'])) {
      $element['upload']['#attributes']['capture'] = 'environment';
      // Force single-file mode — iOS Safari does not fire change reliably on
      // a multiple+capture input.
      $element['upload']['#multiple'] = FALSE;

      if (isset($element['camera_capture']) && !empty($element['upload']['#id'])) {
        $element['camera_capture']['#attributes']['for'] = $element['upload']['#id'];
      }
    }

    // Hide the "Take Photo" control when this row already has an uploaded file.
    $fids = $element['#value']['fids'] ?? ($element['fids']['#value'] ?? []);
    if (!empty($fids) && isset($element['camera_capture'])) {
      $element['camera_capture']['#access'] = FALSE;
    }

    return $element;
  }

}