/**
 * @requires javelin-behavior javelin-stratcom javelin-dom
 * @provides javelin-behavior-aphront-form-disable-on-submit
 */

JX.behavior('aphront-form-disable-on-submit', function() {

  var restore = [];
  var root = null;
  var new_tab = false;

  JX.Stratcom.listen('click', 'tag:button', function(e) {
    var raw = e.getRawEvent();
    new_tab = (raw.altKey || raw.ctrlKey || raw.metaKey || raw.shiftKey);
  });


  JX.Stratcom.listen('keydown', ['tag:form', 'tag:textarea'], function(e) {
    var raw = e.getRawEvent();
    if (!(e.getSpecialKey() === 'return' && (raw.ctrlKey || raw.metaKey))) {
      return;
    }

    e.kill();

    var form = e.getNode('tag:form');

    // This allows 'workflow' and similar actions to take effect.
    var r = JX.DOM.invoke(form, 'didSyntheticSubmit');
    if (r.getPrevented()) {
      return;
    }

    // Don't double-submit forms.
    if (form._disabled) {
      return;
    }

    will_submit(form);

    // If nothing handled the synthetic submit, submit normally.
    form.submit();
  });

  function will_submit(root) {
    root._disabled = true;
    var buttons = JX.DOM.scry(root, 'button');
    for (var ii = 0; ii < buttons.length; ii++) {
      if (!buttons[ii].disabled) {
        buttons[ii].disabled = 'disabled';
        JX.DOM.alterClass(buttons[ii], 'disabled', true);
        restore.push(buttons[ii]);
      }
    }
  }

  JX.Stratcom.listen('submit', 'tag:form', function(e) {
    if (e.getNode('workflow')) {
      // Don't activate for forms with workflow, the workflow behavior will
      // handle it.
      return;
    }

    root = e.getNode('tag:form');

    // If the form is a "download" form, don't disable it on submit because
    // we won't transition off the page.
    if (JX.Stratcom.hasSigil(root, 'download')) {
      return;
    }

    // Open the form to a new tab in Firefox explicitly (automatic in Chrome).
    // We render some buttons as links so users may be confused that the links
    // don't open to new tabs with Ctrl+click as normal links.
    root.target = (new_tab ? '_blank' : '');
    if (new_tab) {
      return;
    }

    if (root._disabled) {
      e.kill();
    }

    will_submit(root);
  });

  JX.Stratcom.listen('unload', null, function() {
    // Re-enable everything on page unload so we don't bfcache back to a page
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
