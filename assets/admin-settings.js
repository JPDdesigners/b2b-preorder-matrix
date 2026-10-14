(function () {
  'use strict';

  function relativeLuminance(hex) {
    var clean = String(hex || '').replace('#', '');
    if (!/^[0-9a-fA-F]{6}$/.test(clean)) {
      return 0;
    }
    var channels = [
      parseInt(clean.slice(0, 2), 16) / 255,
      parseInt(clean.slice(2, 4), 16) / 255,
      parseInt(clean.slice(4, 6), 16) / 255,
    ].map(function (c) {
      return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
  }

  function updateCustomColorUI(hex, syncPicker, syncInput) {
    if (!/^#([A-Fa-f0-9]{6})$/.test(hex)) {
      return;
    }
    var normalized = hex.toLowerCase();
    var picker = document.getElementById('b2b_custom_color_picker');
    var input = document.getElementById('b2b_custom_color_hex');
    var swatch = document.getElementById('b2b-custom-swatch');
    var customRadio = document.querySelector(
      'input[name="b2b_matrix_appearance[palette]"][value="custom"]'
    );
    var contrastBadge = document.getElementById('b2b-contrast-badge');

    if (syncPicker && picker) {
      picker.value = normalized;
    }
    if (syncInput && input) {
      input.value = normalized;
    }
    if (swatch) {
      swatch.style.backgroundColor = normalized;
    }
    if (customRadio) {
      customRadio.checked = true;
    }
    if (contrastBadge) {
      var lum = relativeLuminance(normalized);
      var darkText = 1.05 / (lum + 0.05) < 4.5;
      contrastBadge.textContent = darkText
        ? 'Ανοιχτό χρώμα · αυτόματο σκούρο κείμενο'
        : 'Σκούρο χρώμα · λευκό κείμενο';
      contrastBadge.style.backgroundColor = normalized;
      contrastBadge.style.color = darkText ? '#0f172a' : '#ffffff';
    }
  }

  function initAdminSettings() {
    var picker = document.getElementById('b2b_custom_color_picker');
    var input = document.getElementById('b2b_custom_color_hex');

    if (picker) {
      picker.addEventListener('input', function () {
        updateCustomColorUI(picker.value, false, true);
      });
      picker.addEventListener('change', function () {
        updateCustomColorUI(picker.value, false, true);
      });
    }

    if (input) {
      input.addEventListener('input', function () {
        if (/^#([A-Fa-f0-9]{6})$/.test(input.value)) {
          updateCustomColorUI(input.value, true, false);
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminSettings);
  } else {
    initAdminSettings();
  }
})();
