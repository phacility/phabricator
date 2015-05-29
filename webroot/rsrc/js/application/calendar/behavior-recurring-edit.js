/**
 * @provides javelin-behavior-recurring-edit
 */


JX.behavior('recurring-edit', function(config) {
  var checkbox = JX.$(config.isRecurring);
  JX.DOM.listen(checkbox, 'change', null, function() {
    var frequency = JX.$(config.frequency);

    frequency.disabled = checkbox.checked ? false : true;
  });

});
