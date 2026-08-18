<?php

namespace Drupal\camera_capture\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Random;
// Import interface for constants.
use Drupal\Core\File\FileSystemInterface;

/**
 * Form to capture a photo and a 10-second video from the browser camera.
 */
class CameraCaptureForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'camera_capture_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Root wrapper ensures JS can scope and detect readiness.
    $form['root'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'camera-capture-root',
        'class' => ['camera-capture-root'],
      ],
    ];

    // Instructions.
    $form['root']['instructions'] = [
      '#type' => 'markup',
      '#markup' => '<p>Use your camera to capture a photo or record a 10-second video.</p>',
    ];

    // Live preview (video) and hidden canvas.
    $form['root']['camera_preview'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'camera-preview-container',
        'class' => ['camera-preview-container'],
      ],
      'video' => [
        '#type' => 'html_tag',
        '#tag' => 'video',
        '#value' => '',
        '#attributes' => [
          'id' => 'camera-preview',
          'autoplay' => 'autoplay',
          'playsinline' => 'playsinline',
        ],
      ],
      'br' => [
        '#type' => 'markup',
        '#markup' => '<br />',
      ],
      'canvas' => [
        '#type' => 'html_tag',
        '#tag' => 'canvas',
        '#value' => '',
        '#attributes' => [
          'id' => 'camera-canvas',
          'width' => '640',
          'height' => '480',
          'style' => 'display:none;',
        ],
      ],
    ];

    // Controls (buttons via FAPI html_tag).
    $form['root']['controls'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['camera-controls']],
      'capture_btn' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Capture Photo'),
        '#attributes' => [
          'type' => 'button',
          'id' => 'capture-btn',
          'class' => ['button', 'button--primary'],
        ],
      ],
      'record_btn' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Record 10s Video'),
        '#attributes' => [
          'type' => 'button',
          'id' => 'record-btn',
          'class' => ['button'],
        ],
      ],
      'stop_btn' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Stop'),
        '#attributes' => [
          'type' => 'button',
          'id' => 'stop-btn',
          'class' => ['button'],
        ],
      ],
    ];

    // Recorded video preview.
    $form['root']['video_preview'] = [
      '#type' => 'html_tag',
      '#tag' => 'video',
      '#value' => '',
      '#attributes' => [
        'id' => 'video-preview',
        'controls' => 'controls',
      ],
    ];

    // Hidden fields for captured data URLs (populated by camera.js).
    $form['root']['captured_image'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'captured-image'],
    ];
    $form['root']['captured_video'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'captured-video'],
    ];

    // Submit.
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save Media'),
      ],
    ];

    // Attach the JS library.
    $form['#attached']['library'][] = 'camera_capture/camera';

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Validate that at least one media (image or video) was captured.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $image_data = (string) $form_state->getValue('captured_image');
    $video_data = (string) $form_state->getValue('captured_video');

    if ($image_data === '' && $video_data === '') {
      $form_state->setErrorByName('captured_image', $this->t('Please capture a photo or record a video before saving.'));
    }
    if ($image_data !== '' && strpos($image_data, 'data:image/png;base64,') !== 0) {
      $form_state->setErrorByName('captured_image', $this->t('Invalid image data.'));
    }
    if ($video_data !== '' && strpos($video_data, 'data:video/webm;base64,') !== 0) {
      $form_state->setErrorByName('captured_video', $this->t('Invalid video data.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $image_data = (string) $form_state->getValue('captured_image');
    $video_data = (string) $form_state->getValue('captured_video');

    // Prepare public scheme.
    $fs = \Drupal::service('file_system');
    $directory = 'public://';
    $fs->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    /** @var \Drupal\file\FileRepositoryInterface $file_repository */
    $file_repository = \Drupal::service('file.repository');

    $random = new Random();
    $timestamp = \Drupal::time()->getRequestTime();
    $saved_any = FALSE;

    // Save image.
    if ($image_data !== '' && strpos($image_data, 'data:image/png;base64,') === 0) {
      $raw = substr($image_data, strlen('data:image/png;base64,'));
      $decoded = base64_decode($raw);
      if ($decoded === FALSE) {
        $this->messenger()->addError($this->t('Failed to decode image data.'));
      }
      else {
        $filename = sprintf('camera_photo_%d_%s.png', $timestamp, $random->name(8));
        $uri = $directory . $filename;
        // Use FileSystemInterface constant here:
        $file = $file_repository->writeData($decoded, $uri, FileSystemInterface::EXISTS_RENAME);
        if ($file) {
          $saved_any = TRUE;
          $this->messenger()->addStatus($this->t('Photo saved: @uri', ['@uri' => $file->getFileUri()]));
        }
        else {
          $this->messenger()->addError($this->t('Failed to save photo.'));
        }
      }
    }

    // Save video.
    if ($video_data !== '' && strpos($video_data, 'data:video/webm;base64,') === 0) {
      $raw = substr($video_data, strlen('data:video/webm;base64,'));
      $decoded = base64_decode($raw);
      if ($decoded === FALSE) {
        $this->messenger()->addError($this->t('Failed to decode video data.'));
      }
      else {
        $filename = sprintf('camera_video_%d_%s.webm', $timestamp, $random->name(8));
        $uri = $directory . $filename;
        $file = $file_repository->writeData($decoded, $uri, FileSystemInterface::EXISTS_RENAME);
        if ($file) {
          $saved_any = TRUE;
          $this->messenger()->addStatus($this->t('Video saved: @uri', ['@uri' => $file->getFileUri()]));
        }
        else {
          $this->messenger()->addError($this->t('Failed to save video.'));
        }
      }
    }

    if (!$saved_any) {
      $this->messenger()->addWarning($this->t('No media was saved. Please try again.'));
    }
  }

}
