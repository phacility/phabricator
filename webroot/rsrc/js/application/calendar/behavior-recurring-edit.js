/**
 * @provides javelin-behavior-recurring-edit
 */


JX.behavior('recurring-edit', function(config) {
  var checkbox = JX.$(config.isRecurring);
  var frequency = JX.$(config.frequency);
  var end_date = JX.$(config.recurrenceEndDate);

  var end_date_checkbox = JX.DOM.find(end_date, 'input', 'calendar-enable');

  JX.DOM.listen(checkbox, 'change', null, function() {
    if (checkbox.checked) {
      enableRecurring();
    } else {
      disableRecurring();
    }
  });

  JX.DOM.listen(end_date, 'change', null, function() {
    if (end_date_checkbox.checked) {
      enableRecurring();
    }
  });

  function enableRecurring() {
    checkbox.checked = true;
    frequency.disabled = false;
    end_date.disabled = false;
  }

  function disableRecurring() {
    checkbox.checked = false;
    frequency.disabled = true;
    end_date.disabled = true;
    end_date_checkbox.checked = false;

    JX.DOM.alterClass(end_date, 'datepicker-disabled', true);
  }
});
