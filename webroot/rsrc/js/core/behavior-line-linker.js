/**
 * @provides javelin-behavior-phabricator-line-linker
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-history
 */

JX.behavior('phabricator-line-linker', function() {
  var origin = null;
  var target = null;
  var root = null;
  var highlighted = null;

  var editor_link = null;
  try {
    editor_link = JX.$('editor_link');
  } catch (ex) {
    // Ignore.
  }

  function getRowNumber(tr) {
    var th = tr.firstChild;

    // If the "<th />" tag contains an "<a />" with "data-n" that we're using
    // to prevent copy/paste of line numbers, use that.
    if (th.firstChild) {
      var line = th.firstChild.getAttribute('data-n');
      if (line) {
        return line;
      }
    }

    return +(th.textContent || th.innerText);
  }

  JX.Stratcom.listen(
    ['click', 'mousedown'],
    ['phabricator-source', 'tag:tr', 'tag:th', 'tag:a'],
    function(e) {
      if (!e.isNormalMouseEvent()) {
        return;
      }

      // Make sure the link we clicked is actually a line number in a source
      // table, not some kind of link in some element embedded inside the
      // table. The row's immediate ancestor table needs to be the table with
      // the "phabricator-source" sigil.

      var row = e.getNode('tag:tr');
      var table = e.getNode('phabricator-source');
      if (JX.DOM.findAbove(row, 'table') !== table) {
        return;
      }

      var number = getRowNumber(row);
      if (!number) {
        return;
      }

      e.kill();

      // If this is a click event, kill it. We handle mousedown and mouseup
      // instead.
      if (e.getType() === 'click') {
        return;
      }

      origin = row;
      target = origin;

      root = table;
    });

  var highlight = function(e) {
    if (!origin) {
      return;
    }

    if (e.getNode('phabricator-source') !== root) {
      return;
    }
    target = e.getNode('tag:tr');

    var min;
    var max;

    // NOTE: We're using position to figure out which order these rows are in,
    // not row numbers. We do this because Harbormaster build logs may have
    // multiple rows with the same row number.

    if (JX.$V(origin).y <= JX.$V(target).y) {
      min = origin;
      max = target;
    } else {
      min = target;
      max = origin;
    }

    // If we haven't changed highlighting, we don't have a list of highlighted
    // nodes yet. Assume every row is highlighted.
    var ii;
    if (highlighted === null) {
      highlighted = [];
      var rows = JX.DOM.scry(root, 'tr');
      for (ii = 0; ii < rows.length; ii++) {
        highlighted.push(rows[ii]);
      }
    }

    // Unhighlight any existing highlighted rows.
    for (ii = 0; ii < highlighted.length; ii++) {
      JX.DOM.alterClass(highlighted[ii], 'phabricator-source-highlight', false);
    }
    highlighted = [];

    // Highlight the newly selected rows.
    var cursor = min;
    while (true) {
      JX.DOM.alterClass(cursor, 'phabricator-source-highlight', true);
      highlighted.push(cursor);

      if (cursor === max) {
        break;
      }

      cursor = cursor.nextSibling;
    }
  };

  JX.Stratcom.listen('mouseover', 'phabricator-source', highlight);

  JX.Stratcom.listen(
    'mouseup',
    null,
    function(e) {
      if (!origin) {
        return;
      }

      highlight(e);
      e.kill();

      var o = getRowNumber(origin);
      var t = getRowNumber(target);
      var uri = JX.Stratcom.getData(root).uri;

      origin = null;
      target = null;
      root = null;

      var lines = (o == t ? o : Math.min(o, t) + '-' + Math.max(o, t));
      uri = uri + '$' + lines;

      JX.History.replace(uri);

      if (editor_link) {
        if (editor_link.href) {
          var editdata = JX.Stratcom.getData(editor_link);
          editor_link.href = editdata.link_template.replace('%25l', o);
        }
      }
    });

});
