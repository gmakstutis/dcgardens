/**
 * Camera capture behavior with MutationObserver for robust readiness.
 * Works on HTTPS and http://localhost. No jQuery, no once().
 * Includes proper video readiness and dynamic canvas sizing to avoid blank/black PNGs.
 */
(function (Drupal) {
  Drupal.behaviors.cameraCapture = {
    attach: function (context) {
      // Find our root container; behavior may attach on various contexts.
      const root = document.getElementById('camera-capture-root');
      if (!root) {
        console.debug('[cameraCapture] root not found in this context, skipping');
        return;
      }

      // If already initialized, skip.
      if (root.dataset.cameraInit === '1') {
        console.debug('[cameraCapture] already initialized, skipping');
        return;
      }

      // Helper: check if all required elements exist under root.
      const hasAll = () => {
        return (
          root.querySelector('#camera-preview') &&
          root.querySelector('#camera-canvas') &&
          root.querySelector('#capture-btn') &&
          root.querySelector('#record-btn') &&
          root.querySelector('#stop-btn') &&
          root.querySelector('#video-preview') &&
          root.querySelector('#captured-image') &&
          root.querySelector('#captured-video')
        );
      };

      // If not ready yet, observe mutations until ready.
      if (!hasAll()) {
        console.debug('[cameraCapture] waiting for elements…');
        const observer = new MutationObserver((_mutations, obs) => {
          if (hasAll()) {
            obs.disconnect();
            init(root);
          }
        });
        observer.observe(root, { childList: true, subtree: true });

        // Timeout fallback (in case there are no mutations).
        setTimeout(() => {
          if (hasAll()) {
            init(root);
          }
        }, 500);
      } else {
        init(root);
      }

      function init(rootEl) {
        // Guard double init.
        if (rootEl.dataset.cameraInit === '1') return;

        const videoEl     = rootEl.querySelector('#camera-preview');
        const canvasEl    = rootEl.querySelector('#camera-canvas');
        const captureBtn  = rootEl.querySelector('#capture-btn');
        const recordBtn   = rootEl.querySelector('#record-btn');
        const stopBtn     = rootEl.querySelector('#stop-btn');
        const previewEl   = rootEl.querySelector('#video-preview');
        const imageHidden = rootEl.querySelector('#captured-image');
        const videoHidden = rootEl.querySelector('#captured-video');

        if (!videoEl || !canvasEl || !captureBtn || !recordBtn || !stopBtn || !previewEl || !imageHidden || !videoHidden) {
          console.error('[cameraCapture] Required elements still not found at init');
          return;
        }

        console.debug('[cameraCapture] initializing handlers');

        let mediaRecorder = null;
        let chunks = [];
        let streamStarted = false;

        /**
         * Start the camera stream and ensure the video element has dimensions.
         */
        const startStream = async () => {
          if (streamStarted) return true;
          try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            videoEl.srcObject = stream;

            // Wait for metadata so videoWidth/videoHeight are available.
            await new Promise((resolve) => {
              if (videoEl.readyState >= 1 /* HAVE_METADATA */) {
                resolve();
              } else {
                videoEl.addEventListener('loadedmetadata', resolve, { once: true });
              }
            });

            // Attempt to play (user click counts as gesture).
            const playPromise = videoEl.play();
            if (playPromise && typeof playPromise.then === 'function') {
              await playPromise.catch(() => {
                /* Autoplay may be blocked; subsequent clicks will play. */
              });
            }

            // Ensure the video can render a frame.
            await new Promise((resolve) => {
              if (videoEl.readyState >= 2 /* HAVE_CURRENT_DATA */) {
                resolve();
              } else {
                videoEl.addEventListener('canplay', resolve, { once: true });
              }
            });

            // Initialize MediaRecorder after stream is definitely started.
            if (typeof MediaRecorder === 'undefined') {
              console.error('[cameraCapture] MediaRecorder API not available in this browser.');
              alert('MediaRecorder is not supported in this browser.');
              return false;
            }

            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = e => {
              if (e.data && e.data.size > 0) chunks.push(e.data);
            };
            mediaRecorder.onstop = () => {
              const blob = new Blob(chunks, { type: 'video/webm' });
              chunks = [];
              const videoURL = URL.createObjectURL(blob);
              previewEl.src = videoURL;

              const reader = new FileReader();
              reader.onloadend = () => {
                videoHidden.value = reader.result; // data:video/webm;base64,...
              };
              reader.readAsDataURL(blob);
            };

            streamStarted = true;
            console.debug('[cameraCapture] stream started with size', videoEl.videoWidth, 'x', videoEl.videoHeight);
            return true;
          } catch (err) {
            console.error('[cameraCapture] getUserMedia error:', err);
            alert('Unable to access camera/microphone. Use HTTPS or localhost and allow permissions.');
            return false;
          }
        };

        // Capture Photo handler (fixes black/blank PNGs).
        captureBtn.addEventListener('click', async () => {
          console.debug('[cameraCapture] Capture Photo clicked');

          const ok = await startStream();
          if (!ok) return;

          // Ensure video has non-zero dimensions.
          let vw = videoEl.videoWidth;
          let vh = videoEl.videoHeight;

          if (!vw || !vh) {
            // Retry briefly.
            await new Promise(r => setTimeout(r, 100));
            vw = videoEl.videoWidth;
            vh = videoEl.videoHeight;
            if (!vw || !vh) {
              console.warn('[cameraCapture] video dimensions not ready; skipping capture for now');
              alert('Camera preview not ready yet. Please try Capture Photo again.');
              return;
            }
          }

          // Match canvas size to the actual video frame (most reliable).
          canvasEl.width  = vw;
          canvasEl.height = vh;

          const ctx = canvasEl.getContext('2d');
          ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

          const imageData = canvasEl.toDataURL('image/png');
          if (!imageData || imageData.length < 50) {
            console.warn('[cameraCapture] produced empty imageData');
            alert('Capture failed. Please try again.');
            return;
          }

          imageHidden.value = imageData;
          console.debug('[cameraCapture] image hidden field populated', canvasEl.width, 'x', canvasEl.height);
        });

        // Record 10s handler.
        recordBtn.addEventListener('click', async () => {
          console.debug('[cameraCapture] Record 10s clicked');
          const ok = await startStream();
          if (!ok) return;
          if (!mediaRecorder) {
            console.warn('[cameraCapture] MediaRecorder not ready');
            return;
          }
          if (mediaRecorder.state !== 'inactive') {
            console.warn('[cameraCapture] Already recording');
            return;
          }
          chunks = [];
          mediaRecorder.start();
          console.debug('[cameraCapture] recording started');
          setTimeout(() => {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
              mediaRecorder.stop();
            }
          }, 10000);
        });

        // Stop recording handler.
        stopBtn.addEventListener('click', () => {
          console.debug('[cameraCapture] Stop clicked');
          if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
          }
        });

        // Mark initialized.
        rootEl.dataset.cameraInit = '1';
        console.debug('[cameraCapture] initialization complete');
      }
    }
  };
})(Drupal);
