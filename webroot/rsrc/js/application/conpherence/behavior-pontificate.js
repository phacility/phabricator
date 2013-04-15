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
    var fileWidget = null;
    try {
      fileWidget = JX.DOM.find(root, 'div', 'widgets-files');
    } catch (ex) {
      // Ignore; maybe no files widget
    }

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

        var inputs = JX.DOM.scry(form, 'input');
        for (var ii = 0; ii < inputs.length; ii++) {
          if (inputs[ii].name == 'latest_transaction_id') {
            inputs[ii].value = r.latest_transaction_id;
            break;
          }
        }

        var textarea = JX.DOM.find(form, 'textarea');
        textarea.value = '';

        JX.Stratcom.invoke(
          'conpherence-selectthread',
          null,
          { id : r.conpherence_phid + '-nav-item' }
        );
      }))
    .start();
  };

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'conpherence-pontificate',
    onsubmit);

});
