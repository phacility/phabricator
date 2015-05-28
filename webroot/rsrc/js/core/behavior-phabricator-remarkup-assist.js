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
      if (edit_mode == 'fa-arrows-alt') {
        JX.DOM.alterClass(edit_root, 'remarkup-control-fullscreen-mode', false);
        JX.DOM.alterClass(document.body, 'remarkup-fullscreen-mode', false);
      }
      JX.DOM.find(edit_root, 'textarea').style.height = '';
    }

    edit_root = root;
    edit_mode = mode;

    // Now, apply the new mode.
    if (mode == 'fa-arrows-alt') {
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
    if (edit_mode != 'fa-arrows-alt') {
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

    if (edit_mode != 'fa-arrows-alt') {
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

  function prepend_char_to_lines(ch, sel, def) {
    if (sel) {
      sel = sel.split('\n');
    } else {
      sel = [def];
    }
    sel = sel.join('\n' + ch);
    return sel;
  }

  function assist(area, action, root) {
    // If the user has some text selected, we'll try to use that (for example,
    // if they have a word selected and want to bold it). Otherwise we'll insert
    // generic text.
    var sel = JX.TextAreaUtils.getSelectionText(area);
    var r = JX.TextAreaUtils.getSelectionRange(area);
    var ch;

    switch (action) {
      case 'fa-bold':
        update(area, '**', sel || pht('bold text'), '**');
        break;
      case 'fa-italic':
        update(area, '//', sel || pht('italic text'), '//');
        break;
      case 'fa-link':
        var name = pht('name');
        if (/^https?:/i.test(sel)) {
          update(area, '[[ ' + sel + ' | ', name, ' ]]');
        } else {
          update(area, '[[ ', pht('URL'), ' | ' + (sel || name) + ' ]]');
        }
        break;
      case 'fa-text-width':
        update(area, '`', sel || pht('monospaced text'), '`');
        break;
      case 'fa-list-ul':
      case 'fa-list-ol':
        ch = (action == 'fa-list-ol') ? '  # ' : '  - ';
        sel = prepend_char_to_lines(ch, sel, pht('List Item'));
        update(area, ((r.start === 0) ? '' : '\n\n') + ch, sel, '\n\n');
        break;
      case 'fa-code':
        sel = sel || 'foreach ($list as $item) {\n  work_miracles($item);\n}';
        var code_prefix = (r.start === 0) ? '' : '\n';
        update(area, code_prefix + '```\n', sel, '\n```');
        break;
      case 'fa-quote-right':
        ch = '> ';
        sel = prepend_char_to_lines(ch, sel, pht('Quoted Text'));
        update(area, ((r.start === 0) ? '' : '\n\n') + ch, sel, '\n\n');
        break;
      case 'fa-table':
        var table_prefix = (r.start === 0 ? '' : '\n\n');
        update(area, table_prefix + '| ', sel || pht('data'), ' |');
        break;
      case 'fa-meh-o':
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
      case 'fa-cloud-upload':
        new JX.Workflow('/file/uploaddialog/').start();
        break;
      case 'fa-arrows-alt':
        if (edit_mode == 'fa-arrows-alt') {
          set_edit_mode(root, 'normal');
        } else {
          set_edit_mode(root, 'fa-arrows-alt');
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
