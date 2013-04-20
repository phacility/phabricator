/**
 * @provides javelin-behavior-phabricator-remarkup-assist
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           phabricator-textareautils
 *           javelin-workflow
 *           phabricator-notification
 */

JX.behavior('phabricator-remarkup-assist', function(config) {

  var edit_mode = 'normal';
  var edit_root = null;

  function set_edit_mode(root, mode) {
    if (mode == edit_mode) {
      return;
    }

    // First, disable any active mode.
    if (edit_mode == 'order') {
      JX.DOM.alterClass(edit_root, 'remarkup-control-order-mode', false);
    }
    if (edit_mode == 'chaos') {
      JX.DOM.alterClass(edit_root, 'remarkup-control-chaos-mode', false);
    }

    edit_root = root;
    edit_mode = mode;

    // Now, apply the new mode.
    if (mode == 'order') {
      JX.DOM.alterClass(edit_root, 'remarkup-control-order-mode', true);
    }

    if (mode == 'chaos') {
      JX.DOM.alterClass(edit_root, 'remarkup-control-chaos-mode', true);
    }

    JX.DOM.focus(JX.DOM.find(edit_root, 'textarea'));
  }

  JX.Stratcom.listen('keydown', null, function(e) {
    cause_chaos();

    if (e.getSpecialKey() != 'esc') {
      return;
    }

    if (edit_mode != 'order') {
      return;
    }

    e.kill();
    set_edit_mode(edit_root, 'normal');
  });

  var chaos_states = [];
  function cause_chaos() {
    for (var ii = 0; ii <= 13; ii++) {
      if (Math.random() > 0.98) {
        chaos_states[ii] = !chaos_states[ii];
      }
      JX.DOM.alterClass(
        edit_root,
        'remarkup-control-chaos-mode-' + ii,
        !!chaos_states[ii]);
    }

    if (Math.random() > 0.99) {
      var n = new JX.Notification()
        .setContent("Hey, listen!")
        .setDuration(1000 + Math.random() * 6000);

      if (Math.random() > 0.75) {
        n.alterClassName('jx-notification-alert', true);
      }

      n.show();
    }
  }

  function update(area, l, m, r) {
    // Replace the selection with the entire assisted text.
    JX.TextAreaUtils.setSelectionText(area, l + m + r);

    // Now, select just the middle part. For instance, if the user clicked
    // "B" to create bold text, we insert '**bold**' but just select the word
    // "bold" so if they type stuff they'll be editing the bold text.
    var r = JX.TextAreaUtils.getSelectionRange(area);
    JX.TextAreaUtils.setSelectionRange(
      area,
      r.start + l.length,
      r.start + l.length + m.length);
  }

  function assist(area, action, root) {
    // If the user has some text selected, we'll try to use that (for example,
    // if they have a word selected and want to bold it). Otherwise we'll insert
    // generic text.
    var sel = JX.TextAreaUtils.getSelectionText(area);
    var r = JX.TextAreaUtils.getSelectionRange(area);

    switch (action) {
      case 'b':
        update(area, '**', sel || 'bold text', '**');
        break;
      case 'i':
        update(area, '//', sel || 'italic text', '//');
        break;
      case 'tt':
        update(area, '`', sel || 'monospaced text', '`');
        break;
      case 'ul':
      case 'ol':
        var ch = (action == 'ol') ? '  # ' : '  - ';
        if (sel) {
          sel = sel.split("\n");
        } else {
          sel = ["List Item"];
        }
        sel = sel.join("\n" + ch);
        update(area, ((r.start == 0) ? "" : "\n\n") + ch, sel, "\n\n");
        break;
      case 'code':
        sel = sel || "foreach ($list as $item) {\n  work_miracles($item);\n}";
        sel = sel.split("\n");
        sel = "  " + sel.join("\n  ");
        update(area, ((r.start == 0) ? "" : "\n\n"), sel, "\n\n");
        break;
      case 'table':
        update(area, (r.start == 0 ? '' : '\n\n') + '| ', sel || 'data', ' |');
        break;
      case 'meme':
        new JX.Workflow('/macro/meme/create/')
          .setHandler(function(response) {
            update(
              area,
              '',
              sel,
              (r.start == 0 ? '' : '\n\n') + response.text + '\n\n');
          })
          .start();
        break;
      case 'chaos':
        if (edit_mode == 'chaos') {
          set_edit_mode(root, 'normal');
        } else {
          set_edit_mode(root, 'chaos');
        }
        break;
      case 'order':
        if (edit_mode == 'order') {
          set_edit_mode(root, 'normal');
        } else {
          set_edit_mode(root, 'order');
        }
        break;
    }
  }

  JX.Stratcom.listen(
    ['click'],
    'remarkup-assist',
    function(e) {
      var data = e.getNodeData('remarkup-assist');
      if (!data.action) {
        return;
      }

      e.kill();

      var root = e.getNode('remarkup-assist-control');
      var area = JX.DOM.find(root, 'textarea');

      assist(area, data.action, root);
    });

});
