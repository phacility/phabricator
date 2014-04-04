/**
 * @provides javelin-behavior-conpherence-menu
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-behavior-device
 *           javelin-history
 *           javelin-vector
 *           phabricator-shaped-request
 */

JX.behavior('conpherence-menu', function(config) {

  /**
   * State for displayed thread.
   */
  var _thread = {
    selected: null,
    visible: null,
    node: null
  };

  /**
   * Current role of this behavior. The two possible roles are to show a 'list'
   * of threads or a specific 'thread'. On devices, this behavior stays in the
   * 'list' role indefinitely, treating clicks normally and the next page
   * loads the behavior with role = 'thread'. On desktop, this behavior
   * auto-loads a thread as part of the 'list' role. As the thread loads the
   * role is changed to 'thread'.
   */
  var _currentRole = null;

  /**
   * When _oldDevice is null the code is executing for the first time.
   */
  var _oldDevice = null;

  /**
   * Initializes this behavior based on all the configuraton jonx and the
   * result of JX.Device.getDevice();
   */
  function init() {
    _currentRole = config.role;

    if (_currentRole == 'thread') {
      markThreadsLoading(true);
    } else {
      markThreadLoading(true);
    }
    markWidgetLoading(true);
    onDeviceChange();
  }
  init();

  /**
   * Selecting threads
   */
  JX.Stratcom.listen(
    'conpherence-selectthread',
    null,
    function (e) {
      selectThreadByID(e.getData().id);
    }
  );

  function selectThreadByID(id, update_page_data) {
    var thread = JX.$(id);
    selectThread(thread, update_page_data);
  }

  function selectThread(node, update_page_data) {
    if (_thread.node) {
      JX.DOM.alterClass(_thread.node, 'conpherence-selected', false);
      // keep the unread-count hidden still. big TODO once we ajax in updates
      // to threads to make this work right and move threads between read /
      // unread
    }

    JX.DOM.alterClass(node, 'conpherence-selected', true);
    JX.DOM.alterClass(node, 'hide-unread-count', true);

    _thread.node = node;

    var data = JX.Stratcom.getData(node);
    _thread.selected = data.threadID;

    if (update_page_data) {
      updatePageData(data);
    }

    redrawThread();
  }

  function updatePageData(data) {
    var uri_suffix = _thread.selected + '/';
    if (data.use_base_uri) {
      uri_suffix = '';
    }
    JX.History.replace(config.baseURI + uri_suffix);
    if (data.title) {
      document.title = data.title;
    } else if (_thread.node) {
      var threadData = JX.Stratcom.getData(_thread.node);
      document.title = threadData.title;
    }
  }

  JX.Stratcom.listen(
    'conpherence-update-page-data',
    null,
    function (e) {
      updatePageData(e.getData());
    }
  );

  function redrawThread() {
    if (!_thread.node) {
      return;
    }

    if (_thread.visible == _thread.selected) {
      return;
    }

    var data = JX.Stratcom.getData(_thread.node);

    if (_thread.visible !== null || !config.hasThread) {
      markThreadLoading(true);
      var uri = config.baseURI + data.threadID + '/';
      new JX.Workflow(uri, {})
        .setHandler(JX.bind(null, onLoadThreadResponse, data.threadID))
        .start();
    } else if (config.hasThread) {
      _scrollMessageWindow();
    } else {
      didRedrawThread();
    }

    if (_thread.visible !== null || !config.hasWidgets) {
      reloadWidget(data);
    } else {
     JX.Stratcom.invoke(
      'conpherence-update-widgets',
      null,
      {
        widget : getDefaultWidget(),
        buildSelectors : false,
        toggleWidget : true,
        threadID : _thread.selected
      });
    }

    _thread.visible = _thread.selected;
  }

  function markThreadsLoading(loading) {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var menu = JX.DOM.find(root, 'div', 'conpherence-menu-pane');
    JX.DOM.alterClass(menu, 'loading', loading);
  }

  function markThreadLoading(loading) {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var header_root = JX.DOM.find(root, 'div', 'conpherence-header-pane');
    var messages_root = JX.DOM.find(root, 'div', 'conpherence-message-pane');
    var form_root = JX.DOM.find(root, 'div', 'conpherence-form');

    JX.DOM.alterClass(header_root, 'loading', loading);
    JX.DOM.alterClass(messages_root, 'loading', loading);
    JX.DOM.alterClass(form_root, 'loading', loading);

    try {
      var textarea = JX.DOM.find(form, 'textarea');
      textarea.disabled = loading;
      var button = JX.DOM.find(form, 'button');
      button.disabled = loading;
    } catch (ex) {
      // haven't loaded it yet!
    }
  }

  function markWidgetLoading(loading) {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var widgets_root = JX.DOM.find(root, 'div', 'conpherence-widget-pane');

    JX.DOM.alterClass(widgets_root, 'loading', loading);
  }

  function reloadWidget(data) {
    markWidgetLoading(true);
    if (!data.widget) {
      data.widget = getDefaultWidget();
    }
    var widget_uri = config.baseURI + 'widget/' + data.threadID + '/';
    new JX.Workflow(widget_uri, {})
      .setHandler(JX.bind(null, onWidgetResponse, data.threadID, data.widget))
      .start();
  }
  JX.Stratcom.listen(
    'conpherence-reload-widget',
    null,
    function (e) {
      var data = e.getData();
      if (data.threadID != _thread.selected) {
        return;
      }
      reloadWidget(data);
    }
  );

  function onWidgetResponse(thread_id, widget, response) {
    // we got impatient and this is no longer the right answer :/
    if (_thread.selected != thread_id) {
      return;
    }
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var widgets_root = JX.DOM.find(root, 'div', 'conpherence-widgets-holder');
    JX.DOM.setContent(widgets_root, JX.$H(response.widgets));

    JX.Stratcom.invoke(
      'conpherence-update-widgets',
      null,
      {
        widget : widget,
        buildSelectors : true,
        toggleWidget : true,
        threadID : _thread.selected
      });

    markWidgetLoading(false);
  }

  function getDefaultWidget() {
    var device = JX.Device.getDevice();
    var widget = 'conpherence-message-pane';
    if (device == 'desktop') {
      widget = 'widgets-people';
    }
    return widget;
  }

  function onLoadThreadResponse(thread_id, response) {
    // we got impatient and this is no longer the right answer :/
    if (_thread.selected != thread_id) {
      return;
    }
    var header = JX.$H(response.header);
    var messages = JX.$H(response.messages);
    var form = JX.$H(response.form);
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var header_root = JX.DOM.find(root, 'div', 'conpherence-header-pane');
    var messages_root = JX.DOM.find(root, 'div', 'conpherence-messages');
    var form_root = JX.DOM.find(root, 'div', 'conpherence-form');
    JX.DOM.setContent(header_root, header);
    JX.DOM.setContent(messages_root, messages);
    JX.DOM.setContent(form_root, form);

    markThreadLoading(false);

    didRedrawThread(true);
  }

  /**
   * This function is a wee bit tricky. Internally, we want to scroll the
   * message window and let other stuff - notably widgets - redraw / build if
   * necessary. Externally, we want a hook to scroll the message window
   * - notably when the widget selector is used to invoke the message pane.
   * The following three functions get 'er done.
   */
   function didRedrawThread(build_device_widget_selector) {
     _scrollMessageWindow();
     JX.Stratcom.invoke(
       'conpherence-did-redraw-thread',
       null,
       {
         widget : getDefaultWidget(),
         threadID : _thread.selected,
         buildDeviceWidgetSelector : build_device_widget_selector
       });
  }
  function _scrollMessageWindow() {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var messages_root = JX.DOM.find(root, 'div', 'conpherence-messages');
    messages_root.scrollTop = messages_root.scrollHeight;
  }
  JX.Stratcom.listen(
    'conpherence-redraw-thread',
    null,
    function (e) {
      _scrollMessageWindow();
    }
  );

  JX.Stratcom.listen(
    'click',
    'conpherence-menu-click',
    function(e) {
      if (!e.isNormalClick()) {
        return;
      }

      // On devices, just follow the link normally.
      if (JX.Device.getDevice() != 'desktop') {
        return;
      }

      e.kill();
      selectThread(e.getNode('conpherence-menu-click'), true);
    });

  JX.Stratcom.listen('click', 'conpherence-edit-metadata', function (e) {
    e.kill();
    var root = e.getNode('conpherence-layout');
    var form = JX.DOM.find(root, 'form', 'conpherence-pontificate');
    var data = e.getNodeData('conpherence-edit-metadata');
    var header = JX.DOM.find(root, 'div', 'conpherence-header-pane');
    var messages = JX.DOM.find(root, 'div', 'conpherence-messages');

    new JX.Workflow.newFromForm(form, data)
      .setHandler(JX.bind(this, function(r) {
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;

        JX.DOM.setContent(
          header,
          JX.$H(r.header)
        );

        try {
          // update the menu entry
          JX.DOM.replace(
            JX.$(r.conpherence_phid + '-nav-item'),
            JX.$H(r.nav_item)
          );
          JX.Stratcom.invoke(
            'conpherence-selectthread',
            null,
            { id : r.conpherence_phid + '-nav-item' }
          );
        } catch (ex) {
          // Ignore; this view may not have a menu.
        }
      }))
      .start();
  });

  var _loadingTransactionID = null;
  JX.Stratcom.listen('click', 'show-older-messages', function(e) {
    e.kill();
    var data = e.getNodeData('show-older-messages');
    if (data.oldest_transaction_id == _loadingTransactionID) {
      return;
    }
    _loadingTransactionID = data.oldest_transaction_id;
    var node = e.getNode('show-older-messages');
    JX.DOM.setContent(node, 'Loading...');
    JX.DOM.alterClass(node, 'conpherence-show-older-messages-loading', true);

    var conf_id = _thread.selected;
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var messages_root = JX.DOM.find(root, 'div', 'conpherence-messages');
    new JX.Workflow(config.baseURI + conf_id + '/', data)
    .setHandler(function(r) {
      JX.DOM.remove(node);
      var messages = JX.$H(r.messages);
      JX.DOM.prependContent(
        messages_root,
        JX.$H(messages));
    }).start();
  });

  /**
   * On devices, we just show a thread list, so we don't want to automatically
   * select or load any threads. On desktop, we automatically select the first
   * thread, changing the _currentRole from list to thread.
   */
  function onDeviceChange() {
    var new_device = JX.Device.getDevice();
    if (new_device === _oldDevice) {
      return;
    }

    if (_oldDevice === null) {
      _oldDevice = new_device;
      if (_currentRole == 'list') {
        if (new_device != 'desktop') {
          return;
        }
      } else {
        loadThreads();
        return;
      }
    }
    var update_toggled_widget =
      new_device == 'desktop' || _oldDevice == 'desktop';
    _oldDevice = new_device;

    if (_thread.visible !== null && update_toggled_widget) {
      JX.Stratcom.invoke(
        'conpherence-did-redraw-thread',
        null,
        {
          widget : getDefaultWidget(),
          threadID : _thread.selected
        });
    }

    if (_currentRole == 'list' && new_device == 'desktop') {
      // this selects a thread and loads it
      didLoadThreads();
      _currentRole = 'thread';
      var root = JX.DOM.find(document, 'div', 'conpherence-layout');
      JX.DOM.alterClass(root, 'conpherence-role-list', false);
      JX.DOM.alterClass(root, 'conpherence-role-thread', true);
    }
  }
  JX.Stratcom.listen('phabricator-device-change', null, onDeviceChange);

  function loadThreads() {
    markThreadsLoading(true);
    var uri = config.baseURI + 'thread/' + config.selectedThreadID + '/';
    new JX.Workflow(uri)
      .setHandler(onLoadThreadsResponse)
      .start();
  }

  function onLoadThreadsResponse(r) {
    var layout = JX.$(config.layoutID);
    var menu = JX.DOM.find(layout, 'div', 'conpherence-menu-pane');
    JX.DOM.setContent(menu, JX.$H(r));

    config.selectedID && selectThreadByID(config.selectedID);

    _thread.node.scrollIntoView();

    markThreadsLoading(false);
  }

  function didLoadThreads() {
    // If there's no thread selected yet, select the current thread or the
    // first thread.
    if (!_thread.selected) {
      if (config.selectedID) {
        selectThreadByID(config.selectedID, true);
      } else {
        var layout = JX.$(config.layoutID);
        var threads = JX.DOM.scry(layout, 'a', 'conpherence-menu-click');
        if (threads.length) {
          selectThread(threads[0]);
        } else {
          var nothreads = JX.DOM.find(layout, 'div', 'conpherence-no-threads');
          nothreads.style.display = 'block';
          markThreadLoading(false);
          markWidgetLoading(false);
        }
      }
    }
  }

  var handleThreadScrollers = function (e) {
    e.kill();

    var data = e.getNodeData('conpherence-menu-scroller');
    var scroller = e.getNode('conpherence-menu-scroller');
    JX.DOM.alterClass(scroller, 'loading', true);
    JX.DOM.setContent(scroller.firstChild, 'Loading...');
    new JX.Workflow(scroller.href, data)
      .setHandler(
        JX.bind(null, threadScrollerResponse, scroller, data.direction))
      .start();
  };

  var threadScrollerResponse = function (scroller, direction, r) {
    var html = JX.$H(r.html);

    var thread_phids = r.phids;
    var reselect_id = null;
    // remove any threads that are in the list that we just got back
    // in the result set; things have changed and they'll be in the
    // right place soon
    for (var ii = 0; ii < thread_phids.length; ii++) {
      try {
        var node_id = thread_phids[ii] + '-nav-item';
        var node = JX.$(node_id);
        var node_data = JX.Stratcom.getData(node);
        if (node_data.id == _thread.selected) {
          reselect_id = node_id;
        }
        JX.DOM.remove(node);
      } catch (ex) {
        // ignore , just haven't seen this thread yet
      }
    }

    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var menu_root = JX.DOM.find(root, 'div', 'conpherence-menu-pane');
    var scroll_y = 0;
    // we have to do some hyjinx in the up case to make the menu scroll to
    // where it should
    if (direction == 'up') {
      var style = {
        position: 'absolute',
        left:     '-10000px'
      };
      var test_size = JX.$N('div', {style: style}, html);
      document.body.appendChild(test_size);
      var html_size = JX.Vector.getDim(test_size);
      JX.DOM.remove(test_size);
      scroll_y = html_size.y;
    }
    JX.DOM.replace(scroller, html);
    menu_root.scrollTop += scroll_y;

    if (reselect_id) {
      JX.Stratcom.invoke(
        'conpherence-selectthread',
        null,
        { id : reselect_id }
      );
    }
  };

  JX.Stratcom.listen(
    ['click'],
    'conpherence-menu-scroller',
    handleThreadScrollers
  );

  var onkeydownDraft = function (e) {
    var form = e.getNode('tag:form');
    var data = e.getNodeData('tag:form');

    if (!data.preview) {
      var uri = config.baseURI + 'update/' + _thread.selected + '/';
      data.preview = new JX.PhabricatorShapedRequest(
        uri,
        JX.bag,
        function () {
          var data = JX.DOM.convertFormToDictionary(form);
          data.action = 'draft';
          return data;
        });
    }

    data.preview.trigger();
  };

  JX.Stratcom.listen(
    ['keydown'],
    'conpherence-pontificate',
    onkeydownDraft);

});
