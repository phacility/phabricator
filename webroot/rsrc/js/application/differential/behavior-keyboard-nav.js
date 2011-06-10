/**
 * @provides javelin-behavior-differential-keyboard-navigation
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-keyboard-shortcut
 */

JX.behavior('differential-keyboard-navigation', function(config) {

  var cursor = -1;
  var cursor_block = null;
  var changesets;

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
    for (var ii = 0; ii < rows.length; ii++) {
      type = getRowType(rows[ii]);
      if (type == 'ignore') {
        continue;
      }
      if (!type && start) {
        blocks.push([start, rows[ii - 1]]);
        start = null;
      } else if (type && !start) {
        start = rows[ii];
      }
    }
    if (start) {
      blocks.push([start, rows[ii - 1]]);
    }

    return blocks;
  }

  function getRowType(row) {
    // NOTE: Being somewhat over-general here to allow other types of objects
    // to be easily focused in the future (inline comments, 'show more..').

    if (row.className.indexOf('inline') !== -1) {
      return 'ignore';
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

  function jump(manager, delta) {
    init();

    if (cursor < 0) {
      if (delta < 0) {
        // If the user goes "back" without a selection, just reject the action.
        return;
      } else {
        cursor = 0;
      }
    }

    var selected;
    var extent;
    while (true) {
      var blocks = getBlocks(cursor);
      var focus;
      if (delta < 0) {
        focus = blocks.length;
      } else {
        focus = -1;
      }

      for (var ii = 0; ii < blocks.length; ii++) {
        if (blocks[ii][0] == cursor_block) {
          focus = ii;
          break;
        }
      }

      focus += delta;

      if (blocks[focus]) {
        selected = blocks[focus][0];
        extent = blocks[focus][1];
        cursor_block = selected;
        break;
      } else {
        var adjusted = (cursor + delta);
        if (adjusted < 0 || adjusted >= changesets.length) {
          // Stop cursor movement when the user reaches either end.
          return;
        }
        cursor = adjusted;
      }
    }

    manager.scrollTo(selected);
    manager.focusOn(selected, extent);
  }

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

});

