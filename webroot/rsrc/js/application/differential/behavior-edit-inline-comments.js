/**
 * @provides javelin-behavior-differential-edit-inline-comments
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-util
 *           javelin-vector
 *           differential-inline-comment-editor
 */

JX.behavior('differential-edit-inline-comments', function(config) {

  var selecting = false;
  var reticle = JX.$N('div', {className: 'differential-reticle'});
  var old_cells = [];
  JX.DOM.hide(reticle);

  var origin = null;
  var target = null;
  var root   = null;
  var changeset = null;

  var editor = null;

  function updateReticle() {
    JX.DOM.getContentFrame().appendChild(reticle);

    var top = origin;
    var bot = target;
    if (JX.$V(top).y > JX.$V(bot).y) {
      var tmp = top;
      top = bot;
      bot = tmp;
    }

    // Find the leftmost cell that we're going to highlight: this is the next
    // <td /> in the row. In 2up views, it should be directly adjacent. In
    // 1up views, we may have to skip over the other line number column.
    var l = top;
    while (JX.DOM.isType(l, 'th')) {
      l = l.nextSibling;
    }

    // Find the rightmost cell that we're going to highlight: this is the
    // farthest consecutive, adjacent <td /> in the row. Sometimes the left
    // and right nodes are the same (left side of 2up view); sometimes we're
    // going to highlight several nodes (copy + code + coverage).
    var r = l;
    while (r.nextSibling && JX.DOM.isType(r.nextSibling, 'td')) {
      r = r.nextSibling;
    }

    var pos = JX.$V(l)
      .add(JX.Vector.getAggregateScrollForNode(l));

    var dim = JX.$V(r)
      .add(JX.Vector.getAggregateScrollForNode(r))
      .add(-pos.x, -pos.y)
      .add(JX.Vector.getDim(r));

    var bpos = JX.$V(bot)
      .add(JX.Vector.getAggregateScrollForNode(bot));
    dim.y = (bpos.y - pos.y) + JX.Vector.getDim(bot).y;

    pos.setPos(reticle);
    dim.setDim(reticle);

    JX.DOM.show(reticle);

    // Find all the cells in the same row position between the top and bottom
    // cell, so we can highlight them.
    var seq = 0;
    var row = top.parentNode;
    for (seq = 0; seq < row.childNodes.length; seq++) {
      if (row.childNodes[seq] == top) {
        break;
      }
    }

    var cells = [];
    while (true) {
      cells.push(row.childNodes[seq]);
      if (row.childNodes[seq] == bot) {
        break;
      }
      row = row.nextSibling;
    }

    setSelectedCells(cells);
  }

  function setSelectedCells(new_cells) {
    updateSelectedCellsClass(old_cells, false);
    updateSelectedCellsClass(new_cells, true);
    old_cells = new_cells;
  }

  function updateSelectedCellsClass(cells, selected) {
    for (var ii = 0; ii < cells.length; ii++) {
      JX.DOM.alterClass(cells[ii], 'selected', selected);
    }
  }

  function hideReticle() {
    JX.DOM.hide(reticle);
    setSelectedCells([]);
  }

  JX.DifferentialInlineCommentEditor.listen('done', function() {
    selecting = false;
    editor = false;
    hideReticle();
    set_link_state(false);
  });

  function isOnRight(node) {
    return node.parentNode.firstChild != node;
  }

  function isNewFile(node) {
    var data = JX.Stratcom.getData(root);
    return isOnRight(node) || (data.left != data.right);
  }

  function getRowNumber(th_node) {
    try {
      return parseInt(th_node.id.match(/^C\d+[ON]L(\d+)$/)[1], 10);
    } catch (x) {
      return undefined;
    }
  }

  var set_link_state = function(active) {
    JX.DOM.alterClass(JX.$(config.stage), 'inline-editor-active', active);
  };

  JX.Stratcom.listen(
    'mousedown',
    ['differential-changeset', 'tag:th'],
    function(e) {
      if (editor  ||
          selecting ||
          e.isRightButton() ||
          getRowNumber(e.getTarget()) === undefined) {
        return;
      }

      selecting = true;
      root = e.getNode('differential-changeset');

      origin = target = e.getTarget();

      var data = e.getNodeData('differential-changeset');
      if (isOnRight(target)) {
        changeset = data.right;
      } else {
        changeset = data.left;
      }

      updateReticle();

      e.kill();
    });

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    ['differential-changeset', 'tag:th'],
    function(e) {
      if (editor) {
        // Don't update the reticle if we're editing a comment, since this
        // would be distracting and we want to keep the lines corresponding
        // to the comment highlighted during the edit.
        return;
      }

      if (getRowNumber(e.getTarget()) === undefined) {
        // Don't update the reticle if this "<th />" doesn't correspond to a
        // line number. For instance, this may be a dead line number, like the
        // empty line numbers on the left hand side of a newly added file.
        return;
      }

      if (selecting) {
        if (isOnRight(e.getTarget()) != isOnRight(origin)) {
          // Don't update the reticle if we're selecting a line range and the
          // "<th />" under the cursor is on the wrong side of the file. You
          // can only leave inline comments on the left or right side of a
          // file, not across lines on both sides.
          return;
        }

        if (e.getNode('differential-changeset') !== root) {
          // Don't update the reticle if we're selecting a line range and
          // the "<th />" under the cursor corresponds to a different file.
          // You can only leave inline comments on lines in a single file,
          // not across multiple files.
          return;
        }
      }

      if (e.getType() == 'mouseout') {
        if (selecting) {
          // Don't hide the reticle if we're selecting, since we want to
          // keep showing the line range that will be used if the mouse is
          // released.
          return;
        }
        hideReticle();
      } else {
        target = e.getTarget();
        if (!selecting) {
          // If we're just hovering the mouse and not selecting a line range,
          // set the origin to the current row so we highlight it.
          origin = target;
        }

        updateReticle();
      }
    });

  JX.Stratcom.listen(
    'mouseup',
    null,
    function(e) {
      if (editor || !selecting) {
        return;
      }

      var o = getRowNumber(origin);
      var t = getRowNumber(target);

      var insert;
      var len;
      if (t < o) {
        len = (o - t);
        o = t;
        insert = origin.parentNode;
      } else {
        len = (t - o);
        insert = target.parentNode;
      }

      var view = JX.ChangesetViewManager.getForNode(root);

      editor = new JX.DifferentialInlineCommentEditor(config.uri)
        .setTemplates(view.getUndoTemplates())
        .setOperation('new')
        .setChangesetID(changeset)
        .setLineNumber(o)
        .setLength(len)
        .setIsNew(isNewFile(target) ? 1 : 0)
        .setOnRight(isOnRight(target) ? 1 : 0)
        .setRow(insert.nextSibling)
        .setTable(insert.parentNode)
        .setRenderer(view.getRenderer())
        .start();

      set_link_state(true);

      e.kill();
    });

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    'differential-inline-comment',
    function(e) {
      if (e.getType() == 'mouseout') {
        hideReticle();
      } else {
        root = e.getNode('differential-changeset');
        if (root) {
          var data = e.getNodeData('differential-inline-comment');
          var change = e.getNodeData('differential-changeset');

          var id_part = data.on_right ? change.right : change.left;
          var new_part = data.isNewFile ? 'N' : 'O';
          var prefix = 'C' + id_part + new_part + 'L';

          origin = JX.$(prefix + data.number);
          target = JX.$(prefix + (parseInt(data.number, 10) +
                                  parseInt(data.length, 10)));

          updateReticle();
        }
      }
    });

  var action_handler = function(op, e) {
    e.kill();

    if (editor) {
      return;
    }

    var node = e.getNode('differential-inline-comment');
    handle_inline_action(node, op);
  };

  var handle_inline_action = function(node, op) {
    var data = JX.Stratcom.getData(node);

    // If you click an action in the preview at the bottom of the page, we
    // find the corresponding node and simulate clicking that, if it's
    // present on the page. This gives the editor a more consistent view
    // of the document.
    if (JX.Stratcom.hasSigil(node, 'differential-inline-comment-preview')) {
      var nodes = JX.DOM.scry(
        JX.DOM.getContentFrame(),
        'div',
        'differential-inline-comment');

      var found = false;
      var node_data;
      for (var ii = 0; ii < nodes.length; ++ii) {
        if (nodes[ii] == node) {
          // Don't match the preview itself.
          continue;
        }
        node_data = JX.Stratcom.getData(nodes[ii]);
        if (node_data.id == data.id) {
          node = nodes[ii];
          data = node_data;
          found = true;
          break;
        }
      }

      if (!found) {
        switch (op) {
          case 'delete':
            new JX.DifferentialInlineCommentEditor(config.uri)
              .deleteByID(data.id);
            return;
        }
      }

      if (op == 'delete') {
        op = 'refdelete';
      }
    }

    if (op == 'done') {
      var checkbox = JX.DOM.find(node, 'input', 'differential-inline-done');
      new JX.DifferentialInlineCommentEditor(config.uri)
        .toggleCheckbox(data.id, checkbox);
      return;
    }

    var original = data.original;
    var reply_phid = null;
    if (op == 'reply') {
      // If the user hit "reply", the original text is empty (a new reply), not
      // the text of the comment they're replying to.
      original = '';
      reply_phid = data.phid;
    }

    var row = JX.DOM.findAbove(node, 'tr');
    var changeset_root = JX.DOM.findAbove(
      node,
      'div',
      'differential-changeset');
    var view = JX.ChangesetViewManager.getForNode(changeset_root);

    editor = new JX.DifferentialInlineCommentEditor(config.uri)
      .setTemplates(view.getUndoTemplates())
      .setOperation(op)
      .setID(data.id)
      .setChangesetID(data.changesetID)
      .setLineNumber(data.number)
      .setLength(data.length)
      .setOnRight(data.on_right)
      .setOriginalText(original)
      .setRow(row)
      .setTable(row.parentNode)
      .setReplyToCommentPHID(reply_phid)
      .setRenderer(view.getRenderer())
      .start();

    set_link_state(true);
  };

  for (var op in {'edit': 1, 'delete': 1, 'reply': 1, 'done': 1}) {
    JX.Stratcom.listen(
      'click',
      ['differential-inline-comment', 'differential-inline-' + op],
      JX.bind(null, action_handler, op));
  }

  JX.Stratcom.listen(
    'differential-inline-action',
    null,
    function(e) {
      var data = e.getData();
      handle_inline_action(data.node, data.op);
    });

});
