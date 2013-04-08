/**
 * @provides javelin-behavior-conpherence-pontificate
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-workflow
 *           javelin-stratcom
 */

JX.behavior('conpherence-pontificate', function(config) {
  var onsubmit = function(e) {
    e.kill();

    var form = e.getNode('tag:form');

    var root = e.getNode('conpherence-layout');
    var messages = JX.DOM.find(root, 'div', 'conpherence-messages');
    var header = JX.DOM.find(root, 'div', 'conpherence-header');
    var fileWidget = null;
    try {
      fileWidget = JX.DOM.find(root, 'div', 'widgets-files');
    } catch (ex) {
      // Ignore; maybe no files widget
    }

    JX.Workflow.newFromForm(form)
      .setHandler(JX.bind(this, function(r) {
        // add the new transactions, probably just our post but who knows
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;
        JX.DOM.setContent(header, JX.$H(r.header));

        try {
          var node = JX.$(r.conpherence_phid + '-nav-item');
          JX.DOM.replace(
            node,
            JX.$H(r.nav_item));
          JX.Stratcom.invoke(
            'conpherence-selectthread',
            null,
            { id : r.conpherence_phid + '-nav-item' }
          );
        } catch (ex) {
          // Ignore; this view may not have a menu.
        }

        if (fileWidget) {
          JX.DOM.setContent(
            fileWidget,
            JX.$H(r.file_widget)
          );
        }

        var inputs = JX.DOM.scry(form, 'input');
        for (var ii = 0; ii < inputs.length; ii++) {
          if (inputs[ii].name == 'latest_transaction_id') {
            inputs[ii].value = r.latest_transaction_id;
            break;
          }
        }

        var textarea = JX.DOM.find(form, 'textarea');
        textarea.value = '';
      }))
    .start();
  };

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'conpherence-pontificate',
    onsubmit);

});
