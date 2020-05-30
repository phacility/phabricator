/**
 * @provides javelin-behavior-phabricator-line-linker
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-history
 *           javelin-external-editor-link-engine
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

  function getRowNumber(th) {
    // If the "<th />" tag contains an "<a />" with "data-n" that we're using
    // to prevent copy/paste of line numbers, use that.
    if (th.firstChild) {
      var line = th.firstChild.getAttribute('data-n');
      if (line) {
        return line;
      }
    }

    return null;
  }

  JX.Stratcom.listen(
    ['click', 'mousedown'],
    ['phabricator-source', 'tag:th', 'tag:a'],
    function(e) {
      if (!e.isNormalMouseEvent()) {
        return;
      }

      // Make sure the link we clicked is actually a line number in a source
      // table, not some kind of link in some element embedded inside the
      // table. The row's immediate ancestor table needs to be the table with
      // the "phabricator-source" sigil.

      var cell = e.getNode('tag:th');
      var table = e.getNode('phabricator-source');
      if (JX.DOM.findAbove(cell, 'table') !== table) {
        return;
      }

      var number = getRowNumber(cell);
      if (!number) {
        return;
      }

      e.kill();

      // If this is a click event, kill it. We handle mousedown and mouseup
      // instead.
      if (e.getType() === 'click') {
        return;
      }

      origin = cell;
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
    target = e.getNode('tag:th');

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
    min = JX.DOM.findAbove(min, 'tr');
    max = JX.DOM.findAbove(max, 'tr');

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
      var path;

      if (!uri) {
        uri = JX.$U(window.location);
        path = uri.getPath();
        path = path.replace(/\$[\d-]+$/, '');
        uri.setPath(path);
        uri = uri.toString();
      }

      origin = null;
      target = null;
      root = null;

      var lines = (o == t ? o : Math.min(o, t) + '-' + Math.max(o, t));

      uri = JX.$U(uri);
      path = uri.getPath();
      path = path + '$' + lines;
      uri = uri.setPath(path).toString();

      JX.History.replace(uri);

      if (editor_link) {
        var data = JX.Stratcom.getData(editor_link);

        var variables = {
          l: parseInt(Math.min(o, t), 10),
        };

        var template = data.template;

        var editor_uri = new JX.ExternalEditorLinkEngine()
          .setTemplate(template)
          .setVariables(variables)
          .newURI();

        editor_link.href = editor_uri;
      }
    });


  // Try to jump to the highlighted lines if we don't have an explicit anchor
  // in the URI.
  if (!window.location.hash.length) {
    try {
      var anchor = JX.$('phabricator-line-linker-anchor');
      JX.DOM.scrollToPosition(0, JX.$V(anchor).y - 60);
    } catch (ex) {
      // If we didn't hit an element on the page, just move on.
    }
  }

  if (editor_link) {
    // TODO: This should be pht()'d, but this behavior is weird enough to
    // make that a bit tricky.

    new JX.KeyboardShortcut('\\', 'Open File in External Editor')
        .setGroup('diff-nav')
        .setHandler(function() {
          JX.$U(editor_link.href).go();
        })
        .register();
  }

});
