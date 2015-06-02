/**
 * @provides javelin-behavior-recurring-edit
 */


JX.behavior('recurring-edit', function(config) {
  var checkbox = JX.$(config.isRecurring);

  JX.DOM.listen(checkbox, 'change', null, function() {
    var frequency = JX.$(config.frequency);
    var end_date = JX.$(config.recurrenceEndDate);

    frequency.disabled = checkbox.checked ? false : true;
    end_date.disabled = checkbox.checked ? false : true;

    if (end_date.disabled) {
      JX.DOM.alterClass(end_date, 'datepicker-disabled', !checkbox.checked);
    }
  });

});
