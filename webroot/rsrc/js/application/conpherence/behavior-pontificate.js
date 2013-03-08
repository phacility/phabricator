/**
 * @provides javelin-behavior-conpherence-pontificate
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-workflow
 */

JX.behavior('conpherence-pontificate', function(config) {

  var root = JX.$(config.form_pane);

  var onsubmit = function(e) {
    e.kill();
    var form = JX.DOM.find(root, 'form');
    JX.Workflow.newFromForm(form)
      .setHandler(JX.bind(this, function(r) {
        // add the new transactions, probably just our post but who knows
        var messages = JX.$(config.messages);
        JX.DOM.appendContent(messages, JX.$H(r.transactions));
        messages.scrollTop = messages.scrollHeight;

        // update the menu entry as well
        JX.DOM.replace(
          JX.$(r.conpherence_phid + '-nav-item'),
          JX.$H(r.nav_item)
        );
        JX.DOM.replace(
          JX.$(r.conpherence_phid + '-menu-item'),
          JX.$H(r.menu_item)
        );

        // update the header
        JX.DOM.setContent(
          JX.$(config.header),
          JX.$H(r.header)
        );

        // update the file widget
        JX.DOM.setContent(
          JX.$(config.file_widget),
          JX.$H(r.file_widget)
        );

        // clear the textarea
        var textarea = JX.DOM.find(form, 'textarea');
        textarea.value = '';

      }))
    .start();
  };

  JX.DOM.listen(
    root,
    ['submit', 'didSyntheticSubmit'],
    null,
    onsubmit
  );

  JX.DOM.listen(
    root,
    ['click'],
    'conpherence-pontificate',
    onsubmit
  );

});
