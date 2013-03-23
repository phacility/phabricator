/**
 * @provides javelin-behavior-maniphest-subpriority-editor
 * @requires javelin-behavior
 *           javelin-magical-init
 *           javelin-dom
 *           javelin-vector
 *           javelin-stratcom
 *           javelin-workflow
 */

JX.behavior('maniphest-subpriority-editor', function(config) {

  var dragging = null;
  var sending  = null;
  var origin   = null;
  var targets  = null;
  var target   = null;
  var droptarget = JX.$N('li', {className: 'maniphest-subpriority-target'});

  var ondrag = function(e) {
    if (dragging || sending) {
      return;
    }

    if (!e.isNormalMouseEvent()) {
      return;
    }

    // Can't grab onto slippery nodes.
    if (e.getNode('slippery')) {
      return;
    }

    dragging = e.getNode('maniphest-task');
    origin = JX.$V(e);

    var tasks = JX.DOM.scry(document.body, 'li', 'maniphest-task');
    var heads = JX.DOM.scry(document.body, 'h1',  'task-group');

    var nodes = tasks.concat(heads);

    targets = [];
    for (var ii = 0; ii < nodes.length; ii++) {
      targets.push({
        node: nodes[ii],
        y: JX.$V(nodes[ii]).y + (JX.Vector.getDim(nodes[ii]).y / 2)
      });
    }
    targets.sort(function(u, v) { return v.y - u.y; });

    JX.DOM.alterClass(dragging, 'maniphest-task-dragging', true);

    droptarget.style.height = JX.Vector.getDim(dragging).y + 'px';

    e.kill();
  };

  var onmove = function(e) {
    if (!dragging) {
      return;
    }

    var p = JX.$V(e);

    // Compute the size and position of the drop target indicator, because we
    // need to update our static position computations to account for it.

    var adjust_h = JX.Vector.getDim(droptarget).y;
    var adjust_y = JX.$V(droptarget).y;

    // Find the node we're dragging the task underneath. This is the first
    // node in the list that's above the cursor. If that node is the node
    // we're dragging or its predecessor, don't select a target, because the
    // operation would be a no-op.

    var cur_target = null;
    for (var ii = 0; ii < targets.length; ii++) {

      // If the drop target indicator is above the target, we need to adjust
      // the target's trigger height down accordingly. This makes dragging
      // items down the list smoother, because the target doesn't jump to the
      // next item while the cursor is over it.

      var trigger = targets[ii].y;
      if (adjust_y <= trigger) {
        trigger += adjust_h;
      }

      // If the cursor is above this target, we aren't dropping underneath it.

      if (trigger >= p.y) {
        continue;
      }

      // Don't choose the dragged row or its predecessor as targets.

      cur_target = targets[ii].node;
      if (cur_target == dragging) {
        cur_target = null;
      }
      if (targets[ii - 1] && targets[ii - 1].node == dragging) {
        cur_target = null;
      }

      break;
    }

    // If we've selected a new target, update the UI to show where we're
    // going to drop the row.

    if (cur_target != target) {

      if (target) {
        JX.DOM.remove(droptarget);
      }

      if (cur_target) {
        if (cur_target.nextSibling) {
          if (JX.DOM.isType(cur_target, 'h1')) {
            // Dropping at the beginning of a priority list.
            cur_target.nextSibling.insertBefore(
              droptarget,
              cur_target.nextSibling.firstChild);
          } else {
            // Dropping in the middle of a priority list.
            cur_target.parentNode.insertBefore(
              droptarget,
              cur_target.nextSibling);
          }
        } else {
          // Dropping at the end of a priority list.
          cur_target.parentNode.appendChild(droptarget);
        }
      }

      target = cur_target;

      if (target) {

        // If we've changed where the droptarget is, update the adjustments
        // so we accurately reflect document state when we tweak things below.
        // This avoids a flash of bad state as the mouse is dragged upward
        // across the document.

        adjust_h = JX.Vector.getDim(droptarget).y;
        adjust_y = JX.$V(droptarget).y;
      }
    }

    // If the drop target indicator is above the cursor in the document, adjust
    // the cursor position for the change in node document position. Do this
    // before choosing a new target to avoid a flash of nonsense.

    if (target) {
      if (adjust_y <= origin.y) {
        p.y -= adjust_h;
      }
    }

    p.x = 0;
    p.y -= origin.y;
    p.setPos(dragging);

    e.kill();
  };

  var ondrop = function(e) {
    if (!dragging) {
      return;
    }

    JX.DOM.alterClass(dragging, 'maniphest-task-dragging', false);
    JX.$V(0, 0).setPos(dragging);

    if (!target) {
      dragging = null;
      return;
    }

    var data = {
      task: JX.Stratcom.getData(dragging).taskID
    };

    if (JX.DOM.isType(target, 'h1')) {
      data.priority = JX.Stratcom.getData(target).priority;
    } else {
      data.after = JX.Stratcom.getData(target).taskID;
    }

    target = null;

    JX.DOM.remove(dragging);
    JX.DOM.replace(droptarget, dragging);

    sending = dragging;
    dragging = null;

    JX.DOM.alterClass(sending, 'maniphest-task-loading', true);

    var onresponse = function(r) {
      var nodes = JX.$H(r.tasks).getFragment().firstChild;
      var task = JX.DOM.find(nodes, 'li', 'maniphest-task');
      JX.DOM.replace(sending, task);

      sending = null;
    };

    new JX.Workflow(config.uri, data)
      .setHandler(onresponse)
      .start();

    e.kill();
  };

  // NOTE: Javelin does not dispatch mousemove by default.
  JX.enableDispatch(document.body, 'mousemove');

  JX.Stratcom.listen('mousedown', 'maniphest-task', ondrag);
  JX.Stratcom.listen('mousemove', null,             onmove);
  JX.Stratcom.listen('mouseup',   null,             ondrop);

});
