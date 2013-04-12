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
  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'add-person',
    function (e) {
      e.kill();
      var root = e.getNode('conpherence-layout');
      var form = e.getNode('tag:form');
      var data = e.getNodeData('add-person');
      var peopleRoot = e.getNode('widgets-people');
      var messages = null;
      try {
        messages = JX.DOM.find(root, 'div', 'conpherence-messages');
      } catch (ex) {
      }
      var latestTransactionData = JX.Stratcom.getData(
        JX.DOM.find(
          root,
          'input',
          'latest-transaction-id'
      ));
      data.latest_transaction_id = latestTransactionData.id;
      JX.Workflow.newFromForm(form, data)
      .setHandler(JX.bind(this, function (r) {
        if (messages) {
          JX.DOM.appendContent(messages, JX.$H(r.transactions));
          messages.scrollTop = messages.scrollHeight;
        }

        // update the people widget
        JX.DOM.setContent(
          peopleRoot,
          JX.$H(r.people_widget)
        );
      }))
      .start();
    }
  );

  JX.Stratcom.listen(
    ['click'],
    'remove-person',
    function (e) {
      var peopleRoot = e.getNode('widgets-people');
      var form = JX.DOM.find(peopleRoot, 'form');
      var data = e.getNodeData('remove-person');
      // we end up re-directing to conpherence home
      JX.Workflow.newFromForm(form, data)
      .start();
    }
  );

  /* settings widget */
  var onsubmitSettings = function (e) {
    e.kill();
    var form = e.getNode('tag:form');
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

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'notifications-update',
    onsubmitSettings
  );

});
