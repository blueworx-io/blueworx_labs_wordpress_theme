/**
 * BlueWorx > Design.
 *
 * Two jobs. Keeps each colour picker and its hex box in step, so either can be
 * used; the hex box is the one the form sends, and it may be left empty where
 * that means "work it out". And opens WordPress's media library for the logo
 * pickers, writing the chosen image's id into the hidden field beside it.
 *
 * Without this script the hex boxes still work and the logos keep what they
 * have, so nothing here is needed to save the screen.
 */
(function () {
  'use strict';

  var HEX = /^#[0-9a-f]{6}$/i;

  document.querySelectorAll('input[type="color"][data-blueworx-color-for]').forEach(function (picker) {
    var box = document.getElementById(picker.getAttribute('data-blueworx-color-for'));

    if (!box) {
      return;
    }

    picker.addEventListener('input', function () {
      box.value = picker.value;
    });

    box.addEventListener('input', function () {
      var value = box.value.trim();

      if (/^[0-9a-f]{6}$/i.test(value)) {
        value = '#' + value;
      }

      if (HEX.test(value)) {
        picker.value = value.toLowerCase();
      }
    });
  });

  /**
   * Shows an image, or the empty placeholder, in a picker's preview.
   *
   * @param {string} id  The hidden field's id.
   * @param {string} url The image, or '' for none.
   */
  function showPreview(id, url) {
    var preview = document.getElementById(id + '_preview');

    if (!preview) {
      return;
    }

    if (url) {
      var img = document.createElement('img');
      img.className = 'bw-media__img';
      img.id = id + '_preview';
      img.alt = '';
      img.src = url;
      preview.replaceWith(img);
      return;
    }

    if ('IMG' === preview.tagName) {
      var empty = document.createElement('span');
      empty.className = 'bw-media__empty';
      empty.id = id + '_preview';
      preview.replaceWith(empty);
    }
  }

  document.querySelectorAll('[data-blueworx-media]').forEach(function (button) {
    var id = button.getAttribute('data-blueworx-media');
    var field = document.getElementById(id);
    var frame = null;

    if (!field || !window.wp || !wp.media) {
      return;
    }

    button.addEventListener('click', function (event) {
      event.preventDefault();

      if (!frame) {
        var labels = window.blueworxDesignScreen || {};

        frame = wp.media({
          title: labels.choose,
          button: { text: labels.use },
          library: { type: 'image' },
          multiple: false,
        });

        frame.on('select', function () {
          var image = frame.state().get('selection').first().toJSON();
          var sizes = image.sizes || {};

          field.value = image.id;
          showPreview(id, (sizes.medium || sizes.full || image).url);
        });
      }

      frame.open();
    });
  });

  document.querySelectorAll('[data-blueworx-clear]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      var id = button.getAttribute('data-blueworx-clear');
      var field = document.getElementById(id);

      event.preventDefault();

      if (field) {
        field.value = '';
        showPreview(id, '');
      }
    });
  });
})();
