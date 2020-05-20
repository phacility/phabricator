/**
 * @provides javelin-behavior-phabricator-remarkup-assist
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           phabricator-phtize
 *           phabricator-textareautils
 *           javelin-workflow
 *           javelin-vector
 *           phuix-autocomplete
 *           javelin-mask
 */

JX.behavior('phabricator-remarkup-assist', function(config) {
  var pht = JX.phtize(config.pht);
  var root = JX.$(config.rootID);
  var area = JX.DOM.find(root, 'textarea');

  var edit_mode = 'normal';
  var edit_root = null;
  var preview = null;
  var pinned = false;

  // When we pin the comment area to the bottom of the window, we need to put
  // an extra spacer element at the bottom of the document so that it is
  // possible to scroll down far enough to see content at the end. Otherwise,
  // the last part of the document will be hidden behind the comment area when
  // the document is fully scrolled.
  var pinned_spacer = JX.$N(
    'div',
    {className: 'remarkup-assist-pinned-spacer'});

  function set_edit_mode(root, mode) {
    if (mode == edit_mode) {
      return;
    }

    // First, disable any active mode.
    if (edit_root) {
      if (edit_mode == 'fullscreen') {
        JX.DOM.alterClass(edit_root, 'remarkup-control-fullscreen-mode', false);
        JX.DOM.alterClass(document.body, 'remarkup-fullscreen-mode', false);
        JX.Mask.hide('jx-light-mask');
      }

      area.style.height = '';

      // If we're in preview mode, kick the preview back down to default
      // size.
      if (preview) {
        JX.DOM.show(area);
        resize_preview();
        JX.DOM.hide(area);
      }
    }

    edit_root = root;
    edit_mode = mode;

    // Now, apply the new mode.
    if (mode == 'fullscreen') {
      JX.DOM.alterClass(edit_root, 'remarkup-control-fullscreen-mode', true);
      JX.DOM.alterClass(document.body, 'remarkup-fullscreen-mode', true);
      JX.Mask.show('jx-light-mask');

      // If we're in preview mode, expand the preview to full-size.
      if (preview) {
        JX.DOM.show(area);
      }

      resizearea();

      if (preview) {
        resize_preview();
        JX.DOM.hide(area);
      }
    }

    JX.DOM.focus(area);
  }

  function set_pinned_mode(root, mode) {
    if (mode === pinned) {
      return;
    }

    pinned = mode;

    var container = get_pinned_container(root);
    JX.DOM.alterClass(container, 'remarkup-assist-pinned', pinned);

    if (pinned) {
      JX.DOM.appendContent(document.body, pinned_spacer);
    } else {
      JX.DOM.remove(pinned_spacer);
    }

    resizearea();

    JX.DOM.focus(area);
  }

  function get_pinned_container(root) {
    return JX.DOM.findAbove(root, 'div', 'phui-comment-form');
  }

  function resizearea() {
    // If we're in the pinned comment mode, resize the pinned spacer to be the
    // same size as the pinned form. This allows users to scroll to the bottom
    // of the document by creating extra footer space to scroll through.
    if (pinned) {
      var container = get_pinned_container(root);
      var d = JX.Vector.getDim(container);
      d.x = null;
      d.setDim(pinned_spacer);
    }

    if (!edit_root) {
      return;
    }
    if (edit_mode != 'fullscreen') {
      return;
    }

    // In Firefox, a textarea with position "absolute" or "fixed", anchored
    // "top" and "bottom", and height "auto" renders as two lines high. Force
    // it to the correct height with Javascript.

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
    set_pinned_mode(root, false);
  });

  function update(area, l, m, r) {
    // Replace the selection with the entire assisted text.
    JX.TextAreaUtils.setSelectionText(area, l + m + r, true);

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

    if (ch === '>') {
      for(var i=0; i < sel.length; i++) {
        if (sel[i][0] === '>') {
          ch = '>';
        } else {
          ch = '> ';
        }
        sel[i] = ch + sel[i];
      }
      return sel.join('\n');
    }

    return sel.join('\n' + ch);
  }

  function assist(area, action, root, button) {
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
        ch = '>';
        sel = prepend_char_to_lines(ch, sel, pht('Quoted Text'));
        update(area, ((r.start === 0) ? '' : '\n\n'), sel, '\n\n');
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
        new JX.Workflow('/file/uploaddialog/')
          .setHandler(function(response) {
            var files = response.files;
            for (var ii = 0; ii < files.length; ii++) {
              var file = files[ii];

              var upload = new JX.PhabricatorFileUpload()
                .setID(file.id)
                .setPHID(file.phid)
                .setURI(file.uri);

              JX.TextAreaUtils.insertFileReference(area, upload);
            }
          })
          .start();
        break;
      case 'fa-arrows-alt':
        set_pinned_mode(root, false);
        if (edit_mode == 'fullscreen') {
          set_edit_mode(root, 'normal');
        } else {
          set_edit_mode(root, 'fullscreen');
        }
        break;
      case 'fa-eye':
        if (!preview) {
          preview = JX.$N(
            'div',
            {
              className: 'remarkup-inline-preview'
            },
            null);

          area.parentNode.insertBefore(preview, area);
          JX.DOM.alterClass(button, 'preview-active', true);
          JX.DOM.alterClass(root, 'remarkup-preview-active', true);
          resize_preview();
          JX.DOM.hide(area);

          update_preview();
        } else {
          JX.DOM.show(area);
          resize_preview(true);
          JX.DOM.remove(preview);
          preview = null;

          JX.DOM.alterClass(button, 'preview-active', false);
          JX.DOM.alterClass(root, 'remarkup-preview-active', false);
        }
        break;
      case 'fa-thumb-tack':
        // If we're pinning, kick us out of fullscreen mode first.
        set_edit_mode(edit_root, 'normal');

        // Now pin or unpin the area.
        set_pinned_mode(root, !pinned);
        break;

    }
  }

  function resize_preview(restore) {
    if (!preview) {
      return;
    }

    var src;
    var dst;

    if (restore) {
      src = preview;
      dst = area;
    } else {
      src = area;
      dst = preview;
    }

    var d = JX.Vector.getDim(src);
    d.x = null;
    d.setDim(dst);
  }

  function update_preview() {
    var value = area.value;

    var data = {
      text: value
    };

    var onupdate = function(r) {
      if (area.value !== value) {
        return;
      }

      if (!preview) {
        return;
      }

      JX.DOM.setContent(preview, JX.$H(r.content).getFragment());
    };

    new JX.Workflow('/transactions/remarkuppreview/', data)
      .setHandler(onupdate)
      .start();
  }

  JX.DOM.listen(
    root,
    'click',
    'remarkup-assist',
    function(e) {
      var data = e.getNodeData('remarkup-assist');
      if (!data.action) {
        return;
      }

      e.kill();

      if (config.disabled) {
        return;
      }

      assist(area, data.action, root, e.getNode('remarkup-assist'));
    });

  var autocomplete = new JX.PHUIXAutocomplete()
    .setArea(area);

  for (var k in config.autocompleteMap) {
    autocomplete.addAutocomplete(k, config.autocompleteMap[k]);
  }

  autocomplete.start();

  if (config.canPin) {
    new JX.KeyboardShortcut('z', pht('key-help'))
      .setGroup('xactions')
      .setHandler(function() {
        set_pinned_mode(root, !pinned);
      })
      .register();
  }

  if (config.sendOnEnter) {
    // Send on enter if the shift key is not held.
    JX.DOM.listen(area, 'keydown', null,
      function(e) {
        if (e.getSpecialKey() != 'return') {
          return;
        }

        // Let other listeners (particularly the inline autocomplete) have a
        // chance to handle this event.
        if (JX.Stratcom.pass()) {
          return;
        }

        var raw = e.getRawEvent();
        if (raw.shiftKey) {
          // If the shift key is pressed, let the browser write a newline into
          // the textarea.
          return;
        }

        if (edit_mode == 'fullscreen') {
          // Don't send on enter in fullscreen
          return;
        }

        // From here on, interpret this as a "send" action, not a literal
        // newline.
        e.kill();

        // This allows 'workflow' and similar actions to take effect.
        // Such as pontificate in Conpherence
        var form = e.getNode('tag:form');
        JX.DOM.invoke(form, 'didSyntheticSubmit');
      });
  }

});
