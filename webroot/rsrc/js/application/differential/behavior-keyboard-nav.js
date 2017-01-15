/**
 * @provides javelin-behavior-differential-keyboard-navigation
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           phabricator-keyboard-shortcut
 */

JX.behavior('differential-keyboard-navigation', function(config) {

  var cursor = -1;
  var changesets;

  var selection_begin = null;
  var selection_end = null;

  var refreshFocus = function() {};

  function init() {
    if (changesets) {
      return;
    }
    changesets = JX.DOM.scry(document.body, 'div', 'differential-changeset');
  }

  function getBlocks(cursor) {
    // TODO: This might not be terribly fast; we can't currently memoize it
    // because it can change as ajax requests come in (e.g., content loads).

    var rows = JX.DOM.scry(changesets[cursor], 'tr');
    var blocks = [[changesets[cursor], changesets[cursor]]];
    var start = null;
    var type;
    var ii;

    // Don't show code blocks inside a collapsed file.
    var diff = JX.DOM.scry(changesets[cursor], 'table', 'differential-diff');
    if (diff.length == 1 && JX.Stratcom.getData(diff[0]).hidden) {
      return blocks;
    }

    function push() {
      if (start) {
        blocks.push([start, rows[ii - 1]]);
      }
      start = null;
    }

    for (ii = 0; ii < rows.length; ii++) {
      type = getRowType(rows[ii]);
      if (type == 'comment') {
        // If we see these types of rows, make a block for each one.
        push();
      }
      if (!type) {
        push();
      } else if (type && !start) {
        start = rows[ii];
      }
    }
    push();

    return blocks;
  }

  function getRowType(row) {
    // NOTE: Being somewhat over-general here to allow other types of objects
    // to be easily focused in the future (inline comments, 'show more..').

    if (row.className.indexOf('inline') !== -1) {
      return 'comment';
    }

    if (row.className.indexOf('differential-changeset') !== -1) {
      return 'file';
    }

    var cells = JX.DOM.scry(row, 'td');

    for (var ii = 0; ii < cells.length; ii++) {
      // NOTE: The semantic use of classnames here is for performance; don't
      // emulate this elsewhere since it's super terrible.
      if (cells[ii].className.indexOf('old') !== -1 ||
          cells[ii].className.indexOf('new') !== -1) {
        return 'change';
      }
    }

    return null;
  }

  function jump(manager, delta, jump_to_type) {
    init();

    if (cursor < 0) {
      if (delta < 0) {
        // If the user goes "back" without a selection, just reject the action.
        return;
      } else {
        cursor = 0;
      }
    }

    while (true) {
      var blocks = getBlocks(cursor);
      var focus;
      if (delta < 0) {
        focus = blocks.length;
      } else {
        focus = -1;
      }

      for (var ii = 0; ii < blocks.length; ii++) {
        if (blocks[ii][0] == selection_begin) {
          focus = ii;
          break;
        }
      }

      while (true) {
        focus += delta;

        if (blocks[focus]) {
          var row_type = getRowType(blocks[focus][0]);
          if (jump_to_type && row_type != jump_to_type) {
            continue;
          }

          selection_begin = blocks[focus][0];
          selection_end = blocks[focus][1];

          manager.scrollTo(selection_begin);

          refreshFocus = function() {
            manager.focusOn(selection_begin, selection_end);
          };

          refreshFocus();

          return;
        } else {
          var adjusted = (cursor + delta);
          if (adjusted < 0 || adjusted >= changesets.length) {
            // Stop cursor movement when the user reaches either end.
            return;
          }
          cursor = adjusted;

          // Break the inner loop and go to the next file.
          break;
        }
      }
    }

  }

  // When inline comments are updated, wipe out our cache of blocks since
  // comments may have been added or deleted.
  JX.Stratcom.listen(
    null,
    'differential-inline-comment-update',
    function() {
      changesets = null;
    });
  // Same thing when a file is hidden or shown; don't want to highlight
  // invisible code.
  JX.Stratcom.listen(
    'differential-toggle-file-toggled',
    null,
    function() {
      changesets = null;
      init();
      refreshFocus();
    });

  new JX.KeyboardShortcut('j', 'Jump to next change.')
    .setHandler(function(manager) {
      jump(manager, 1);
    })
    .register();

  new JX.KeyboardShortcut('k', 'Jump to previous change.')
    .setHandler(function(manager) {
      jump(manager, -1);
    })
    .register();

  new JX.KeyboardShortcut('J', 'Jump to next file.')
    .setHandler(function(manager) {
      jump(manager, 1, 'file');
    })
    .register();

  new JX.KeyboardShortcut('K', 'Jump to previous file.')
    .setHandler(function(manager) {
      jump(manager, -1, 'file');
    })
    .register();

  new JX.KeyboardShortcut('n', 'Jump to next inline comment.')
    .setHandler(function(manager) {
      jump(manager, 1, 'comment');
    })
    .register();

  new JX.KeyboardShortcut('p', 'Jump to previous inline comment.')
    .setHandler(function(manager) {
      jump(manager, -1, 'comment');
    })
    .register();


  new JX.KeyboardShortcut('t', 'Jump to the table of contents.')
    .setHandler(function(manager) {
      var toc = JX.$('toc');
      manager.scrollTo(toc);
    })
    .register();

  new JX.KeyboardShortcut(
    'h',
    'Collapse or expand the file display (after jump).')
    .setHandler(function() {
      if (!changesets || !changesets[cursor]) {
        return;
      }
      JX.Stratcom.invoke('differential-toggle-file', null, {
        diff: JX.DOM.scry(changesets[cursor], 'table', 'differential-diff')
      });
    })
    .register();


  function inline_op(node, op) {
    // nothing selected
    if (!node) {
      return;
    }
    if (!JX.DOM.scry(node, 'a', 'differential-inline-' + op)) {
      // No link for this operation, e.g. editing a comment you can't edit.
      return;
    }

    var data = {
      node: JX.DOM.find(node, 'div', 'differential-inline-comment'),
      op: op
    };

    JX.Stratcom.invoke('differential-inline-action', null, data);
  }

  new JX.KeyboardShortcut('r', 'Reply to selected inline comment.')
    .setHandler(function() {
      inline_op(selection_begin, 'reply');
    })
    .register();

  new JX.KeyboardShortcut('e', 'Edit selected inline comment.')
    .setHandler(function() {
      inline_op(selection_begin, 'edit');
    })
    .register();

});
