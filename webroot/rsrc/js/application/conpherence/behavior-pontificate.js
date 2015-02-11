/**
 * @provides javelin-behavior-conpherence-pontificate
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-workflow
 *           javelin-stratcom
 */

JX.behavior('conpherence-pontificate', function() {

  // TODO: This isn't very clean. When you submit a message, you may get a
  // notification about it back before you get the rendered message back. To
  // prevent this, we keep track of whether we're currently updating the
  // thread. If we are, we hold further updates until the response comes
  // back.

  // After the response returns, we'll do another update if we know about
  // a transaction newer than the one we got back from the server.
  var updating = null;

  function get_thread_data() {
    // TODO: This is really, really gross.
    var infonode = JX.DOM.find(document, 'input', 'latest-transaction-id');
    var data = JX.Stratcom.getData(infonode);
    data.latestID = infonode.value;
    return data;
  }

  function update_latest_transaction_id(id) {
    // TODO: Continued grossness from above.
    var infonode = JX.DOM.find(document, 'input', 'latest-transaction-id');
    infonode.value = id;
  }

  JX.Stratcom.listen('aphlict-server-message', null, function(e) {
    var message = e.getData();

    if (message.type != 'message') {
      // Not a message event.
      return;
    }

    var data = get_thread_data();

    if (message.threadPHID != data.threadPHID) {
      // Message event for some thread other than the visible one.
      return;
    }

    if (message.messageID <= data.latestID) {
      // Message event for something we already know about.
      return;
    }

    // If we're currently updating, wait for the update to complete.
    // If this notification tells us about a message which is newer than the
    // newest one we know to exist, keep track of it so we can update once
    // the in-flight update finishes.
    if (updating && updating.threadPHID == data.threadPHID) {
      if (message.messageID > updating.knownID) {
        updating.knownID = message.messageID;
        return;
      }
    }

    update_thread(data);
  });

  function update_thread(data) {
    var params = {
      action: 'load',
      latest_transaction_id: data.latestID
    };

    var uri = '/conpherence/update/' + data.threadID + '/';

    var workflow = new JX.Workflow(uri)
      .setData(params)
      .setHandler(function(r) {
        var messages = JX.DOM.find(document, 'div', 'conpherence-messages');
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;

        update_latest_transaction_id(r.latest_transaction_id);
      });

    sync_workflow(workflow, data);
  }

  function sync_workflow(workflow, data) {
    updating = {
      threadPHID: data.threadPHID,
      knownID: data.latestID
    };

    workflow.listen('finally', function() {
      var new_data = get_thread_data();
      var need_sync = (updating.knownID > new_data.latestID);

      updating = null;

      if (need_sync) {
        update_thread(new_data);
      }
    });

    workflow.start();
  }

  var onsubmit = function(e) {
    e.kill();

    var form = e.getNode('tag:form');

    var root = e.getNode('conpherence-layout');
    var messages_root = JX.DOM.find(root, 'div', 'conpherence-message-pane');
    var form_root = JX.DOM.find(root, 'div', 'conpherence-form');
    var messages = JX.DOM.find(messages_root, 'div', 'conpherence-messages');
    var fileWidget = null;
    try {
      fileWidget = JX.DOM.find(root, 'div', 'widgets-files');
    } catch (ex) {
      // Ignore; maybe no files widget
    }
    JX.DOM.alterClass(form_root, 'loading', true);

    var workflow = JX.Workflow.newFromForm(form)
      .setHandler(JX.bind(this, function(r) {
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;

        if (fileWidget) {
          JX.DOM.setContent(
            fileWidget,
            JX.$H(r.file_widget)
          );
        }

        update_latest_transaction_id(r.latest_transaction_id);

        var textarea = JX.DOM.find(form, 'textarea');
        textarea.value = '';

        JX.Stratcom.invoke(
          'conpherence-selectthread',
          null,
          { id : r.conpherence_phid + '-nav-item' }
          );

        JX.DOM.alterClass(form_root, 'loading', false);
      }));

    sync_workflow(workflow, get_thread_data());
  };

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'conpherence-pontificate',
    onsubmit);

});
