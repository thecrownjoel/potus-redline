/**
 * Red Line — Admin JavaScript.
 */
(function($) {
  'use strict';

  $(document).ready(function() {

    // Poll options — add/remove
    var maxOptions = 6;
    var optionCount = $('#pollOptions .rl-option-row').length;

    $('#addOption').on('click', function() {
      if (optionCount >= maxOptions) {
        alert('Maximum 6 options allowed.');
        return;
      }
      optionCount++;
      var row = '<div class="rl-option-row">' +
        '<input type="text" name="options[]" class="regular-text" placeholder="Option ' + optionCount + '">' +
        '<button type="button" class="button rl-remove-option">×</button>' +
        '</div>';
      $('#pollOptions').append(row);
    });

    $(document).on('click', '.rl-remove-option', function() {
      if ($('#pollOptions .rl-option-row').length <= 2) {
        alert('Minimum 2 options required.');
        return;
      }
      $(this).closest('.rl-option-row').remove();
      optionCount--;
    });

    // Regenerate API key
    $('#regenerateKey').on('click', function() {
      if (!confirm('Generate a new API key? The old key will stop working immediately.')) return;
      var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
      var key = '';
      for (var i = 0; i < 32; i++) {
        key += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      $('#api_key').val(key);
    });

    // Schedule field toggle
    $('select[name="status"]').on('change', function() {
      var scheduleRow = $(this).closest('table').find('[name="scheduled_at"]').closest('tr');
      if ($(this).val() === 'scheduled') {
        scheduleRow.show();
      } else {
        scheduleRow.hide();
      }
    }).trigger('change');

    // Dismiss notices
    $(document).on('click', '.notice-dismiss', function() {
      $(this).closest('.notice').fadeOut();
    });

  });

})(jQuery);
