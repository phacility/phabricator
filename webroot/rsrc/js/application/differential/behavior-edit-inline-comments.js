/**
 * @provides javelin-behavior-differential-edit-inline-comments
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-util
 *           javelin-vector
 */

JX.behavior('differential-edit-inline-comments', function(config) {

  var selecting = false;
  var reticle = JX.$N('div', {className: 'differential-reticle'});
  JX.DOM.hide(reticle);

  var origin = null;
  var target = null;
  var root   = null;
  var changeset = null;

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
  }

  function hideReticle() {
    JX.DOM.hide(reticle);
  }

  function isOnRight(node) {
    return node.parentNode.firstChild != node;
  }

  function getRowNumber(th_node) {
    try {
      return parseInt(th_node.id.match(/^C\d+[ON]L(\d+)$/)[1], 10);
    } catch (x) {
      return undefined;
    }
  }

  JX.Stratcom.listen(
    'mousedown',
    ['differential-changeset', 'tag:th'],
    function(e) {
      if (e.isRightButton() ||
          getRowNumber(e.getTarget()) === undefined) {
        return;
      }

      if (selecting) {
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
      if (e.getIsTouchEvent()) {
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
      if (!selecting) {
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

      var view = JX.DiffChangeset.getForNode(root);

      view.newInlineForRange(origin, target);

      selecting = false;
      origin = null;
      target = null;

      e.kill();
    });

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    'differential-inline-comment',
    function(e) {
      if (e.getIsTouchEvent()) {
        return;
      }
      hideReticle();
    });

});
