/**
 * @requires javelin-behavior javelin-stratcom javelin-dom
 * @provides javelin-behavior-aphront-form-disable-on-submit
 */

JX.behavior('aphront-form-disable-on-submit', function(config) {

  var restore = [];
  var root = null;

  JX.Stratcom.listen('submit', 'tag:form', function(e) {
    if (e.getNode('workflow')) {
      // Don't activate for forms with workflow, the workflow behavior will
      // handle it.
      return;
    }

    root = e.getNode('tag:form');
    if (root._disabled) {
      e.kill();
    }
    root._disabled = true;
    var buttons = JX.DOM.scry(root, 'button');
    for (var ii = 0; ii < buttons.length; ii++) {
      if (!buttons[ii].disabled) {
        buttons[ii].disabled = 'disabled';
        JX.DOM.alterClass(buttons[ii], 'disabled', true);
        restore.push(buttons[ii]);
      }
    }
  });

  JX.Stratcom.listen('unload', null, function(e) {
    // Reenable everything on page unload so we don't bfcache back to a page
    // that has disabled forms.
    for (var ii = 0; ii < restore.length; ii++) {
      restore[ii].disabled = '';
      JX.DOM.alterClass(restore[ii], 'disabled', false);
      root._disabled = false;
    }
    if (root) {
      delete root._disabled;
    }
    restore = [];
    root = null;
  });

});
