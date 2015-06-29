/**
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-util
 *           phabricator-notification
 *           javelin-behavior-device
 *           phuix-dropdown-menu
 *           phuix-action-list-view
 *           phuix-action-view
 *           conpherence-thread-manager
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
    var device_menu = new JX.PHUIXDropdownMenu(device_header);
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

    var menu = new JX.PHUIXDropdownMenu(widget_header);
    menu
      .setAlign('left')
      .setOffsetY(4);

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

    var list = new JX.PHUIXActionListView();
    var map = {};

    var widgets = config.widgetRegistry;
    for (var widget in widgets) {
      var widget_data = widgets[widget];
      if (widget_data.deviceOnly && data.deviceMenu === false) {
        continue;
      }

      var handler;
      var href;
      if (widget == 'widgets-edit') {
        var threadManager = JX.ConpherenceThreadManager.getInstance();
        handler = function(e) {
          e.prevent();
          menu.close();
          threadManager.runUpdateWorkflowFromLink(
            e.getTarget(),
            {
              action : 'metadata',
              force_ajax : true,
              stage : 'submit'
            });
        };
        href = threadManager._getUpdateURI();
      } else {
        handler = JX.bind(null, function(widget, e) {
          toggleWidget({widget: widget});
          e.prevent();
          menu.close();
        }, widget);
      }
      var item = new JX.PHUIXActionView()
        .setIcon(widget_data.icon || 'none')
        .setName(widget_data.name)
        .setHref(href)
        .setHandler(handler);
      map[widget_data.name] = item;
      list.addItem(item);
    }

    menu
      .setWidth(200)
      .setContent(list.getNode());

    menu.listen('open', function() {
      for (var k in map) {
        map[k].setDisabled((k == _selectedWidgetName));
      }
    });
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
      var adder = JX.DOM.find(widget_pane, 'a', 'conpherence-widget-adder');
      var threadManager = JX.ConpherenceThreadManager.getInstance();
      var disabled = !threadManager.getCanEditLoadedThread();
      JX.DOM.setContent(
        widget_header,
        widget_data.name);
      JX.DOM.appendContent(
        widget_header,
        JX.$N('span', { className : 'caret' }));
      if (widget_data.hasCreate) {
        JX.DOM.show(adder);
        JX.DOM.alterClass(
          adder,
          'disabled',
          disabled);
      } else {
        JX.DOM.hide(adder);
      }
    }

    for (var widget in config.widgetRegistry) {
      widget_data = widgets[widget];
      if (widget_data.deviceOnly && is_desktop) {
        // some one off code for conpherence messages which are device-only
        // as a widget, but shown always on the desktop
        if (widget == 'conpherence-message-pane') {
          JX.$(widget).style.display = 'block';
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

  /**
   * Generified adding new stuff to widgets technology!
   */
  JX.Stratcom.listen(
    ['click'],
    'conpherence-widget-adder',
    function (e) {
      e.kill();

      var widgets = config.widgetRegistry;
      // the widget key might be in node data, but otherwise use the
      // selected widget
      var event_data = e.getNodeData('conpherence-widget-adder');
      var widget_key = _selectedWidgetName;
      if (event_data.widget) {
        widget_key = widgets[event_data.widget].name;
      }

      var widget_to_update = null;
      var create_data = null;
      for (var widget in widgets) {
        if (widgets[widget].name == widget_key) {
          create_data = widgets[widget].createData;
          widget_to_update = widget;
          break;
        }
      }
      // this should be impossible, but hey
      if (!widget_to_update) {
        return;
      }
      var href = config.widgetBaseUpdateURI + _loadedWidgetsID + '/';
      if (create_data.customHref) {
        href = create_data.customHref;
      }

      var threadManager = JX.ConpherenceThreadManager.getInstance();
      var latest_transaction_id = threadManager.getLatestTransactionID();
      var data = {
        latest_transaction_id : latest_transaction_id,
        action : create_data.action
      };

      var workflow = new JX.Workflow(href, data)
        .setHandler(function (r) {
          var threadManager = JX.ConpherenceThreadManager.getInstance();
          threadManager.setLatestTransactionID(r.latest_transaction_id);
          var root = JX.DOM.find(document, 'div', 'conpherence-layout');
          if (create_data.refreshFromResponse) {
            var messages = null;
            try {
              messages = JX.DOM.find(root, 'div', 'conpherence-messages');
            } catch (ex) {
            }
            if (messages) {
              JX.DOM.appendContent(messages, JX.$H(r.transactions));
              JX.Stratcom.invoke('conpherence-redraw-thread', null, {});
            }

            if (r.people_widget) {
              try {
                var people_root = JX.DOM.find(root, 'div', 'widgets-people');
                // update the people widget
                JX.DOM.setContent(
                  people_root,
                  JX.$H(r.people_widget));
              } catch (ex) {
              }
            }

          // otherwise let's redraw the widget somewhat lazily
          } else {
            JX.Stratcom.invoke(
              'conpherence-reload-widget',
              null,
              {
                threadID : _loadedWidgetsID,
                widget : widget_to_update
              });
          }
        });

      threadManager.syncWorkflow(workflow, 'submit');
    }
  );

  JX.Stratcom.listen(
    ['touchstart', 'mousedown'],
    'remove-person',
    function (e) {
      var href = config.widgetBaseUpdateURI + _loadedWidgetsID + '/';
      var data = e.getNodeData('remove-person');

      // While the user is removing themselves, disable the notification
      // update behavior. If we don't do this, the user can get an error
      // when they remove themselves about permissions as the notification
      // code tries to load what jist happened.
      var threadManager = JX.ConpherenceThreadManager.getInstance();
      var loadedPhid = threadManager.getLoadedThreadPHID();
      threadManager.setLoadedThreadPHID(null);

      new JX.Workflow(href, data)
        .setCloseHandler(function() {
          threadManager.setLoadedThreadPHID(loadedPhid);
        })
        // we re-direct to conpherence home so the thread manager will
        // fix itself there
        .setHandler(function(r) {
          JX.$U(r.href).go();
        })
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
