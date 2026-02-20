/**
 * @file
 */

(($, once) => {
  Drupal.plupload = Drupal.plupload || {};
  // Add Plupload events for autoupload and autosubmit.
  Drupal.plupload.filesAddedCallback = (up) => {
    setTimeout(() => {
      up.start();
    }, 100);
  };
  Drupal.plupload.uploadCompleteCallback = (up) => {
    const $this = $(up.settings.container);
    // If there is submit_element trigger it.
    const { submitElement } = drupalSettings.plupload[$this.attr('id')];
    if (submitElement) {
      $(submitElement).click();
    }
    // Otherwise submit default form.
    else {
      const $form = $this.parents('form');
      $($form[0]).submit();
    }
  };
  /**
   * Attaches the Plupload behavior to each Plupload form element.
   */
  Drupal.behaviors.plupload = {
    attach(context, settings) {
      $(once('plupload-init', '.plupload-element'), context).each(function () { // eslint-disable-line func-names,prettier/prettier
        const $this = $(this);

        // Merge the default settings and the element settings to get a full
        // settings object to pass to the Plupload library for this element.
        const id = $this.attr('id');
        const defaultSettings = settings.plupload._default
          ? settings.plupload._default
          : {};
        const elementSettings =
          id && settings.plupload[id] ? settings.plupload[id] : {};
        const pluploadSettings = $.extend({}, defaultSettings, elementSettings);

        // Do additional requirements testing to prevent a less than ideal runtime
        // from being used. For example, the Plupload library treats Firefox 3.5
        // as supporting HTML 5, but this is incorrect, because Firefox 3.5
        // doesn't support the 'multiple' attribute for file input controls. So,
        // if settings.plupload._requirements.html5.mozilla = '1.9.2', then we
        // remove 'html5' from pluploadSettings.runtimes if $.browser.mozilla is
        // true and if $.browser.version is less than '1.9.2'.
        if (settings.plupload._requirements && pluploadSettings.runtimes) {
          const runtimes = pluploadSettings.runtimes.split(',');
          const filteredRuntimes = [];
          for (let i = 0; i < runtimes.length; i++) {
            let includeRuntime = true;
            if (settings.plupload._requirements[runtimes[i]]) {
              const requirements = settings.plupload._requirements[runtimes[i]];
              const browsers = Object.keys(requirements);
              for (let key = 0; key < browsers.length; key++) {
                const browser = browsers[key];
                if (
                  $.browser[browser] &&
                  Drupal.plupload.compareVersions(
                    $.browser.version,
                    requirements[browser],
                  ) < 0
                ) {
                  includeRuntime = false;
                }
              }
            }
            if (includeRuntime) {
              filteredRuntimes.push(runtimes[i]);
            }
          }
          pluploadSettings.runtimes = filteredRuntimes.join(',');
        }
        // Process Plupload events.
        if (elementSettings.init || false) {
          if (!pluploadSettings.init) {
            pluploadSettings.init = {};
          }
          const initMethods = Object.keys(elementSettings.init);
          for (let key = 0; key < initMethods.length; key++) {
            const callback = elementSettings.init[initMethods[key]].split('.');
            let fn = window;
            for (let j = 0; j < callback.length; j++) {
              if (typeof fn[callback[j]] !== 'undefined') {
                fn = fn[callback[j]];
              } else {
                // Drupal.announce might be introduced in 8.1
                // https://www.drupal.org/node/77245
                if (Drupal.announce) {
                  Drupal.announce(
                    `Plupload callback not defined: ${fn}.${callback[j]}`,
                  );
                }
                break;
              }
            }
            if (typeof fn === 'function') {
              pluploadSettings.init[initMethods[key]] = fn;
            }
          }
        }
        // Initialize Plupload for this element.
        $this.pluploadQueue(pluploadSettings);
      });

      // Remove drupal default upload behavior on plupload input.
      // @see Drupal.behaviors.fileAutoUpload().
      $(
        once.remove(
          'auto-file-upload',
          '.plupload-element .moxie-shim input[type="file"]',
          context,
        ),
      ).off('.autoFileUpload');
    },
  };

  /**
   * Attaches the Plupload behavior to each Plupload form element.
   */
  Drupal.behaviors.pluploadform = {
    attach(context, settings) {
      $(once('plupload-form', 'form'), context).each(function () {
        if ($(this).find('.plupload-element').length > 0) {
          const $form = $(this);
          const originalFormAttributes = {
            method: $form.attr('method'),
            enctype: $form.attr('enctype'),
            action: $form.attr('action'),
            target: $form.attr('target'),
          };

          $(this).submit(() => {
            let completedPluploaders = 0;
            const totalPluploaders = $(this).find('.plupload-element').length;
            let errors = '';

            $(this)
              .find('.plupload-element')
              .each(function (index) {
                const uploader = $(this).pluploadQueue();

                const id = $(this).attr('id');
                const defaultSettings = settings.plupload._default
                  ? settings.plupload._default
                  : {};
                const elementSettings =
                  id && settings.plupload[id] ? settings.plupload[id] : {};
                const pluploadSettings = $.extend(
                  {},
                  defaultSettings,
                  elementSettings,
                );

                // Only allow the submit to proceed if there are files and they've all
                // completed uploading.
                // TODO: Implement a setting for whether the field is required, rather
                // than assuming that all are.
                if (uploader.state === plupload.STARTED) {
                  errors += Drupal.t(
                    'Please wait while your files are being uploaded.',
                  );
                } else if (
                  uploader.files.length === 0 &&
                  !pluploadSettings.required
                ) {
                  completedPluploaders += 1;
                } else if (uploader.files.length === 0) {
                  errors += Drupal.t(
                    '@index: You must upload at least one file.\n',
                    { '@index': index + 1 },
                  );
                } else if (
                  uploader.files.length > 0 &&
                  uploader.total.uploaded === uploader.files.length
                ) {
                  completedPluploaders += 1;
                } else {
                  const stateChangedHandler = () => {
                    if (uploader.total.uploaded === uploader.files.length) {
                      uploader.unbind('StateChanged', stateChangedHandler);
                      completedPluploaders += 1;
                      if (completedPluploaders === totalPluploaders) {
                        // Plupload's html4 runtime has a bug where it changes the
                        // attributes of the form to handle the file upload, but then
                        // fails to change them back after the upload is finished.
                        const attributeKeys = Object.keys(
                          originalFormAttributes,
                        );
                        for (let key = 0; key < attributeKeys.length; key++) {
                          const attr = attributeKeys[key];
                          $form.attr(attr, originalFormAttributes[attr]);
                        }
                        // Click a specific element if one is specified.
                        if (settings.plupload[id].submit_element) {
                          $(settings.plupload[id].submit_element).click();
                        } else {
                          $form.submit();
                        }
                        return true;
                      }
                    }
                  };
                  uploader.bind('StateChanged', stateChangedHandler);
                  uploader.start();
                }
              });
            if (completedPluploaders === totalPluploaders) {
              // Plupload's html4 runtime has a bug where it changes the
              // attributes of the form to handle the file upload, but then
              // fails to change them back after the upload is finished.
              const attributeKeys = Object.keys(originalFormAttributes);
              for (let key = 0; key < attributeKeys.length; key++) {
                const attr = attributeKeys[key];
                $form.attr(attr, originalFormAttributes[attr]);
              }
              return true;
            }
            if (errors.length > 0) {
              alert(errors); // eslint-disable-line no-alert
            }

            return false;
          });
        }
      });
    },
  };

  /**
   * Helper function to compare version strings.
   *
   * Returns one of:
   *   - A negative integer if a < b.
   *   - A positive integer if a > b.
   *   - 0 if a == b.
   *
   * @param {string} a
   *   The first string to compare.
   * @param {string} b
   *   The second string to compare.
   * @return {number}
   *   The result of the comparison.
   */
  Drupal.plupload.compareVersions = (a, b) => {
    a = a.split('.');
    b = b.split('.');
    // Return the most significant difference, if there is one.
    for (let i = 0; i < Math.min(a.length, b.length); i++) {
      const compare = parseInt(a[i]) - parseInt(b[i]); // eslint-disable-line radix
      if (compare !== 0) {
        return compare;
      }
    }
    // If the common parts of the two version strings are equal, the greater
    // version number is the one with the most sections.
    return a.length - b.length;
  };
})(jQuery, once);
