/**
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-util
 *           phabricator-notification
 * @provides javelin-behavior-conpherence-widget-pane
 */

JX.behavior('conpherence-widget-pane', function(config) {

  JX.Stratcom.listen(
    'click',
    'conpherence-change-widget',
    function(e) {
      e.kill();
      var data = e.getNodeData('conpherence-change-widget');
      // abort if this widget isn't exactly involved in this toggle business
      if (!config.widgetRegistery[data.widget]) {
        return;
      }
      for (var widget in config.widgetRegistery) {
        if (!config.widgetRegistery[widget]) {
          continue;
        } else if (widget == data.widget) {
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

  var settingsRoot = JX.$(config.settings_widget);

  var onsubmitSettings = function (e) {
    e.kill();
    var form = JX.DOM.find(settingsRoot, 'form');
    var button = JX.DOM.find(form, 'button');
    JX.Workflow.newFromForm(form)
    .setHandler(JX.bind(this, function (r) {
      new JX.Notification()
      .setDuration(6000)
      .setContent(r)
      .show();
      button.disabled = '';
      JX.DOM.alterClass(button, 'disabled', false);
    }))
    .start();
  };

  JX.DOM.listen(
    settingsRoot,
    ['click'],
    'notifications-update',
    onsubmitSettings
  );

});
