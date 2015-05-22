/**
 * @provides javelin-behavior-event-all-day
 */


JX.behavior('event-all-day', function(config) {
  var checkbox = JX.$(config.allDayID);
  JX.DOM.listen(checkbox, 'change', null, function() {
    var start = JX.$(config.startDateID);
    var end = JX.$(config.endDateID);

    JX.DOM.alterClass(start, 'no-time', checkbox.checked);
    JX.DOM.alterClass(end, 'no-time', checkbox.checked);
  });

});
