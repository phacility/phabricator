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

  /**
   * There can be race conditions around loading the messages or the widgets
   * first. Keep track of what widgets we've loaded with this variable.
   */
  var _loadedWidgetsID = null;

  /**
   * At any given time there can be only one selected widget. Keep track of
   * which one it is by the user-facing name for ease of use with
   * PhabricatorDropdownMenuItems.
   */
  var _selectedWidgetName = null;

  /**
   * This is potentially built each time the user switches conpherence threads
   * or when the result JX.Device.getDevice() changes from desktop to some
   * other value.
   */
  var buildDeviceWidgetSelector = function (data) {
    var device_header = _getDeviceWidgetHeader();
    if (!device_header) {
      return;
    }
    JX.DOM.show(device_header);
    var device_menu = new JX.PhabricatorDropdownMenu(device_header);
    data.deviceMenu = true;
    _buildWidgetSelector(device_menu, data);
  };

  /**
   * This is potentially built each time the user switches conpherence threads
   * or when the result JX.Device.getDevice() changes from mobile or tablet to
   * desktop.
   */
  var buildDesktopWidgetSelector = function (data) {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var widget_pane = JX.DOM.find(root, 'div', 'conpherence-widget-pane');
    var widget_header = JX.DOM.find(widget_pane, 'a', 'widgets-selector');
    var menu = new JX.PhabricatorDropdownMenu(widget_header);
    menu.toggleAlignDropdownRight(false);
    data.deviceMenu = false;
    _buildWidgetSelector(menu, data);
  };

  /**
   * Workhorse that actually builds the widget selector. Note some fancy bits
   * where we listen for the "open" event and enable / disable widgets as
   * appropos.
   */
  var _buildWidgetSelector = function (menu, data) {
    _loadedWidgetsID = data.threadID;
    var widgets = config.widgetRegistry;
    for (var widget in widgets) {
      var widget_data = widgets[widget];
      if (widget_data.deviceOnly && data.deviceMenu === false) {
        continue;
      }
      menu.addItem(new JX.PhabricatorMenuItem(
        widget_data.name,
        JX.bind(null, toggleWidget, { widget : widget }),
        '#'
      ).setDisabled(widget == data.widget));
    }

    menu.listen(
     'open',
     JX.bind(menu, function () {
       for (var ii = 0; ii < this._items.length; ii++) {
         var item = this._items[ii];
         var name = item.getName();
         if (name == _selectedWidgetName) {
           item.setDisabled(true);
         } else {
           item.setDisabled(false);
         }
       }
     }));
  };

  /**
   * Since this is not always on the page, avoid having a repeat
   * try / catch block and consolidate into this helper function.
   */
  var _getDeviceWidgetHeader = function () {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var device_header = null;
    try {
      device_header = JX.DOM.find(
        root,
        'a',
        'device-widgets-selector');
    } catch (ex) {
      // is okay - no deviceWidgetHeader yet... but bail time
    }
    return device_header;
  };

  /**
   * Responder to the 'conpherence-did-redraw-thread' event, this bad boy
   * hides or shows the device widget selector as appropros.
   */
  var _didRedrawThread = function (data) {
    if (_loadedWidgetsID === null || _loadedWidgetsID != data.threadID) {
      return;
    }
    var device = JX.Device.getDevice();
    var device_selector = _getDeviceWidgetHeader();
    if (device == 'desktop') {
      JX.DOM.hide(device_selector);
    } else {
      JX.DOM.show(device_selector);
    }
    if (data.buildDeviceWidgetSelector) {
      buildDeviceWidgetSelector(data);
    }
    toggleWidget(data);
  };
  JX.Stratcom.listen(
    'conpherence-did-redraw-thread',
    null,
    function (e) {
      _didRedrawThread(e.getData());
    }
  );

  /**
   * Toggling a widget involves showing / hiding the appropriate widget
   * bodies as well as updating the selectors to have the label on the
   * newly selected widget.
   */
  var toggleWidget = function (data) {
    var widgets = config.widgetRegistry;
    var widget_data = widgets[data.widget];
    var device = JX.Device.getDevice();
    var is_desktop = device == 'desktop';

    if (widget_data.deviceOnly && is_desktop) {
      return;
    }
    _selectedWidgetName = widget_data.name;

    var device_header = _getDeviceWidgetHeader();
    if (device_header) {
      // this is fragile but adding a sigil to this element is awkward
      var device_header_spans = JX.DOM.scry(device_header, 'span');
      var device_header_span = device_header_spans[1];
      JX.DOM.setContent(
        device_header_span,
        widget_data.name);
    }

    // don't update the non-device selector with device only widget stuff
    if (!widget_data.deviceOnly) {
      var root = JX.DOM.find(document, 'div', 'conpherence-layout');
      var widget_pane = JX.DOM.find(root, 'div', 'conpherence-widget-pane');
      var widget_header = JX.DOM.find(widget_pane, 'a', 'widgets-selector');
      JX.DOM.setContent(
        widget_header,
        widget_data.name);
      JX.DOM.appendContent(
        widget_header,
        JX.$N('span', { className : 'caret' }));
    }

    for (var widget in config.widgetRegistry) {
      widget_data = widgets[widget];
      if (widget_data.deviceOnly && is_desktop) {
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
    'conpherence-update-widgets',
    null,
    function (e) {
      var data = e.getData();
      if (data.buildSelectors) {
        buildDesktopWidgetSelector(data);
        buildDeviceWidgetSelector(data);
      }
      if (data.toggleWidget) {
        toggleWidget(data);
      }
    });

  /* people widget */
  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'add-person',
    function (e) {
      e.kill();
      var root = e.getNode('conpherence-layout');
      var form = e.getNode('tag:form');
      var data = e.getNodeData('add-person');
      var people_root = e.getNode('widgets-people');
      var messages = null;
      try {
        messages = JX.DOM.find(root, 'div', 'conpherence-messages');
      } catch (ex) {
      }
      var latest_transaction_data = JX.Stratcom.getData(
        JX.DOM.find(
          root,
          'input',
          'latest-transaction-id'
      ));
      data.latest_transaction_id = latest_transaction_data.id;
      JX.Workflow.newFromForm(form, data)
      .setHandler(JX.bind(this, function (r) {
        if (messages) {
          JX.DOM.appendContent(messages, JX.$H(r.transactions));
          messages.scrollTop = messages.scrollHeight;
        }

        // update the people widget
        JX.DOM.setContent(
          people_root,
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
      var people_root = e.getNode('widgets-people');
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
