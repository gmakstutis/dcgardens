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

    // Ensure items_count is initialised in field state to the number of
    // items that actually reference a file. WidgetBase::form() initialises
    // it to count($items), which for an empty field is 0 and works fine,
    // but for an existing entity with N stored files we want items_count
    // to start at N so all existing files render plus one empty upload row.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    if (!isset($field_state['items_count'])) {
      $uploaded = 0;
      foreach ($items as $item) {
        if (!empty($item->target_id)) {
          $uploaded++;
        }
      }
      $field_state['items_count'] = $uploaded;
      static::setWidgetState($parents, $field_name, $form_state, $field_state);
    }

    // Load items from field state (uploaded files that have not been saved to
    // the entity yet). This mirrors FileWidget::formMultipleElements() so
    // files uploaded via AJAX persist across form rebuilds.
    if (isset($field_state['items'])) {
      $items->setValue($field_state['items']);
    }

    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $is_unlimited = $cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $is_multiple = $is_unlimited || $cardinality > 1;

    // Determine the number of widgets to display. For unlimited cardinality
    // the row count is driven by items_count (the number of uploaded items)
    // so the trailing-empty-row block always adds exactly one empty field at
    // the end. Otherwise use the cardinality.
    if ($is_unlimited) {
      $max = $field_state['items_count'];
      // Auto-uploads can grow $items beyond items_count (which is only
      // bumped by addMoreSubmit); keep $max at least as large as the
      // highest populated delta so _weight ranges and #max_delta stay sane.
      if (count($items) - 1 > $max) {
        $max = count($items) - 1;
      }
    }
    else {
      $max = $cardinality - 1;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();

    $id_prefix = implode('-', array_merge($parents, [$field_name]));

    $elements = [];

    // Render one row per existing (uploaded) item. The empty upload row is
    // added by the trailing-empty-row block below, so the form always ends
    // with exactly one empty field when more uploads are allowed. This
    // matches the FileWidget behaviour the rest of the widget relies on.
    $delta = 0;
    foreach ($items as $item) {
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
      $elements['#process'] = [
        [\Drupal\file\Plugin\Field\FieldWidget\FileWidget::class, 'processMultiple'],
        [static::class, 'hideWeightFields'],
        [static::class, 'addMoreButtonProcess'],
      ];
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
    // using WidgetBase's standard AJAX submit/callback. The wrapper ID is
    // filled in by addMoreButtonProcess() (which runs after
    // FileWidget::processMultiple sets #prefix with the real, AJAX-unique
    // #id) because Html::getUniqueId appends a random suffix on AJAX
    // requests that cannot be predicted at build time.
    if ($is_unlimited && !$form_state->isProgrammed()) {
      $elements['add_more'] = [
        '#type' => 'submit',
        '#name' => strtr($id_prefix, '-', '_') . '_add_more',
        '#value' => $this->t('Add another item'),
        '#attributes' => ['class' => ['field-add-more-submit', 'use-ajax']],
        '#button_type' => 'small',
        '#limit_validation_errors' => [array_merge($parents, [$field_name])],
        '#submit' => [[static::class, 'addMoreSubmit']],
        '#ajax' => [
          'callback' => [static::class, 'addMoreAjax'],
          'wrapper' => '',
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
        'class' => ['camera-upload-capture-button', 'btn', 'btn-success', 'mb-4'],
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
   * Captures any files uploaded in this submission into field_state['items']
   * (mirroring FileWidget::submit, which only runs for the upload/remove
   * button) so that photos selected via "Take Photo" are preserved when the
   * form rebuilds. Without this, the managed_file value callback uploads
   * the file during form processing but the FID never reaches
   * field_state['items'], and formMultipleElements() drops the row on the
   * rebuild — so "Add another item" appears to do nothing.
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $field_name = $element['#field_name'];
    $parents = $element['#field_parents'];

    $field_state = static::getWidgetState($parents, $field_name, $form_state);

    // Capture files uploaded in this submission (valueCallback has already
    // run for every delta and populated form_state values with fids). Filter
    // out empty rows and split multi-fid items, matching FileWidget::submit.
    $field_values = NestedArray::getValue($form_state->getValues(), array_merge($parents, [$field_name]));
    $submitted_values = [];
    if (is_array($field_values)) {
      foreach ($field_values as $delta => $submitted_value) {
        if (is_array($submitted_value) && !empty($submitted_value['fids'])) {
          foreach ($submitted_value['fids'] as $fid) {
            $new_value = $submitted_value;
            $new_value['fids'] = [$fid];
            $submitted_values[] = $new_value;
          }
        }
      }
    }
    $field_state['items'] = array_values($submitted_values);

    // Keep items_count consistent with the number of uploaded items so
    // downstream code (e.g. WidgetBase::addMoreAjax) sees a sane value.
    $field_state['items_count'] = count($field_state['items']);
    static::setWidgetState($parents, $field_name, $form_state, $field_state);

    // Clear user input for the field (like FileWidget::submit) so the
    // rebuilt form doesn't reprocess stale per-delta input against
    // re-indexed deltas.
    NestedArray::setValue($form_state->getUserInput(), array_merge($parents, [$field_name]), NULL);

    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the "Add another item" button.
   *
   * Wraps WidgetBase::addMoreAjax but logs debug info to watchdog to help
   * diagnose issues with repeated clicks.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    return \Drupal\Core\Field\WidgetBase::addMoreAjax($form, $form_state);
  }

  /**
   * Form API #process callback that runs after FileWidget::processMultiple().
   *
   * Sets the "Add another item" button's AJAX wrapper to the real wrapper ID
   * from the parent element's #prefix, which processMultiple built using the
   * AJAX-unique #id. Html::getUniqueId() appends a random suffix on AJAX
   * requests, so the wrapper ID cannot be hardcoded at build time.
   */
  public static function addMoreButtonProcess(array $element, FormStateInterface $form_state, array &$form) {
    if (isset($element['add_more']['#ajax']['wrapper'])) {
      // FileWidget::processMultiple sets #prefix to
      // '<div id="<wrapper-id>">'. Extract that id.
      if (preg_match('/<div id="([^"]+)"/', $element['#prefix'] ?? '', $m)) {
        $element['add_more']['#ajax']['wrapper'] = $m[1];
      }
      elseif (!empty($element['#id'])) {
        $element['add_more']['#ajax']['wrapper'] = $element['#id'] . '-ajax-wrapper';
      }
    }
    return $element;
  }

  /**
   * Form API #process callback for the multiple-element wrapper.
   *
   * Runs after FileWidget::processMultiple(), which re-adds visible _weight
   * selects on uploaded-file rows. We hide all _weight fields since row
   * reordering is not needed for camera uploads.
   */
  public static function hideWeightFields(array $element, FormStateInterface $form_state, array &$form) {
    foreach (\Drupal\Core\Render\Element::children($element) as $key) {
      if (isset($element[$key]['_weight'])) {
        $element[$key]['_weight']['#access'] = FALSE;
      }
      // The add_more button should not have a _weight at all.
      if ($key === 'add_more' && isset($element[$key]['_weight'])) {
        unset($element[$key]['_weight']);
      }
    }
    return $element;
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