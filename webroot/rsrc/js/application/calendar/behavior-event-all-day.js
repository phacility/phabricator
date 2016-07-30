/**
 * @provides javelin-behavior-event-all-day
 */

JX.behavior('event-all-day', function(config) {
  var all_day = JX.$(config.allDayID);

  JX.DOM.listen(all_day, 'change', null, function() {
    var is_all_day = !!parseInt(all_day.value, 10);

    for (var ii = 0; ii < config.controlIDs.length; ii++) {
      var control = JX.$(config.controlIDs[ii]);
      JX.DOM.alterClass(control, 'no-time', is_all_day);
    }
  });

});
