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
 *           javelin-scrollbar
 *           phabricator-title
 *           phabricator-shaped-request
 *           conpherence-thread-manager
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

  var scrollbar = null;

  // TODO - move more logic into the ThreadManager
  var threadManager = new JX.ConpherenceThreadManager();
  threadManager.setMessagesRootCallback(function() {
    return scrollbar.getContentNode();
  });
  threadManager.setWillLoadThreadCallback(function() {
    markThreadsLoading(true);
  });
  threadManager.setDidLoadThreadCallback(function(r) {
    var header = JX.$H(r.header);
    var search = JX.$H(r.search);
    var messages = JX.$H(r.transactions);
    var form = JX.$H(r.form);
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var header_root = JX.DOM.find(root, 'div', 'conpherence-header-pane');
    var search_root = JX.DOM.find(root, 'div', 'conpherence-search-main');
    var form_root = JX.DOM.find(root, 'div', 'conpherence-form');
    JX.DOM.setContent(header_root, header);
    JX.DOM.setContent(search_root, search);
    JX.DOM.setContent(scrollbar.getContentNode(), messages);
    JX.DOM.setContent(form_root, form);

    markThreadsLoading(false);

    didRedrawThread(true);
  });

  threadManager.setDidUpdateThreadCallback(function(r) {
    _scrollMessageWindow();
  });

  threadManager.setWillSendMessageCallback(function () {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var form_root = JX.DOM.find(root, 'div', 'conpherence-form');
    markThreadLoading(true);
    JX.DOM.alterClass(form_root, 'loading', true);
  });

  threadManager.setDidSendMessageCallback(function (r, non_update) {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var form_root = JX.DOM.find(root, 'div', 'conpherence-form');
    var textarea = JX.DOM.find(form_root, 'textarea');
    if (!non_update) {
      _scrollMessageWindow();
      textarea.value = '';
    }
    markThreadLoading(false);

    setTimeout(function() { JX.DOM.focus(textarea); }, 100);
  });
  threadManager.start();

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
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var messages_root = JX.DOM.find(root, 'div', 'conpherence-message-pane');
    var messages = JX.DOM.find(messages_root, 'div', 'conpherence-messages');
    scrollbar = new JX.Scrollbar(messages);
    scrollbar.setAsScrollFrame();
  }
  init();

  /**
   * Selecting threads
   */
  function selectThreadByID(id, update_page_data) {
    var thread = JX.$(id);
    selectThread(thread, update_page_data);
  }

  function selectThread(node, update_page_data) {
    if (_thread.node) {
      JX.DOM.alterClass(_thread.node, 'conpherence-selected', false);
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
    var uri = '/Z' + _thread.selected;
    JX.History.replace(uri);
    if (data.title) {
      JX.Title.setTitle(data.title);
    } else if (_thread.node) {
      var threadData = JX.Stratcom.getData(_thread.node);
      JX.Title.setTitle(threadData.title);
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
      threadManager.setLoadThreadURI('/conpherence/' + data.threadID + '/');
      threadManager.loadThreadByID(data.threadID);
    } else if (config.hasThread) {
      // we loaded the thread via the server so let the thread manager know
      threadManager.setLoadedThreadID(config.selectedThreadID);
      threadManager.setLoadedThreadPHID(config.selectedThreadPHID);
      threadManager.setLatestTransactionID(config.latestTransactionID);
      threadManager.setCanEditLoadedThread(config.canEditSelectedThread);
      threadManager.cacheCurrentTransactions();
      _scrollMessageWindow();
      _focusTextarea();
    } else {
      didRedrawThread();
    }

    if (_thread.visible !== null || !config.hasWidgets) {
      reloadWidget(data);
    }

    _thread.visible = _thread.selected;
  }

  function markThreadsLoading(loading) {
    var root = JX.$('conpherence-main-layout');
    JX.DOM.alterClass(root, 'loading', loading);
  }

  function markThreadLoading(loading) {
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
    var widgets_root = JX.DOM.find(root, 'div', 'conpherence-participant-pane');

    JX.DOM.alterClass(widgets_root, 'loading', loading);
  }

  function reloadWidget(data) {
    markWidgetLoading(true);
    if (!data.widget) {
      data.widget = getDefaultWidget();
    }
    var widget_uri = config.baseURI + 'participant/' + data.threadID + '/';
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
  }

  function getDefaultWidget() {
    return 'widgets-people';
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
    _focusTextarea();
    JX.Stratcom.invoke(
      'conpherence-did-redraw-thread',
      null,
      {
        widget : getDefaultWidget(),
        threadID : _thread.selected,
        buildDeviceWidgetSelector : build_device_widget_selector
      });
  }

  var _firstScroll = true;
  function _scrollMessageWindow() {
    if (_firstScroll) {
      _firstScroll = false;

      // We want to let the standard #anchor tech take over after we make sure
      // we don't have to present the user with a "load older message?" dialog
      if (window.location.hash) {
        var hash = window.location.hash.replace(/^#/, '');
        try {
          JX.$('anchor-' + hash);
        } catch (ex) {
          var uri = '/conpherence/' +
            _thread.selected + '/' + hash + '/';
          threadManager.setLoadThreadURI(uri);
          threadManager.loadThreadByID(_thread.selected, true);
          _firstScroll = true;
          return;
        }
        return;
      }
    }
    scrollbar.scrollTo(scrollbar.getViewportNode().scrollHeight);
  }
  function _focusTextarea() {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var form_root = JX.DOM.find(root, 'div', 'conpherence-form');
    try {
      var textarea = JX.DOM.find(form_root, 'textarea');
      // We may have a draft so do this JS trick so we end up focused at the
      // end of the draft.
      var textarea_value = textarea.value;
      textarea.value = '';
      JX.DOM.focus(textarea);
      textarea.value = textarea_value;
    } catch (ex) {
      // no textarea? no problem
    }
  }
  JX.Stratcom.listen(
    'conpherence-redraw-thread',
    null,
    function () {
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

  JX.Stratcom.listen(
    ['click'],
    'conpherence-menu-see-more',
    function (e) {
      e.kill();
      var sigil = e.getNodeData('conpherence-menu-see-more').moreSigil;
      var root = JX.$('conpherence-menu-pane');
      var more = JX.DOM.scry(root, 'li', sigil);
      for (var i = 0; i < more.length; i++) {
        JX.DOM.alterClass(more[i], 'hidden', false);
      }
      JX.DOM.hide(e.getNode('conpherence-menu-see-more'));
    });

  JX.Stratcom.listen(
    ['keydown'],
    'conpherence-pontificate',
    function (e) {
      threadManager.handleDraftKeydown(e);
    });

});
