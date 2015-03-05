/**
 * @provides javelin-behavior-durable-column
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-scrollbar
 *           javelin-quicksand
 *           phabricator-keyboard-shortcut
 */

JX.behavior('durable-column', function() {

  var shouldInit = true;
  var loadThreadID = null;
  var loadedThreadID = null;
  var loadedThreadPHID = null;
  var latestTransactionID = null;

  var frame = JX.$('phabricator-standard-page');
  var quick = JX.$('phabricator-standard-page-body');
  var show = false;


  // TODO - this "upating" stuff is a copy from behavior-pontificate
  // TODO: This isn't very clean. When you submit a message, you may get a
  // notification about it back before you get the rendered message back. To
  // prevent this, we keep track of whether we're currently updating the
  // thread. If we are, we hold further updates until the response comes
  // back.

  // After the response returns, we'll do another update if we know about
  // a transaction newer than the one we got back from the server.
  var updating = null;
  // Copy continues with slight modifications for how we store data now
  JX.Stratcom.listen('aphlict-server-message', null, function(e) {
    var message = e.getData();

    if (message.type != 'message') {
      // Not a message event.
      return;
    }

    if (message.threadPHID != loadedThreadPHID) {
      // Message event for some thread other than the visible one.
      return;
    }

    if (message.messageID <= latestTransactionID) {
      // Message event for something we already know about.
      return;
    }

    // If we're currently updating, wait for the update to complete.
    // If this notification tells us about a message which is newer than the
    // newest one we know to exist, keep track of it so we can update once
    // the in-flight update finishes.
    if (updating && updating.threadPHID == loadedThreadPHID) {
      if (message.messageID > updating.knownID) {
        updating.knownID = message.messageID;
        return;
      }
    }

    update_thread();
  });
  function update_thread() {
    var params = {
      action: 'load',
      latest_transaction_id: latestTransactionID,
      minimal_display: true
    };

    var uri = '/conpherence/update/' + loadedThreadID + '/';

    var workflow = new JX.Workflow(uri)
      .setData(params)
      .setHandler(function(r) {
        var messages = _getColumnMessagesNode();
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;

        latestTransactionID = r.latest_transaction_id;
      });

    sync_workflow(workflow);
  }
  function sync_workflow(workflow) {
    updating = {
      threadPHID: loadedThreadPHID,
      knownID: latestTransactionID
    };
    workflow.listen('finally', function() {
      var need_sync = (updating.knownID > latestTransactionID);
      updating = null;
      if (need_sync) {
        update_thread();
      }
    });
    workflow.start();
  }
  // end copy / hack of stuff with big ole TODO on it


  function _toggleColumn() {
    if (window.location.pathname.indexOf('/conpherence/') === 0) {
      return;
    }
    show = !show;
    JX.DOM.alterClass(frame, 'with-durable-column', show);
    var column = JX.$('conpherence-durable-column');
    if (show) {
      JX.DOM.show(column);
      loadThreadContent(loadThreadID);
    } else {
      JX.DOM.hide(column);
    }
    JX.Stratcom.invoke('resize');
    JX.Quicksand.setFrame(show ? quick : null);
  }

  new JX.KeyboardShortcut('\\', 'Toggle Conpherence Column')
    .setHandler(_toggleColumn)
    .register();

  new JX.Scrollbar(JX.$('conpherence-durable-column-content'));

  JX.Quicksand.start();

  JX.Stratcom.listen(
    'click',
    'conpherence-durable-column-header-action',
    function (e) {
      e.kill();
      var data = e.getNodeData('conpherence-durable-column-header-action');
      var action = data.action;
      var link = e.getNode('tag:a');

      switch (action) {
        case 'add_person':
          JX.Stratcom.invoke('notification-panel-close');
          var params = {
            action: action,
            latest_transaction_id: latestTransactionID,
            minimal_display: true
          };
          var workflow = new JX.Workflow.newFromLink(link)
            .setData(params)
            .setHandler(function(r) {
              var messages = _getColumnMessagesNode();
              JX.DOM.appendContent(messages, JX.$H(r.transactions));
              messages.scrollTop = messages.scrollHeight;

              latestTransactionID = r.latest_transaction_id;
              // since this is a two step workflow, and the "finally" method
              // gets called on the first form load, restore "updating" if
              // necessary
              if (updating === null) {
                updating = {
                  threadPHID: loadedThreadPHID,
                  knownID: latestTransactionID
                };
              }
            });
          sync_workflow(workflow);
          break;
        case 'go_conpherence':
          JX.$U(link.href).go();
          break;
        case 'close_window':
          JX.Stratcom.invoke('notification-panel-close');
          _toggleColumn();
          break;
      }
    });

  function _getColumnNode() {
    return JX.$('conpherence-durable-column');
  }

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

  function loadThreadContent(thread_id) {
    // loaded this thread already
    if (loadedThreadID !== null && loadedThreadID == thread_id) {
      return;
    }
    _markLoading(true);

    var uri = '/conpherence/columnview/';
    var params = null;
    // We can pick a thread from the server the first time
    if (shouldInit) {
      shouldInit = false;
      params = { shouldInit : true };
    } else {
      params = { id : thread_id };
    }
    var handler = function(r) {
      var column = _getColumnNode();
      var new_column = JX.$H(r.content);
      loadedThreadID = r.threadID;
      loadedThreadPHID = r.threadPHID;
      loadThreadID = r.threadID;
      latestTransactionID = r.latestTransactionID;
      JX.DOM.replace(column, new_column);
      JX.DOM.show(_getColumnNode());
      new JX.Scrollbar(JX.$('conpherence-durable-column-content'));
      _markLoading(false);
    };

    new JX.Workflow(uri)
      .setData(params)
      .setHandler(handler)
      .start();
  }

  function _sendMessage(e) {
    e.kill();
    _markLoading(true);

    var form = _getColumnFormNode();
    var params = {
      latest_transaction_id : latestTransactionID,
      minimal_display : true
    };
    var workflow = JX.Workflow.newFromForm(form, params)
      .setHandler(function(r) {
        var messages = _getColumnMessagesNode();
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;

        var textarea = _getColumnTextareaNode();
        textarea.value = '';

        latestTransactionID = r.latest_transaction_id;

        _markLoading(false);

        _focusColumnTextareaNode();
      });
    sync_workflow(workflow);
  }

  JX.Stratcom.listen(
      'click',
      'conpherence-send-message',
      _sendMessage);

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'conpherence-message-form',
    _sendMessage);

});
