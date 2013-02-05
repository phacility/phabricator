/**
 * @provides javelin-behavior-conpherence-widget-pane
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 */

JX.behavior('conpherence-widget-pane', function(config) {

  JX.Stratcom.listen(
    'click',
    'conpherence-change-widget',
    function(e) {
      e.kill();
      var data = e.getNodeData('conpherence-change-widget');
      for (var widget in config.widgetRegistery) {
        if (widget == data.widget) {
          JX.$(widget).style.display = 'block';
          JX.DOM.alterClass(e.getTarget(), data.toggleClass, true);
        } else {
          JX.$(widget).style.display = 'none';
          var cur_toggle = JX.$(widget + '-toggle');
          JX.DOM.alterClass(
            cur_toggle,
            JX.Stratcom.getData(cur_toggle).toggleClass,
            false
          );
        }
      }
    }
  );

});
