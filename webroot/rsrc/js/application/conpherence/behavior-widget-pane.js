/**
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-util
 *           phabricator-notification
 *           javelin-behavior-device
 *           phabricator-dropdown-menu
 *           phabricator-menu-item
 * @provides javelin-behavior-conpherence-widget-pane
 */

JX.behavior('conpherence-widget-pane', function(config) {

  var build_widget_selector = function (data) {
    var widgets = config.widgetRegistry;
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var widgetPane = JX.DOM.find(root, 'div', 'conpherence-widget-pane');
    var widgetHeader = JX.DOM.find(widgetPane, 'a', 'widgets-selector');
    var mobileWidgetHeader = null;
    try {
      mobileWidgetHeader = JX.DOM.find(
        root,
        'a',
        'device-widgets-selector');
    } catch (ex) {
      // is okay - no mobileWidgetHeader yet...
    }
    var widgetData = widgets[data.widget];
    JX.DOM.setContent(
      widgetHeader,
      widgetData.name);
    JX.DOM.appendContent(
      widgetHeader,
      JX.$N('span', { className : 'caret' }));
    if (mobileWidgetHeader) {
      // this is fragile but adding a sigil to this element is awkward
      var mobileWidgetHeaderSpans = JX.DOM.scry(mobileWidgetHeader, 'span');
      var mobileWidgetHeaderSpan = mobileWidgetHeaderSpans[1];
      JX.DOM.setContent(
        mobileWidgetHeaderSpan,
        widgetData.name);
    }

    var menu = new JX.PhabricatorDropdownMenu(widgetHeader);
    menu.toggleAlignDropdownRight(false);
    var deviceMenu = null;
    if (mobileWidgetHeader) {
      deviceMenu = new JX.PhabricatorDropdownMenu(mobileWidgetHeader);
    }

    for (var widget in widgets) {
      widgetData = widgets[widget];
      if (mobileWidgetHeader) {
        deviceMenu.addItem(new JX.PhabricatorMenuItem(
          widgetData.name,
          JX.bind(null, build_widget_selector, { widget : widget }),
          '#'
          ).setDisabled(widget == data.widget));
      }
      if (widgetData.deviceOnly) {
        continue;
      }
      menu.addItem(new JX.PhabricatorMenuItem(
        widgetData.name,
        JX.bind(null, build_widget_selector, { widget : widget }),
        '#'
      ).setDisabled(widget == data.widget));
    }
    if (data.no_toggle) {
      return;
    }
    toggle_widget(data);
  };

  var toggle_widget = function (data) {
    var widgets = config.widgetRegistry;
    var widgetData = widgets[data.widget];
    var device = JX.Device.getDevice();
    var is_desktop = device == 'desktop';

    if (widgetData.deviceOnly && is_desktop) {
      return;
    }

    for (var widget in config.widgetRegistry) {
      widgetData = widgets[widget];
      if (widgetData.deviceOnly && is_desktop) {
        // some one off code for conpherence messages which are device-only
        // as a widget, but shown always on the desktop
        if (widget == 'conpherence-message-pane') {
          JX.$(widget).style.display = 'block';
          JX.Stratcom.invoke('conpherence-redraw-thread', null, {});
        }
        continue;
      }
      if (widget == data.widget) {
        JX.$(widget).style.display = 'block';
        // some one off code for conpherence messages - fancier refresh tech
        if (widget == 'conpherence-message-pane') {
          JX.Stratcom.invoke('conpherence-redraw-thread', null, {});
          JX.Stratcom.invoke('conpherence-update-page-data', null, {});
        }
      } else {
        JX.$(widget).style.display = 'none';
      }
    }
  };

  JX.Stratcom.listen(
    'conpherence-toggle-widget',
    null,
    function (e) {
      build_widget_selector(e.getData());
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
