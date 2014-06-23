/**
 * @provides javelin-behavior-conpherence-pontificate
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-workflow
 *           javelin-stratcom
 */

JX.behavior('conpherence-pontificate', function() {

  JX.Stratcom.listen('aphlict-receive-message', null, function(e) {
    var message = e.getData();

    if (message.type != 'message') {
      // Not a message event.
      return;
    }

    // TODO: This is really, really gross.
    var infonode = JX.DOM.find(document, 'input', 'latest-transaction-id');
    var data = JX.Stratcom.getData(infonode);

    var latest_id = infonode.value;
    var thread_phid = data.threadPHID;
    var thread_id = data.threadID;

    if (message.threadPHID != thread_phid) {
      // Message event for some thread other than the visible one.
      return;
    }

    if (message.messageID <= latest_id) {
      // Message event for something we already know about.
      return;
    }

    var params = {
      action: 'load',
      latest_transaction_id: latest_id
    };

    new JX.Workflow('/conpherence/update/' + thread_id + '/')
      .setData(params)
      .setHandler(function(r) {
        var messages = JX.DOM.find(document, 'div', 'conpherence-messages');
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;

        // TODO: Continued grossness from above.
        infonode.value = r.latest_transaction_id;
      })
      .start();
  });


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

    JX.Workflow.newFromForm(form)
      .setHandler(JX.bind(this, function(r) {
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;

        if (fileWidget) {
          JX.DOM.setContent(
            fileWidget,
            JX.$H(r.file_widget)
          );
        }

        var latest_transaction_dom = JX.DOM.find(
          root,
          'input',
          'latest-transaction-id');
        latest_transaction_dom.value = r.latest_transaction_id;

        var textarea = JX.DOM.find(form, 'textarea');
        textarea.value = '';

        JX.Stratcom.invoke(
          'conpherence-selectthread',
          null,
          { id : r.conpherence_phid + '-nav-item' }
          );

        JX.DOM.alterClass(form_root, 'loading', false);
      }))
    .start();
  };

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'conpherence-pontificate',
    onsubmit);

});
