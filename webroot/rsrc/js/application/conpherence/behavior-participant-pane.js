/**
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-util
 *           phabricator-notification
 *           conpherence-thread-manager
 * @provides javelin-behavior-conpherence-participant-pane
 */

JX.behavior('conpherence-participant-pane', function() {

  /**
   * Generified adding new stuff to widgets technology!
   */
  JX.Stratcom.listen(
    ['click'],
    'conpherence-widget-adder',
    function (e) {
      e.kill();

      var threadManager = JX.ConpherenceThreadManager.getInstance();
      var href = threadManager._getUpdateURI();
      var latest_transaction_id = threadManager.getLatestTransactionID();
      var data = {
        latest_transaction_id : latest_transaction_id,
        action : 'add_person'
      };

      var workflow = new JX.Workflow(href, data)
        .setHandler(function (r) {
          var threadManager = JX.ConpherenceThreadManager.getInstance();
          threadManager.setLatestTransactionID(r.latest_transaction_id);
          var root = JX.DOM.find(document, 'div', 'conpherence-layout');
          var messages = null;
          try {
            messages = JX.DOM.find(root, 'div', 'conpherence-messages');
          } catch (ex) {
          }
          if (messages) {
            JX.DOM.appendContent(messages, JX.$H(r.transactions));
            JX.Stratcom.invoke('conpherence-redraw-thread', null, {});
          }

          try {
            var people_root = JX.DOM.find(root, 'div', 'widgets-people');
            // update the people widget
            JX.DOM.setContent(
              people_root,
              JX.$H(r.people_widget));
          } catch (ex) {
          }

        });

      threadManager.syncWorkflow(workflow, 'submit');
    }
  );

  JX.Stratcom.listen(
    ['touchstart', 'mousedown'],
    'remove-person',
    function (e) {
      var threadManager = JX.ConpherenceThreadManager.getInstance();
      var href = threadManager._getUpdateURI();
      var data = e.getNodeData('remove-person');

      // While the user is removing themselves, disable the notification
      // update behavior. If we don't do this, the user can get an error
      // when they remove themselves about permissions as the notification
      // code tries to load what jist happened.
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
