/**
 * @provides javelin-behavior-conpherence-menu
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-request
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-behavior-device
 *           javelin-history
 *           javelin-vector
 */

JX.behavior('conpherence-menu', function(config) {

  var thread = {
    selected: null,
    node: null,
    visible: null
  };

  function selectthreadid(id, updatePageData) {
    var threads = JX.DOM.scry(document.body, 'a', 'conpherence-menu-click');
    for (var ii = 0; ii < threads.length; ii++) {
      var data = JX.Stratcom.getData(threads[ii]);
      if (data.id == id) {
        selectthread(threads[ii], updatePageData);
        return;
      }
    }
  }

  function selectthread(node, updatePageData) {

    if (thread.node) {
      JX.DOM.alterClass(thread.node, 'conpherence-selected', false);
      // keep the unread-count hidden still. big TODO once we ajax in updates
      // to threads to make this work right and move threads between read /
      // unread
    }

    JX.DOM.alterClass(node, 'conpherence-selected', true);
    JX.DOM.alterClass(node, 'hide-unread-count', true);

    thread.node = node;

    var data = JX.Stratcom.getData(node);
    thread.selected = data.id;

    if (updatePageData) {
      updatepagedata(data);
    }

    redrawthread();
  }

  JX.Stratcom.listen(
    'conpherence-selectthread',
    null,
    function (e) {
      var node = JX.$(e.getData().id);
      selectthread(node);
    }
  );

  function updatepagedata(data) {
    var uri_suffix = thread.selected + '/';
    if (data.use_base_uri) {
      uri_suffix = '';
    }
    JX.History.replace(config.base_uri + uri_suffix);
    if (data.title) {
      document.title = data.title;
    } else if (thread.node) {
      var threadData = JX.Stratcom.getData(thread.node);
      document.title = threadData.title;
    }
  }

  JX.Stratcom.listen(
    'conpherence-update-page-data',
    null,
    function (e) {
      updatepagedata(e.getData());
    }
  );

  function redrawthread() {
    if (!thread.node) {
      return;
    }

    if (thread.visible == thread.selected) {
      return;
    }

    var data = JX.Stratcom.getData(thread.node);

    if (thread.visible !== null || !config.hasThread) {
    var uri = config.base_uri + data.id + '/';
      new JX.Workflow(uri, {})
        .setHandler(onloadthreadresponse)
        .start();
    } else {
      didredrawthread();
    }

    if (thread.visible !== null || !config.hasWidgets) {
      var widget_uri = config.base_uri + 'widget/' + data.id + '/';
      new JX.Workflow(widget_uri, {})
        .setHandler(onwidgetresponse)
        .start();
    } else {
      updatetoggledwidget();
    }

    thread.visible = thread.selected;
  }

  function onwidgetresponse(response) {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var widgetsRoot = JX.DOM.find(root, 'div', 'conpherence-widget-pane');
    JX.DOM.setContent(widgetsRoot, JX.$H(response.widgets));
    updatetoggledwidget();
  }

  function updatetoggledwidget() {
    var device = JX.Device.getDevice();
    if (device != 'desktop') {
      if (config.role == 'list') {
        JX.Stratcom.invoke(
          'conpherence-toggle-widget',
          null,
          {
            widget : 'conpherence-menu-pane'
          }
        );
      } else {
        JX.Stratcom.invoke(
          'conpherence-toggle-widget',
          null,
          {
            widget : 'conpherence-message-pane'
          }
        );
      }
    } else {
      JX.Stratcom.invoke(
        'conpherence-toggle-widget',
        null,
        {
          widget : 'widgets-files'
        }
      );
    }
  }

  function onloadthreadresponse(response) {
    var header = JX.$H(response.header);
    var messages = JX.$H(response.messages);
    var form = JX.$H(response.form);
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var headerRoot = JX.DOM.find(root, 'div', 'conpherence-header-pane');
    var messagesRoot = JX.DOM.find(root, 'div', 'conpherence-messages');
    var formRoot = JX.DOM.find(root, 'div', 'conpherence-form');
    JX.DOM.setContent(headerRoot, header);
    JX.DOM.setContent(messagesRoot, messages);
    JX.DOM.setContent(formRoot, form);

    didredrawthread();
  }

  function didredrawthread() {
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var messagesRoot = JX.DOM.find(root, 'div', 'conpherence-messages');
    messagesRoot.scrollTop = messagesRoot.scrollHeight;
  }

  JX.Stratcom.listen(
    'conpherence-redraw-thread',
    null,
    function (e) {
      didredrawthread();
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
      selectthread(e.getNode('conpherence-menu-click'), true);
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

  JX.Stratcom.listen('click', 'show-older-messages', function(e) {
    e.kill();
    var data = e.getNodeData('show-older-messages');
    var oldest_transaction_id = data.oldest_transaction_id;
    var conf_id = thread.selected;
    JX.DOM.remove(e.getNode('show-older-messages'));
    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var messages_root = JX.DOM.find(root, 'div', 'conpherence-messages');
    new JX.Request(config.base_uri + conf_id + '/', function(r) {
      var messages = JX.$H(r.messages);
      JX.DOM.prependContent(
        messages_root,
        JX.$H(messages));
    }).setData({ oldest_transaction_id : oldest_transaction_id }).send();
  });

  // On mobile, we just show a thread list, so we don't want to automatically
  // select or load any threads. On Desktop, we automatically select the first
  // thread.
  var old_device = null;
  function ondevicechange() {
    var new_device = JX.Device.getDevice();
    if (new_device === old_device) {
      return;
    }
    var update_toggled_widget =
      new_device == 'desktop' || old_device == 'desktop';
    old_device = new_device;

    if (thread.visible !== null && update_toggled_widget) {
      updatetoggledwidget();
    }

    if (!config.hasThreadList) {
      loadthreads();
    } else {
      didloadthreads();
    }
  }

  JX.Stratcom.listen('phabricator-device-change', null, ondevicechange);
  ondevicechange();

  function loadthreads() {
    var uri = config.base_uri + 'thread/' + config.selectedID + '/';
    new JX.Workflow(uri)
      .setHandler(onloadthreadsresponse)
      .start();
  }

  function onloadthreadsresponse(r) {
    var layout = JX.$(config.layoutID);
    var menu = JX.DOM.find(layout, 'div', 'conpherence-menu-pane');
    JX.DOM.setContent(menu, JX.$H(r));

    config.selectedID && selectthreadid(config.selectedID);

    thread.node.scrollIntoView();
  }

  function didloadthreads() {
    // If there's no thread selected yet, select the current thread or the
    // first thread.
    if (!thread.selected) {
      if (config.selectedID) {
        selectthreadid(config.selectedID, true);
      } else {
        var layout = JX.$(config.layoutID);
        var threads = JX.DOM.scry(layout, 'a', 'conpherence-menu-click');
        if (threads.length) {
          selectthread(threads[0]);
        } else {
          var nothreads = JX.DOM.find(layout, 'div', 'conpherence-no-threads');
          nothreads.style.display = 'block';
        }
      }
    }
    redrawthread();
  }

  var handlethreadscrollers = function (e) {
    e.kill();

    var data = e.getNodeData('conpherence-menu-scroller');
    var scroller = e.getNode('conpherence-menu-scroller');
    new JX.Workflow(scroller.href, data)
      .setHandler(
        JX.bind(null, threadscrollerresponse, scroller, data.direction))
      .start();
  };

  var threadscrollerresponse = function (scroller, direction, r) {
    var html = JX.$H(r.html);

    var threadPhids = r.phids;
    var reselectId = null;
    // remove any threads that are in the list that we just got back
    // in the result set; things have changed and they'll be in the
    // right place soon
    for (var ii = 0; ii < threadPhids.length; ii++) {
      try {
        var nodeId = threadPhids[ii] + '-nav-item';
        var node = JX.$(nodeId);
        var nodeData = JX.Stratcom.getData(node);
        if (nodeData.id == thread.selected) {
          reselectId = nodeId;
        }
        JX.DOM.remove(node);
      } catch (ex) {
        // ignore , just haven't seen this thread yet
      }
    }

    var root = JX.DOM.find(document, 'div', 'conpherence-layout');
    var menuRoot = JX.DOM.find(root, 'div', 'conpherence-menu-pane');
    var scrollY = 0;
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
      scrollY = html_size.y;
    }
    JX.DOM.replace(scroller, html);
    menuRoot.scrollTop += scrollY;

    if (reselectId) {
      JX.Stratcom.invoke(
        'conpherence-selectthread',
        null,
        { id : reselectId }
      );
    }
  };

  JX.Stratcom.listen(
    ['click'],
    'conpherence-menu-scroller',
    handlethreadscrollers
  );

});
