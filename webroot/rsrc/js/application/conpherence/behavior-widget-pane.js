/**
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-util
 *           phabricator-notification
 *           javelin-behavior-device
 * @provides javelin-behavior-conpherence-widget-pane
 */

JX.behavior('conpherence-widget-pane', function(config) {

  var toggle_widget = function (data) {
    var device = JX.Device.getDevice();
    var is_desktop = device == 'desktop';
    if (config.widgetRegistery[data.widget] == config.devicesOnly &&
        is_desktop) {
      return;
    }

    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var widgetPane = JX.DOM.find(root, 'div', 'conpherence-widget-pane');
    for (var widget in config.widgetRegistery) {
      // device-only widgets are *always shown* on the desktop
      if (config.widgetRegistery[widget] == config.devicesOnly) {
        if (is_desktop) {
          JX.$(widget).style.display = 'block';
          if (config.widgetExtraNodes[widget]) {
            for (var i in config.widgetExtraNodes[widget]) {
              var tag_data = config.widgetExtraNodes[widget][i];
              var node = JX.DOM.find(root, tag_data.tagname, tag_data.sigil);
              node.style.display = tag_data.desktopstyle;
            }
          }
          continue;
        }
      }

      var cur_toggle = JX.$(widget + '-toggle');
      var toggle_class = config.widgetToggleMap[widget];
      if (widget == data.widget) {
        JX.DOM.alterClass(cur_toggle, toggle_class, true);
        JX.$(widget).style.display = 'block';
        if (config.widgetRegistery[widget] == config.devicesOnly) {
          widgetPane.style.height = '42px';
        } else {
          widgetPane.style.height = '100%';
        }
        if (config.widgetExtraNodes[widget]) {
          for (var i in config.widgetExtraNodes[widget]) {
            var tag_data = config.widgetExtraNodes[widget][i];
            var node = JX.DOM.find(root, tag_data.tagname, tag_data.sigil);
            node.style.display = tag_data.showstyle;
          }
        }
        // some one off code for conpherence messages
        if (widget == 'conpherence-message-pane') {
          JX.Stratcom.invoke('conpherence-redraw-thread', null, {});
          JX.Stratcom.invoke('conpherence-update-page-data', null, {});
        }
        // some one off code for conpherence list
        if (widget == 'conpherence-menu-pane') {
          JX.Stratcom.invoke(
            'conpherence-update-page-data',
            null,
            { use_base_uri : true }
          );
        }
      } else {
        JX.DOM.alterClass(
          cur_toggle,
          toggle_class,
          false
        );
        JX.$(widget).style.display = 'none';
        if (config.widgetExtraNodes[widget]) {
          for (var i in config.widgetExtraNodes[widget]) {
            var tag_data = config.widgetExtraNodes[widget][i];
            var node = JX.DOM.find(root, tag_data.tagname, tag_data.sigil);
            node.style.display = tag_data.hidestyle;
          }
        }
      }
    }
  };

  JX.Stratcom.listen(
    ['touchstart', 'mousedown'],
    'conpherence-change-widget',
    function(e) {
      e.kill();
      var data = e.getNodeData('conpherence-change-widget');
      toggle_widget(data);
    }
  );

  JX.Stratcom.listen(
    'conpherence-toggle-widget',
    null,
    function (e) {
      toggle_widget(e.getData());
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
    ['touchstart', 'mousedown'],
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
