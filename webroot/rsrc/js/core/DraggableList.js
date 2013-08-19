/**
 * @provides phabricator-draggable-list
 * @requires javelin-install
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-util
 *           javelin-vector
 *           javelin-magical-init
 * @javelin
 */

JX.install('DraggableList', {

  construct : function(sigil, root) {
    this._sigil = sigil;
    this._root = root || document.body;

    // NOTE: Javelin does not dispatch mousemove by default.
    JX.enableDispatch(document.body, 'mousemove');

    JX.DOM.listen(this._root, 'mousedown', sigil, JX.bind(this, this._ondrag));
    JX.Stratcom.listen('mousemove', null, JX.bind(this, this._onmove));
    JX.Stratcom.listen('mouseup', null, JX.bind(this, this._ondrop));
  },

  events : [
    'didLock',
    'didUnlock',
    'shouldBeginDrag',
    'didBeginDrag',
    'didCancelDrag',
    'didEndDrag',
    'didDrop'],

  properties : {
    findItemsHandler : null
  },

  members : {
    _root : null,
    _dragging : null,
    _locked : 0,
    _origin : null,
    _target : null,
    _targets : null,
    _dimensions : null,
    _ghostHandler : null,
    _ghostNode : null,

    setGhostHandler : function(handler) {
      this._ghostHandler = handler;
      return this;
    },

    getGhostHandler : function() {
      return this._ghostHandler || JX.bind(this, this._defaultGhostHandler);
    },

    getGhostNode : function() {
      if (!this._ghostNode) {
        this._ghostNode = JX.$N('li', {className: 'drag-ghost'});
      }
      return this._ghostNode;
    },

    setGhostNode : function(node) {
      this._ghostNode = node;
      return this;
    },

    _defaultGhostHandler : function(ghost, target) {
      var parent = this._dragging.parentNode;
      if (target && target.nextSibling) {
        parent.insertBefore(ghost, target.nextSibling);
      } else if (!target && parent.firstChild) {
        parent.insertBefore(ghost, parent.firstChild);
      } else {
        parent.appendChild(ghost);
      }
    },

    findItems : function() {
      var handler = this.getFindItemsHandler();
      if (__DEV__) {
        if (!handler) {
          JX.$E('JX.Draggable.findItems(): No findItemsHandler set!');
        }
      }

      return handler();
    },

    _ondrag : function(e) {
      if (this._dragging) {
        // Don't start dragging if we're already dragging something.
        return;
      }

      if (this._locked) {
        // Don't start drag operations while locked.
        return;
      }

      if (!e.isNormalMouseEvent()) {
        // Don't start dragging for shift click, right click, etc.
        return;
      }

      if (this.invoke('shouldBeginDrag', e).getPrevented()) {
        return;
      }

      e.kill();

      this._dragging = e.getNode(this._sigil);
      this._origin = JX.$V(e);
      this._dimensions = JX.$V(this._dragging);

      var targets = [];
      var items = this.findItems();
      for (var ii = 0; ii < items.length; ii++) {
        targets.push({
          item: items[ii],
          y: JX.$V(items[ii]).y + (JX.Vector.getDim(items[ii]).y / 2)
        });
      }
      targets.sort(function(u, v) { return v.y - u.y; });
      this._targets = targets;
      this._target = null;

      if (!this.invoke('didBeginDrag', this._dragging).getPrevented()) {
        var ghost = this.getGhostNode();
        ghost.style.height = JX.Vector.getDim(this._dragging).y + 'px';
        JX.DOM.alterClass(this._dragging, 'drag-dragging', true);
      }
    },

    _onmove : function(e) {
      if (!this._dragging) {
        return;
      }

      var ghost = this.getGhostNode();
      var target = this._target;
      var targets = this._targets;
      var dragging = this._dragging;
      var origin = this._origin;

      var p = JX.$V(e);

      // Compute the size and position of the drop target indicator, because we
      // need to update our static position computations to account for it.

      var adjust_h = JX.Vector.getDim(ghost).y;
      var adjust_y = JX.$V(ghost).y;

      // Find the node we're dragging the object underneath. This is the first
      // node in the list that's above the cursor. If that node is the node
      // we're dragging or its predecessor, don't select a target, because the
      // operation would be a no-op.

      var cur_target = null;
      var trigger;
      for (var ii = 0; ii < targets.length; ii++) {

        // If the drop target indicator is above the target, we need to adjust
        // the target's trigger height down accordingly. This makes dragging
        // items down the list smoother, because the target doesn't jump to the
        // next item while the cursor is over it.

        trigger = targets[ii].y;
        if (adjust_y <= trigger) {
          trigger += adjust_h;
        }

        // If the cursor is above this target, we aren't dropping underneath it.

        if (trigger >= p.y) {
          continue;
        }

        // Don't choose the dragged row or its predecessor as targets.

        cur_target = targets[ii].item;
        if (cur_target == dragging) {
          cur_target = null;
        }
        if (targets[ii - 1] && targets[ii - 1].item == dragging) {
          cur_target = null;
        }

        break;
      }

      // If we've selected a new target, update the UI to show where we're
      // going to drop the row.

      if (cur_target != target) {

        if (target) {
          JX.DOM.remove(ghost);
        }

        if (cur_target) {
          this.getGhostHandler()(ghost, cur_target);
        }

        target = cur_target;

        if (target) {

          // If we've changed where the ghost node is, update the adjustments
          // so we accurately reflect document state when we tweak things below.
          // This avoids a flash of bad state as the mouse is dragged upward
          // across the document.

          adjust_h = JX.Vector.getDim(ghost).y;
          adjust_y = JX.$V(ghost).y;
        }
      }

      // If the drop target indicator is above the cursor in the document,
      // adjust the cursor position for the change in node document position.
      // Do this before choosing a new target to avoid a flash of nonsense.

      if (target) {
        if (adjust_y <= origin.y) {
          p.y -= adjust_h;
        }
      }

      p.x = 0;
      p.y -= origin.y;
      p.setPos(dragging);
      this._target = target;

      e.kill();
    },

    _ondrop : function(e) {
      if (!this._dragging) {
        return;
      }

      var target = this._target;
      var dragging = this._dragging;
      var ghost = this.getGhostNode();

      this._dragging = null;

      JX.$V(0, 0).setPos(dragging);

      if (target) {
        JX.DOM.remove(dragging);
        JX.DOM.replace(ghost, dragging);
        this.invoke('didDrop', dragging, target);
      } else {
        this.invoke('didCancelDrag', dragging);
      }

      if (!this.invoke('didEndDrag', dragging).getPrevented()) {
        JX.DOM.alterClass(dragging, 'drag-dragging', false);
      }

      e.kill();
    },

    lock : function() {
      this._locked++;
      if (this._locked === 1) {
        this.invoke('didLock');
      }
      return this;
    },

    unlock : function() {
      if (__DEV__) {
        if (!this._locked) {
          JX.$E("JX.Draggable.unlock(): Draggable is not locked!");
        }
      }
      this._locked--;
      if (!this._locked) {
        this.invoke('didUnlock');
      }
      return this;
    }
  }

});
