/**
 * @file
 * Provides the "Take Photo" behaviour for Camera Upload fields.
 *
 * The "Take Photo" button opens the device camera. On capture, the snapshot
 * is placed into the managed_file element's native file input via
 * DataTransfer, then the hidden "Upload" button is clicked so core's normal
 * AJAX upload runs. On unlimited-cardinality fields a new empty row appears
 * automatically after the upload completes.
 *
 * The PHP widget also adds capture="environment" to the file input so that
 * on mobile browsers the native capture chooser is used as a fallback when
 * getUserMedia is unavailable.
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
   * Places a File into a managed_file element and triggers core's AJAX upload.
   *
   * Sets the file on the input via DataTransfer, then triggers mousedown on
   * the hidden Upload button via jQuery — the exact same mechanism core's
   * fileAutoUpload.triggerUploadButton uses when a user picks a file.
   */
  function pushFileAndUpload(wrapper, file) {
    const input = wrapper.querySelector('input[type="file"]');
    if (!input) {
      console.error('Camera Upload: no file input found');
      return;
    }
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;

    // Verify the file was actually set (some browsers block this).
    if (!input.files || input.files.length === 0) {
      console.error('Camera Upload: could not set file on input (browser may block programmatic file assignment)');
      return;
    }

    // Trigger the hidden upload button's mousedown via jQuery — this is
    // exactly what Drupal.file.triggerUploadButton does.
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
   * Falls back to clicking the native file input (which has
   * capture="environment" set by the PHP widget). On mobile this opens the
   * device camera chooser; on desktop it opens a file picker.
   */
  function captureWithNativeInput(wrapper) {
    return new Promise((resolve, reject) => {
      const input = wrapper.querySelector('input[type="file"]');
      if (!input) {
        reject(new Error('No file input found'));
        return;
      }
      const onChange = () => {
        input.removeEventListener('change', onChange);
        if (input.files && input.files[0]) {
          resolve(input.files[0]);
        } else {
          reject(new Error('No file selected'));
        }
      };
      input.addEventListener('change', onChange);
      input.click();
    });
  }

  /**
   * Detects mobile/touch devices (phones and tablets).
   *
   * On these devices the native file input with capture="environment" opens
   * the built-in camera app, which is both more reliable and a better UX
   * than the getUserMedia overlay. Crucially, iOS Safari does not allow
   * programmatically setting input.files via DataTransfer, so the
   * getUserMedia + push approach cannot work there — the native input path
   * lets the browser set the file itself and fire a genuine change event.
   */
  function isMobileDevice() {
    return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
      || (navigator.maxTouchPoints > 1 && /Mac/i.test(navigator.userAgent));
  }

  Drupal.behaviors.cameraUpload = {
    attach(context) {
      once('camera-upload-capture', '.camera-upload-capture-button', context).forEach((button) => {
        button.addEventListener('click', (e) => {
          e.preventDefault();
          const wrapper = button.closest('.js-form-managed-file');
          if (!wrapper) {
            return;
          }

          // On mobile devices, go straight to the native file input (which
          // has capture="environment"). The browser opens the camera, sets
          // the file natively and fires a real change event, so core's
          // fileAutoUpload handler performs the AJAX upload without any
          // programmatic file assignment.
          if (isMobileDevice()) {
            const input = wrapper.querySelector('input[type="file"]');
            if (input) {
              input.click();
            }
            return;
          }

          // On desktop, use the in-browser camera (getUserMedia) and push
          // the snapshot into the managed_file input. Fall back to the
          // native input if getUserMedia is unavailable or denied.
          captureWithGetUserMedia()
            .then((file) => pushFileAndUpload(wrapper, file))
            .catch(() => captureWithNativeInput(wrapper))
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