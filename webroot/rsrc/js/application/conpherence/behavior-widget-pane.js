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

  /* people widget */
  var peopleRoot = JX.$(config.people_widget);
  var peopleUpdateHandler = function (r) {
    // update the transactions
    var messages = JX.$(config.messages);
    JX.DOM.appendContent(messages, JX.$H(r.transactions));
    messages.scrollTop = messages.scrollHeight;

    // update the menu entry as well
    JX.DOM.replace(
      JX.$(r.conpherence_phid + '-nav-item'),
      JX.$H(r.nav_item)
    );

    // update the header
    JX.DOM.setContent(
      JX.$(config.header),
      JX.$H(r.header)
    );

    // update the people widget
    JX.DOM.setContent(
      JX.$(config.people_widget),
      JX.$H(r.people_widget)
    );
  };

  JX.DOM.listen(
    peopleRoot,
    ['click'],
    'add-person',
    function (e) {
      e.kill();
      var form = JX.DOM.find(peopleRoot, 'form');
      var data = e.getNodeData('add-person');
      JX.Workflow.newFromForm(form, data)
      .setHandler(peopleUpdateHandler)
      .start();
    }
  );

  JX.DOM.listen(
    peopleRoot,
    ['click'],
    'remove-person',
    function (e) {
      var form = JX.DOM.find(peopleRoot, 'form');
      var data = e.getNodeData('remove-person');
      JX.Workflow.newFromForm(form, data)
      .setHandler(peopleUpdateHandler)
      .start();
    }
  );

  /* settings widget */
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
