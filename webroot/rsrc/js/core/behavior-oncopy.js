/**
 * @provides javelin-behavior-phabricator-oncopy
 * @requires javelin-behavior
 *           javelin-dom
 */

JX.behavior('phabricator-oncopy', function() {
  var copy_root;
  var copy_mode;

  function onstartselect(e) {
    var target = e.getTarget();

    // See T13513. If the user selects multiple lines in a 2-up diff and then
    // clicks "New Inline Comment" in the context menu that pops up, the
    // mousedown causes us to arrive here and remove the "selectable" CSS
    // styles, and creates a flash of selected content across both sides of
    // the diff, which is distracting. To attempt to avoid this, bail out if
    // the user clicked a link.

    if (JX.DOM.isType(target, 'a')) {
      return;
    }

    var container;
    try {
      // NOTE: For now, all elements with custom oncopy behavior are tables,
      // so this tag selection will hit everything we need it to.
      container = JX.DOM.findAbove(target, 'table', 'intercept-copy');
    } catch (ex) {
      container = null;
    }

    var old_mode = copy_mode;
    clear_selection_mode();

    if (!container) {
      return;
    }

    // If the potential selection is starting inside an inline comment,
    // don't do anything special.
    try {
      if (JX.DOM.findAbove(target, 'div', 'differential-inline-comment')) {
        return;
      }
    } catch (ex) {
      // Continue.
    }

    // Find the row and cell we're copying from. If we don't find anything,
    // don't do anything special.
    var row;
    var cell;
    try {
      // The target may be the cell we're after, particularly if you click
      // in the white area to the right of the text, towards the end of a line.
      if (JX.DOM.isType(target, 'td')) {
        cell = target;
      } else {
        cell = JX.DOM.findAbove(target, 'td');
      }
      row = JX.DOM.findAbove(target, 'tr');
    } catch (ex) {
      return;
    }

    // If the row doesn't have enough nodes, bail out. Note that it's okay
    // to begin a selection in the whitespace on the opposite side of an inline
    // comment. For example, if there's an inline comment on the right side of
    // a diff, it's okay to start selecting the left side of the diff by
    // clicking the corresponding empty space on the left side.
    if (row.childNodes.length < 4) {
      return;
    }

    // If the selection's cell is in the "old" diff or the "new" diff, we'll
    // activate an appropriate copy mode.
    var mode;
    if (cell === row.childNodes[1]) {
      mode = 'copy-l';
    } else if ((row.childNodes.length >= 4) && (cell === row.childNodes[4])) {
      mode = 'copy-r';
    } else {
      return;
    }

    // We found a copy mode, so set it as the current active mode.
    copy_root = container;
    copy_mode = mode;

    // If the user makes a selection, then clicks again inside the same
    // selection, browsers retain the selection. This is because the user may
    // want to drag-and-drop the text to another window.

    // Handle special cases when the click is inside an existing selection.

    var ranges = get_selected_ranges();
    if (ranges.length) {
      // We'll have an existing selection if the user selects text on the right
      // side of a diff, then clicks the selection on the left side of the
      // diff, even if the second click is clicking part of the selection
      // range where the selection highlight is currently invisible because
      // of CSS rules.

      // This behavior looks and feels glitchy: an invisible selection range
      // suddenly pops into existence and there's a bunch of flicker. If we're
      // switching selection modes, clear the old selection to avoid this:
      // assume the user is not trying to drag-and-drop text which is not
      // visually selected.

      if (old_mode !== copy_mode) {
        window.getSelection().removeAllRanges();
      }

      // In the more mundane case, if the user selects some text on one side
      // of a diff and then clicks that same selection in a normal way (in
      // the visible part of the highlighted text), we may either be altering
      // the selection range or may be initiating a text drag depending on how
      // long they hold the button for. Regardless of what we're doing, we're
      // still in a selection mode, so keep the visual hints active.

      JX.DOM.alterClass(copy_root, copy_mode, true);
    }

    // We've chosen a mode and saved it now, but we don't actually update to
    // apply any visual changes until the user actually starts making some
    // kind of selection.
  }

  // When the selection range changes, apply CSS classes if the selection is
  // nonempty. We don't want to make visual changes to the document immediately
  // when the user presses the mouse button, since we aren't yet sure that
  // they are starting a selection: instead, wait for them to actually select
  // something.
  function onchangeselect() {
    if (!copy_mode) {
      return;
    }

    var ranges = get_selected_ranges();
    JX.DOM.alterClass(copy_root, copy_mode, !!ranges.length);
  }

  // When the user releases the mouse, get rid of the selection mode if we
  // don't have a selection.
  function onendselect(e) {
    if (!copy_mode) {
      return;
    }

    var ranges = get_selected_ranges();
    if (!ranges.length) {
      clear_selection_mode();
    }
  }

  function get_selected_ranges() {
    var ranges = [];

    if (!window.getSelection) {
      return ranges;
    }

    var selection = window.getSelection();
    for (var ii = 0; ii < selection.rangeCount; ii++) {
      var range = selection.getRangeAt(ii);
      if (range.collapsed) {
        continue;
      }

      ranges.push(range);
    }

    return ranges;
  }

  function clear_selection_mode() {
    if (!copy_root) {
      return;
    }

    JX.DOM.alterClass(copy_root, copy_mode, false);
    copy_root = null;
    copy_mode = null;
  }

  function oncopy(e) {
    // If we aren't in a special copy mode, just fall back to default
    // behavior.
    if (!copy_mode) {
      return;
    }

    var ranges = get_selected_ranges();
    if (!ranges.length) {
      return;
    }

    var text = [];
    for (var ii = 0; ii < ranges.length; ii++) {
      var range = ranges[ii];

      var fragment = range.cloneContents();
      if (!fragment.childNodes.length) {
        continue;
      }

      // In Chrome and Firefox, because we've already applied "user-select"
      // CSS to everything we don't intend to copy, the text in the selection
      // range is correct, and the range will include only the correct text
      // nodes.

      // However, in Safari, "user-select" does not apply to clipboard
      // operations, so we get everything in the document between the beginning
      // and end of the selection, even if it isn't visibly selected.

      // Even in Chrome and Firefox, we can get partial empty nodes: for
      // example, where a "<tr>" is selectable but no content in the node is
      // selectable. (We have to leave the "<tr>" itself selectable because
      // of how Firefox applies "user-select" rules.)

      // The nodes we get here can also start and end more or less anywhere.

      // One saving grace is that we use "content: attr(data-n);" to render
      // the line numbers and no browsers copy this content, so we don't have
      // to worry about figuring out when text is line numbers.

      for (var jj = 0; jj < fragment.childNodes.length; jj++) {
        var node = fragment.childNodes[jj];
        text.push(extract_text(node));
      }
    }

    text = flatten_list(text);
    text = text.join('');

    var rawEvent = e.getRawEvent();
    var data;
    if ('clipboardData' in rawEvent) {
      data = rawEvent.clipboardData;
    } else {
      data = window.clipboardData;
    }
    data.setData('Text', text);

    e.prevent();
  }

  function extract_text(node) {
    var ii;
    var text = [];

    if (JX.DOM.isType(node, 'tr')) {
      // This is an inline comment row, so we never want to copy any
      // content inside of it.
      if (JX.Stratcom.hasSigil(node, 'inline-row')) {
        return null;
      }

      // This is a "Show More Context" row, so we never want to copy any
      // of the content inside.
      if (JX.Stratcom.hasSigil(node, 'context-target')) {
        return null;
      }

      // Assume anything else is a source code row. Keep only "<td>" cells
      // with the correct mode.
      for (ii = 0; ii < node.childNodes.length; ii++) {
        text.push(extract_text(node.childNodes[ii]));
      }

      return text;
    }

    if (JX.DOM.isType(node, 'td')) {
      var node_mode = node.getAttribute('data-copy-mode');
      if (node_mode !== copy_mode) {
        return;
      }

      // Otherwise, fall through and extract this node's text normally.
    }

    if (node.getAttribute) {
      var copy_text = node.getAttribute('data-copy-text');
      if (copy_text) {
        return copy_text;
      }
    }

    if (!node.childNodes || !node.childNodes.length) {
      return node.textContent;
    }

    for (ii = 0; ii < node.childNodes.length; ii++) {
      var child = node.childNodes[ii];
      text.push(extract_text(child));
    }

    return text;
  }

  function flatten_list(list) {
    var stack = [list];
    var result = [];
    while (stack.length) {
      var next = stack.pop();
      if (JX.isArray(next)) {
        for (var ii = 0; ii < next.length; ii++) {
          stack.push(next[ii]);
        }
      } else if (next === null) {
        continue;
      } else if (next === undefined) {
        continue;
      } else {
        result.push(next);
      }
    }

    return result.reverse();
  }

  JX.enableDispatch(document.body, 'copy');
  JX.enableDispatch(window, 'selectionchange');

  JX.Stratcom.listen('mousedown', null, onstartselect);
  JX.Stratcom.listen('selectionchange', null, onchangeselect);
  JX.Stratcom.listen('mouseup', null, onendselect);

  JX.Stratcom.listen('copy', null, oncopy);
});
