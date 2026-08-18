/**
 * @file
 * Provides the "Take Photo" behaviour for Camera Upload fields.
 *
 * The "Take Photo" control is a <label for="..."> pointing at the managed
 * file input, which has capture="environment" set. On mobile this opens the
 * device camera as a native user gesture (required by iOS Safari). On
 * desktop the JS intercepts the click and uses getUserMedia instead.
 *
 * To make the AJAX upload reliable on iOS — where core's fileAutoUpload
 * jQuery change handler can be unreliable on a capture input — this
 * behaviour ALSO binds its own change listener to the file input that
 * triggers the hidden Upload button's mousedown directly.
 */
(function ($, Drupal) {
  'use strict';

  /**
   * Builds a File object from a canvas snapshot.
   */
  function fileFromCanvas(canvas, filename) {
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    const bytes = atob(dataUrl.split(',')[1]);
    const buffer = new Uint8Array(bytes.length);
    for (let i = 0; i < bytes.length; i++) {
      buffer[i] = bytes.charCodeAt(i);
    }
    return new File([buffer], filename, { type: 'image/jpeg' });
  }

  /**
   * Places a File into a managed_file element and triggers the AJAX upload.
   */
  function pushFileAndUpload(wrapper, file) {
    const input = wrapper.querySelector('input[type="file"]');
    if (!input) {
      return;
    }
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    triggerUpload(wrapper);
  }

  /**
   * Triggers the hidden Upload button's mousedown event via jQuery.
   */
  function triggerUpload(wrapper) {
    $(wrapper)
      .find('.js-form-submit[data-drupal-selector$="upload-button"]')
      .trigger('mousedown');
  }

  /**
   * Opens an in-browser camera modal, returns a Promise<File> on capture.
   */
  function captureWithGetUserMedia() {
    return new Promise((resolve, reject) => {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        reject(new Error('getUserMedia not available'));
        return;
      }

      const overlay = document.createElement('div');
      overlay.className = 'camera-upload-overlay';
      const video = document.createElement('video');
      video.autoplay = true;
      video.playsInline = true;
      video.className = 'camera-upload-video';
      const controls = document.createElement('div');
      controls.className = 'camera-upload-controls';
      const shutter = document.createElement('button');
      shutter.type = 'button';
      shutter.className = 'button';
      shutter.textContent = Drupal.t('Capture');
      const cancel = document.createElement('button');
      cancel.type = 'button';
      cancel.className = 'button';
      cancel.textContent = Drupal.t('Cancel');
      controls.appendChild(shutter);
      controls.appendChild(cancel);
      overlay.appendChild(video);
      overlay.appendChild(controls);
      document.body.appendChild(overlay);

      let stream = null;
      navigator.mediaDevices
        .getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
        .then((s) => {
          stream = s;
          video.srcObject = s;
          return video.play();
        })
        .catch((err) => {
          cleanup();
          reject(err);
        });

      shutter.addEventListener('click', () => {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        const file = fileFromCanvas(canvas, 'camera-' + Date.now() + '.jpg');
        cleanup();
        resolve(file);
      });

      cancel.addEventListener('click', () => {
        cleanup();
        reject(new Error('User cancelled'));
      });

      function cleanup() {
        if (stream) {
          stream.getTracks().forEach((t) => t.stop());
        }
        if (overlay.parentNode) {
          overlay.parentNode.removeChild(overlay);
        }
      }
    });
  }

  /**
   * Detects mobile/touch devices.
   */
  function isMobileDevice() {
    return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
      || (navigator.maxTouchPoints > 1 && /Mac/i.test(navigator.userAgent));
  }

  Drupal.behaviors.cameraUpload = {
    attach(context) {
      // On mobile devices, bind a direct change listener to each camera
      // upload file input so the AJAX upload fires reliably on iOS Safari
      // (where core's fileAutoUpload jQuery handler can be unreliable on
      // capture inputs). On desktop, skip this — core's fileAutoUpload
      // handles the change event, and our pushFileAndUpload already
      // triggers the upload button directly.
      if (isMobileDevice()) {
        once('camera-upload-input', '.camera-upload-managed-file input[type="file"]', context).forEach((input) => {
          input.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
              const wrapper = this.closest('.js-form-managed-file');
              if (wrapper) {
                triggerUpload(wrapper);
              }
            }
          });
        });
      }

      // Handle the "Take Photo" label click.
      once('camera-upload-capture', '.camera-upload-capture-button', context).forEach((button) => {
        button.addEventListener('click', (e) => {
          // On mobile, let the label's native `for` open the camera. Our
          // change listener above will handle the upload.
          if (isMobileDevice()) {
            return;
          }

          // On desktop, prevent the label from opening the file picker and
          // use getUserMedia instead.
          e.preventDefault();
          const wrapper = button.closest('.js-form-managed-file');
          if (!wrapper) {
            return;
          }

          captureWithGetUserMedia()
            .then((file) => pushFileAndUpload(wrapper, file))
            .catch(() => {
              // Fallback: let the native input open.
              const input = wrapper.querySelector('input[type="file"]');
              if (input) {
                input.click();
              }
            })
            .catch((err) => {
              if (err && err.message !== 'User cancelled' && err.message !== 'No file selected') {
                console.error('Camera Upload capture failed:', err);
              }
            });
        });
      });
    },
  };
})(jQuery, Drupal);