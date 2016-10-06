/**
 * @provides javelin-behavior-durable-column
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-behavior-device
 *           javelin-scrollbar
 *           javelin-quicksand
 *           phabricator-keyboard-shortcut
 *           conpherence-thread-manager
 */

JX.behavior('durable-column', function(config, statics) {
  // TODO: Currently, updating the column sends the entire column back. This
  // includes the `durable-column` behavior itself, which tries to re-initialize
  // the column. Detect this and bail.
  //
  // If ThreadManager gets separated into a UI part and a thread part (which
  // seems likely), responses may no longer ship back the entire column. This
  // might let us remove this check.
  if (statics.initialized) {
    return;
  } else {
    statics.initialized = true;
  }

  var userVisible = config.visible;
  var userMinimize = config.minimize;
  var show = null;
  var loadThreadID = null;
  var scrollbar = null;

  var margin = JX.Scrollbar.getScrollbarControlMargin();

  var quick = JX.$('phabricator-standard-page-body');

  function _getColumnNode() {
    return JX.$('conpherence-durable-column');
  }

  function _getColumnScrollNode() {
    var column = _getColumnNode();
    return JX.DOM.find(column, 'div', 'conpherence-durable-column-main');
  }

  function _updateColumnVisibility() {
    var new_value = (userVisible);
    if (new_value !== show) {
      show = new_value;
      _drawColumn(show);
    }
  }

  function _toggleColumn() {
    userVisible = !userVisible;
    _updateColumnVisibility();

    new JX.Request(config.visibleURI)
      .setData({value: (show ? 1 : 0)})
      .send();
  }

  function _minimizeColumn(e) {
    e.kill();
    userMinimize = !userMinimize;
    JX.DOM.alterClass(document.body, 'minimize-column', userMinimize);
    JX.Stratcom.invoke('resize');

    if (!userMinimize) {
      var messages = _getColumnMessagesNode();
      scrollbar.scrollTo(messages.scrollHeight);
    }

    new JX.Request(config.minimizeURI)
      .setData({value: (userMinimize ? 1 : 0)})
      .send();
  }

  function _drawColumn(visible) {
    JX.DOM.alterClass(
      document.body,
      'with-durable-column',
      visible);

    var column = _getColumnNode();
    if (visible) {
      JX.DOM.show(column);
      threadManager.loadThreadByID(loadThreadID);
    } else {
      JX.DOM.hide(column);
    }
    JX.Quicksand.setFrame(visible ? quick : null);

    JX.Stratcom.invoke('resize');
  }

  JX.Stratcom.listen(
    'click',
    'conpherence-persist-column',
    _toggleColumn);

  JX.Stratcom.listen(
    'click',
    'conpherence-minimize-window',
    _minimizeColumn);

  scrollbar = new JX.Scrollbar(_getColumnScrollNode());

  JX.Quicksand.setFrame(userVisible ? quick : null);
  JX.Quicksand.start(config.quicksandConfig);

  /* Conpherence Thread Manager configuration - lots of display
   * callbacks.
   */

  var threadManager = new JX.ConpherenceThreadManager();
  threadManager.setMinimalDisplay(true);
  threadManager.setMessagesRootCallback(function() {
    return _getColumnMessagesNode();
  });
  threadManager.setLoadThreadURI('/conpherence/columnview/');
  threadManager.setWillLoadThreadCallback(function() {
    _markLoading(true);
  });
  threadManager.setDidLoadThreadCallback(function(r) {
    var column = _getColumnNode();
    var new_column = JX.$H(r.content);
    JX.DOM.replace(column, new_column);
    if (show) {
      JX.DOM.show(_getColumnNode());
    } else {
      JX.DOM.hide(_getColumnNode());
    }
    var messages = _getColumnMessagesNode();
    scrollbar = new JX.Scrollbar(_getColumnScrollNode());
    scrollbar.scrollTo(messages.scrollHeight);
    _markLoading(false);
    loadThreadID = threadManager.getLoadedThreadID();
  });
  threadManager.setDidUpdateThreadCallback(function(r) {
    var messages = _getColumnMessagesNode();
    scrollbar.scrollTo(messages.scrollHeight);
  });

  threadManager.setWillSendMessageCallback(function() {
    // Wipe the textarea immediately so the user can start typing more text.
    var textarea = _getColumnTextareaNode();
    textarea.value = '';
    _focusColumnTextareaNode();
  });

  threadManager.setDidSendMessageCallback(function(r, non_update) {
    if (non_update) {
      return;
    }
    var messages = _getColumnMessagesNode();
    scrollbar.scrollTo(messages.scrollHeight);
  });

  threadManager.setWillUpdateWorkflowCallback(function() {
    JX.Stratcom.invoke('notification-panel-close');
  });
  threadManager.setDidUpdateWorkflowCallback(function(r) {
    var messages = _getColumnMessagesNode();
    scrollbar.scrollTo(messages.scrollHeight);
    JX.DOM.setContent(_getColumnTitleNode(), r.conpherence_title);
  });
  threadManager.start();

  JX.Stratcom.listen(
    'click',
    'conpherence-durable-column-header-action',
    function (e) {
      e.kill();
      var data = e.getNodeData('conpherence-durable-column-header-action');
      var action = data.action;
      var link = e.getNode('tag:a');
      var params = null;

      switch (action) {
        case 'metadata':
          threadManager.runUpdateWorkflowFromLink(
            link,
            {
              action: action,
              force_ajax: true,
              stage: 'submit'
            });
          break;
        case 'add_person':
          threadManager.runUpdateWorkflowFromLink(
            link,
            {
              action: action,
              stage: 'submit'
            });
          break;
        case 'go_conpherence':
          JX.$U(link.href).go();
          break;
        case 'hide_column':
          JX.Stratcom.invoke('notification-panel-close');
          _toggleColumn();
          break;
      }
    });

  JX.Stratcom.listen(
    'click',
    'conpherence-durable-column-thread-icon',
    function (e) {
      e.kill();
      var icons = JX.DOM.scry(
        JX.$('conpherence-durable-column'),
        'a',
        'conpherence-durable-column-thread-icon');
      var data = e.getNodeData('conpherence-durable-column-thread-icon');
      var cdata = null;
      for (var i = 0; i < icons.length; i++) {
        cdata = JX.Stratcom.getData(icons[i]);
        JX.DOM.alterClass(
          icons[i],
          'selected',
          cdata.threadID == data.threadID);
      }
      JX.DOM.setContent(_getColumnTitleNode(), JX.$H(data.threadTitle));
      threadManager.loadThreadByID(data.threadID);
    });

  JX.Stratcom.listen('resize', null, _updateColumnVisibility);

  function _getColumnBodyNode() {
    var column = JX.$('conpherence-durable-column');
    return JX.DOM.find(
      column,
      'div',
      'conpherence-durable-column-body');
  }

  function _getColumnMessagesNode() {
    var column = JX.$('conpherence-durable-column');
    return JX.DOM.find(
      column,
      'div',
      'conpherence-durable-column-transactions');
  }

  function _getColumnTitleNode() {
    var column = JX.$('conpherence-durable-column');
    return JX.DOM.find(
      column,
      'div',
      'conpherence-durable-column-header-text');
  }

  function _getColumnFormNode() {
    var column = JX.$('conpherence-durable-column');
    return JX.DOM.find(
      column,
      'form',
      'conpherence-message-form');
  }

  function _getColumnTextareaNode() {
    var column = JX.$('conpherence-durable-column');
    return JX.DOM.find(
        column,
        'textarea',
        'conpherence-durable-column-textarea');
  }

  function _focusColumnTextareaNode() {
    var textarea = _getColumnTextareaNode();
    setTimeout(function() { JX.DOM.focus(textarea); }, 1);
  }

  function _markLoading(loading) {
    var column = _getColumnNode();
    JX.DOM.alterClass(column, 'loading', loading);
  }

  function _sendMessage(e) {
    e.kill();
    var form = _getColumnFormNode();
    threadManager.sendMessage(form, { minimal_display: true });
  }

  JX.Stratcom.listen(
    'click',
    'conpherence-send-message',
    _sendMessage);

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'conpherence-message-form',
    _sendMessage);

  // Send on enter if the shift key is not held.
  JX.Stratcom.listen(
    'keydown',
    'conpherence-message-form',
    function(e) {
      if (e.getSpecialKey() != 'return') {
        return;
      }

      var raw = e.getRawEvent();
      if (raw.shiftKey) {
        // If the shift key is pressed, let the browser write a newline into
        // the textarea.
        return;
      }

      // From here on, interpret this as a "send" action, not a literal
      // newline.
      e.kill();

      _sendMessage(e);
    });

  JX.Stratcom.listen(
    ['keydown'],
    'conpherence-durable-column-textarea',
    function (e) {
      threadManager.handleDraftKeydown(e);
    });

  // HTML5 placeholders are rendered as long as the input is empty, even if the
  // input is currently focused. This is undesirable for the chat input,
  // especially immediately after sending a message. Hide the placeholder while
  // the input is focused.
  JX.Stratcom.listen(
    ['focus', 'blur'],
    'conpherence-durable-column-textarea',
    function (e) {
      var node = e.getTarget();
      if (e.getType() == 'focus') {
        if (node.placeholder) {
          node.placeholderStorage = node.placeholder;
          node.placeholder = '';
        }
      } else {
        if (node.placeholderStorage) {
          node.placeholder = node.placeholderStorage;
          node.placeholderStorage = '';
        }
      }
    });

  JX.Stratcom.listen(
    'quicksand-redraw',
    null,
    function (e) {
      var new_data = e.getData().newResponse;
      var new_classes = new_data.bodyClasses;
      if (userMinimize) {
        new_classes = new_classes + ' minimize-column';
      }
      document.body.className = new_classes;
      JX.Title.setTitle(new_data.title);
    });

  _updateColumnVisibility();

});
