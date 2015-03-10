/**
 * @provides javelin-behavior-durable-column
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-scrollbar
 *           javelin-quicksand
 *           phabricator-keyboard-shortcut
 *           conpherence-thread-manager
 */

JX.behavior('durable-column', function() {

  var show = false;
  var loadThreadID = null;

  var frame = JX.$('phabricator-standard-page');
  var quick = JX.$('phabricator-standard-page-body');

  function _getColumnContentNode() {
    return JX.$('conpherence-durable-column-content');
  }

  function _toggleColumn() {
    if (window.location.pathname.indexOf('/conpherence/') === 0) {
      return;
    }
    show = !show;
    JX.DOM.alterClass(frame, 'with-durable-column', show);
    var column = JX.$('conpherence-durable-column');
    if (show) {
      JX.DOM.show(column);
      threadManager.loadThreadByID(loadThreadID);
    } else {
      JX.DOM.hide(column);
    }
    JX.Stratcom.invoke('resize');
    JX.Quicksand.setFrame(show ? quick : null);
  }

  new JX.KeyboardShortcut('\\', 'Toggle Conpherence Column')
    .setHandler(_toggleColumn)
    .register();

  new JX.Scrollbar(_getColumnContentNode());

  JX.Quicksand.start();

  /* Conpherence Thread Manager configuration - lots of display
   * callbacks.
   */
  var threadManager = new JX.ConpherenceThreadManager();
  threadManager.setMinimalDisplay(true);
  threadManager.setMessagesNodeFunction(_getColumnMessagesNode);
  threadManager.setTitleNodeFunction(_getColumnTitleNode);
  threadManager.setLoadThreadURI('/conpherence/columnview/');
  threadManager.setWillLoadThreadCallback(function () {
    _markLoading(true);
  });
  threadManager.setDidLoadThreadCallback(function (r) {
    var column = _getColumnNode();
    var new_column = JX.$H(r.content);
    JX.DOM.replace(column, new_column);
    JX.DOM.show(_getColumnNode());
    new JX.Scrollbar(_getColumnContentNode());
    _markLoading(false);
    loadThreadID = threadManager.getLoadedThreadID();
  });
  threadManager.setWillSendMessageCallback(function () {
    _markLoading(true);
  });
  threadManager.setDidSendMessageCallback(function (r) {
    var messages = _getColumnMessagesNode();
    JX.DOM.appendContent(messages, JX.$H(r.transactions));
    var content = _getColumnContentNode();
    content.scrollTop = content.scrollHeight;

    var textarea = _getColumnTextareaNode();
    textarea.value = '';

    _markLoading(false);

    _focusColumnTextareaNode();
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
          JX.Stratcom.invoke('notification-panel-close');
          threadManager.runUpdateWorkflowFromLink(
            link,
            {
              action: action,
              force_ajax: true,
              stage: 'submit'
            });
          break;
        case 'add_person':
          JX.Stratcom.invoke('notification-panel-close');
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

});
