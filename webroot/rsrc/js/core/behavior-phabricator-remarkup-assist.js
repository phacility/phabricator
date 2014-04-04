/**
 * @provides javelin-behavior-phabricator-remarkup-assist
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           phabricator-phtize
 *           phabricator-textareautils
 *           javelin-workflow
 *           javelin-vector
 */

JX.behavior('phabricator-remarkup-assist', function(config) {
  var pht = JX.phtize(config.pht);

  var edit_mode = 'normal';
  var edit_root = null;

  function set_edit_mode(root, mode) {
    if (mode == edit_mode) {
      return;
    }

    // First, disable any active mode.
    if (edit_root) {
      if (edit_mode == 'fullscreen') {
        JX.DOM.alterClass(edit_root, 'remarkup-control-fullscreen-mode', false);
        JX.DOM.alterClass(document.body, 'remarkup-fullscreen-mode', false);
      }
      JX.DOM.find(edit_root, 'textarea').style.height = '';
    }

    edit_root = root;
    edit_mode = mode;

    // Now, apply the new mode.
    if (mode == 'fullscreen') {
      JX.DOM.alterClass(edit_root, 'remarkup-control-fullscreen-mode', true);
      JX.DOM.alterClass(document.body, 'remarkup-fullscreen-mode', true);
      resizearea();
    }

    JX.DOM.focus(JX.DOM.find(edit_root, 'textarea'));
  }

  function resizearea() {
    if (!edit_root) {
      return;
    }
    if (edit_mode != 'fullscreen') {
      return;
    }

    // In Firefox, a textarea with position "absolute" or "fixed", anchored
    // "top" and "bottom", and height "auto" renders as two lines high. Force
    // it to the correct height with Javascript.

    var area = JX.DOM.find(edit_root, 'textarea');

    var v = JX.Vector.getViewport();
    v.x = null;
    v.y -= 26;

    v.setDim(area);
  }

  JX.Stratcom.listen('resize', null, resizearea);


  JX.Stratcom.listen('keydown', null, function(e) {
    if (e.getSpecialKey() != 'esc') {
      return;
    }

    if (edit_mode != 'fullscreen') {
      return;
    }

    e.kill();
    set_edit_mode(edit_root, 'normal');
  });

  function update(area, l, m, r) {
    // Replace the selection with the entire assisted text.
    JX.TextAreaUtils.setSelectionText(area, l + m + r);

    // Now, select just the middle part. For instance, if the user clicked
    // "B" to create bold text, we insert '**bold**' but just select the word
    // "bold" so if they type stuff they'll be editing the bold text.
    var range = JX.TextAreaUtils.getSelectionRange(area);
    JX.TextAreaUtils.setSelectionRange(
      area,
      range.start + l.length,
      range.start + l.length + m.length);
  }

  function assist(area, action, root) {
    // If the user has some text selected, we'll try to use that (for example,
    // if they have a word selected and want to bold it). Otherwise we'll insert
    // generic text.
    var sel = JX.TextAreaUtils.getSelectionText(area);
    var r = JX.TextAreaUtils.getSelectionRange(area);

    switch (action) {
      case 'b':
        update(area, '**', sel || pht('bold text'), '**');
        break;
      case 'i':
        update(area, '//', sel || pht('italic text'), '//');
        break;
      case 'link':
        var name = pht('name');
        if (/^https?:/i.test(sel)) {
          update(area, '[[ ' + sel + ' | ', name, ' ]]');
        } else {
          update(area, '[[ ', pht('URL'), ' | ' + (sel || name) + ' ]]');
        }
        break;
      case 'tt':
        update(area, '`', sel || pht('monospaced text'), '`');
        break;
      case 'ul':
      case 'ol':
        var ch = (action == 'ol') ? '  # ' : '  - ';
        if (sel) {
          sel = sel.split("\n");
        } else {
          sel = [pht('List Item')];
        }
        sel = sel.join("\n" + ch);
        update(area, ((r.start === 0) ? "" : "\n\n") + ch, sel, "\n\n");
        break;
      case 'code':
        sel = sel || "foreach ($list as $item) {\n  work_miracles($item);\n}";
        sel = sel.split("\n");
        sel = "  " + sel.join("\n  ");
        update(area, ((r.start === 0) ? "" : "\n\n"), sel, "\n\n");
        break;
      case 'table':
        var prefix = (r.start === 0 ? '' : '\n\n');
        update(area, prefix + '| ', sel || pht('data'), ' |');
        break;
      case 'meme':
        new JX.Workflow('/macro/meme/create/')
          .setHandler(function(response) {
            update(
              area,
              '',
              sel,
              (r.start === 0 ? '' : '\n\n') + response.text + '\n\n');
          })
          .start();
        break;
      case 'image':
        new JX.Workflow('/file/uploaddialog/').start();
        break;
      case 'fullscreen':
        if (edit_mode == 'fullscreen') {
          set_edit_mode(root, 'normal');
        } else {
          set_edit_mode(root, 'fullscreen');
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
