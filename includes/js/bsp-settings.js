jQuery(function() {
  var $ = jQuery;
  var bspCheckboxes = $('#bsp-settings-form').find(':checkbox');
  $('#bsp-invert-checkboxes').click(function() {
    if ($(this).prop('checked')) {
      bspCheckboxes.attr('checked', 'checked');
    }
    else {
      bspCheckboxes.removeAttr('checked');
    }
  });
});