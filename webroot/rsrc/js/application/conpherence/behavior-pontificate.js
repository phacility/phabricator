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

    var files = null;
    try {
      files = JX.DOM.find(root, 'div', 'conpherence-widget-files');
    } catch (ex) {
      // Ignore, this view may not have a Files widget.
    }

    JX.Workflow.newFromForm(form)
      .setHandler(JX.bind(this, function(r) {
        // add the new transactions, probably just our post but who knows
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;
        JX.DOM.setContent(header, JX.$H(r.header));

        try {
          JX.DOM.replace(
            JX.$(r.conpherence_phid + '-nav-item'),
            JX.$H(r.nav_item));
        } catch (ex) {
          // Ignore; this view may not have a menu.
        }

        if (files) {
          JX.DOM.setContent(files, JX.$H(r.file_widget));
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
