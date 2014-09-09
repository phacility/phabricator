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
    this._group = [this];

    // NOTE: Javelin does not dispatch mousemove by default.
    JX.enableDispatch(document.body, 'mousemove');

    JX.DOM.listen(this._root, 'mousedown', sigil, JX.bind(this, this._ondrag));
    JX.Stratcom.listen('mousemove', null, JX.bind(this, this._onmove));
    JX.Stratcom.listen('scroll', null, JX.bind(this, this._onmove));
    JX.Stratcom.listen('mouseup', null, JX.bind(this, this._ondrop));
  },

  events : [
    'didLock',
    'didUnlock',
    'shouldBeginDrag',
    'didBeginDrag',
    'didCancelDrag',
    'didEndDrag',
    'didDrop',
    'didSend',
    'didReceive'],

  properties : {
    findItemsHandler : null
  },

  members : {
    _root : null,
    _dragging : null,
    _locked : 0,
    _origin : null,
    _originScroll : null,
    _target : null,
    _targets : null,
    _dimensions : null,
    _ghostHandler : null,
    _ghostNode : null,
    _group : null,
    _lastMousePosition: null,
    _lastAdjust: null,

    getRootNode : function() {
      return this._root;
    },

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

    setGroup : function(lists) {
      var result = [];
      var need_self = true;
      for (var ii = 0; ii < lists.length; ii++) {
        if (lists[ii] == this) {
          need_self = false;
        }
        result.push(lists[ii]);
      }

      if (need_self) {
        result.push(this);
      }

      this._group = result;
      return this;
    },

    _canDragX : function() {
      return this._hasGroup();
    },

    _hasGroup : function() {
      return (this._group.length > 1);
    },

    _defaultGhostHandler : function(ghost, target) {
      var parent;

      if (!this._hasGroup()) {
        parent = this._dragging.parentNode;
      } else {
        parent = this.getRootNode();
      }

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

      if (e.getNode('tag:a')) {
        // Never start a drag if we're somewhere inside an <a> tag. This makes
        // links unclickable in Firefox.
        return;
      }

      if (JX.Stratcom.pass()) {
        // Let other handlers deal with this event before we do.
        return;
      }

      e.kill();

      this._dragging = e.getNode(this._sigil);
      this._origin = JX.$V(e);
      this._originScroll = JX.Vector.getAggregateScrollForNode(this._dragging);
      this._dimensions = JX.$V(this._dragging);

      for (var ii = 0; ii < this._group.length; ii++) {
        this._group[ii]._clearTarget();
      }

      if (!this.invoke('didBeginDrag', this._dragging).getPrevented()) {
        // Set the height of all the ghosts in the group. In the normal case,
        // this just sets this list's ghost height.
        for (var jj = 0; jj < this._group.length; jj++) {
          var ghost = this._group[jj].getGhostNode();
          ghost.style.height = JX.Vector.getDim(this._dragging).y + 'px';
        }

        JX.DOM.alterClass(this._dragging, 'drag-dragging', true);
      }
    },

    _getTargets : function() {
      if (this._targets === null) {
        var targets = [];
        var items = this.findItems();
        for (var ii = 0; ii < items.length; ii++) {
          var item = items[ii];

          var ipos = JX.$V(item);
          if (item == this._dragging) {
            // If the item we're measuring is also the item we're dragging,
            // we need to measure its position as though it was still in the
            // list, not its current position in the document (which is
            // under the cursor). To do this, adjust the measured position by
            // removing the offsets we added to put the item underneath the
            // cursor.
            if (this._lastAdjust) {
              ipos.x -= this._lastAdjust.x;
              ipos.y -= this._lastAdjust.y;
            }
          }

          targets.push({
            item: items[ii],
            y: ipos.y + (JX.Vector.getDim(items[ii]).y / 2)
          });
        }
        targets.sort(function(u, v) { return v.y - u.y; });
        this._targets = targets;
      }

      return this._targets;
    },

    _dirtyTargetCache: function() {
      if (this._hasGroup()) {
        var group = this._group;
        for (var ii = 0; ii < group.length; ii++) {
          group[ii]._targets = null;
        }
      } else {
        this._targets = null;
      }

      return this;
    },

    _getTargetList : function(p) {
      var target_list;
      if (this._hasGroup()) {
        var group = this._group;
        for (var ii = 0; ii < group.length; ii++) {
          var root = group[ii].getRootNode();
          var rp = JX.$V(root);
          var rd = JX.Vector.getDim(root);

          var is_target = false;
          if (p.x >= rp.x && p.y >= rp.y) {
            if (p.x <= (rp.x + rd.x) && p.y <= (rp.y + rd.y)) {
              is_target = true;
              target_list = group[ii];
            }
          }

          JX.DOM.alterClass(root, 'drag-target-list', is_target);
        }
      } else {
        target_list = this;
      }

      return target_list;
    },

    _setTarget : function(cur_target) {
      var ghost = this.getGhostNode();
      var target = this._target;

      if (cur_target !== target) {
        this._clearTarget();
        if (cur_target !== false) {
          var ok = this.getGhostHandler()(ghost, cur_target);
          // If the handler returns explicit `false`, prevent the drag.
          if (ok === false) {
            cur_target = false;
          }
        }

        this._target = cur_target;
      }

      return this;
    },

    _clearTarget : function() {
      var target = this._target;
      var ghost = this.getGhostNode();

      if (target !== false) {
        JX.DOM.remove(ghost);
      }

      this._target = false;
      return this;
    },

    _getCurrentTarget : function(p) {
      var ghost = this.getGhostNode();
      var targets = this._getTargets();
      var dragging = this._dragging;

      var adjust_h = JX.Vector.getDim(ghost).y;
      var adjust_y = JX.$V(ghost).y;

      // Find the node we're dragging the object underneath. This is the first
      // node in the list that's above the cursor. If that node is the node
      // we're dragging or its predecessor, don't select a target, because the
      // operation would be a no-op.

      // NOTE: When we're dragging into the first position in the list, we
      // use the target `null`. When we don't have a valid target, we use
      // the target `false`. Spooky! Magic! Anyway, `null` and `false` mean
      // completely different things.

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
        if (!dragging) {
          // If the item on the cursor isn't from this list, it can't be
          // dropped onto itself or its predecessor in this list.
        } else {
          if (cur_target == dragging) {
            cur_target = false;
          }
          if (targets[ii - 1] && targets[ii - 1].item == dragging) {
            cur_target = false;
          }
        }

        break;
      }

      // If the dragged row is the first row, don't allow it to be dragged
      // into the first position, since this operation doesn't make sense.
      if (dragging && cur_target === null) {
        var first_item = targets[targets.length - 1].item;
        if (dragging === first_item) {
          cur_target = false;
        }
      }

      return cur_target;
    },

    _onmove : function(e) {
      // We'll get a callback here for "mousemove" (and can determine the
      // location of the cursor) and also for "scroll" (and can not). If this
      // is a move, save the mouse position, so if we get a scroll next we can
      // reuse the known position.

      if (e.getType() == 'mousemove') {
        this._lastMousePosition = JX.$V(e);
      }

      if (!this._dragging) {
        return;
      }

      if (!this._lastMousePosition) {
        return;
      }

      if (e.getType() == 'scroll') {
        // If this is a scroll event, the positions of drag targets may have
        // changed.
        this._dirtyTargetCache();
      }

      var p = JX.$V(this._lastMousePosition.x, this._lastMousePosition.y);

      var group = this._group;
      var target_list = this._getTargetList(p);

      // Compute the size and position of the drop target indicator, because we
      // need to update our static position computations to account for it.

      var cur_target = false;
      if (target_list) {
        cur_target = target_list._getCurrentTarget(p);
      }

      // If we've selected a new target, update the UI to show where we're
      // going to drop the row.

      for (var ii = 0; ii < group.length; ii++) {
        if (group[ii] == target_list) {
          group[ii]._setTarget(cur_target);
        } else {
          group[ii]._clearTarget();
        }
      }

      // If the drop target indicator is above the cursor in the document,
      // adjust the cursor position for the change in node document position.
      // Do this before choosing a new target to avoid a flash of nonsense.

      var scroll = JX.Vector.getAggregateScrollForNode(this._dragging);

      var origin = {
        x: this._origin.x + (this._originScroll.x - scroll.x),
        y: this._origin.y + (this._originScroll.y - scroll.y)
      };

      var adjust_h = 0;
      var adjust_y = 0;
      if (this._target !== false) {
        var ghost = this.getGhostNode();
        adjust_h = JX.Vector.getDim(ghost).y;
        adjust_y = JX.$V(ghost).y;

        if (adjust_y <= origin.y) {
          p.y -= adjust_h;
        }
      }

      if (this._canDragX()) {
        p.x -= origin.x;
      } else {
        p.x = 0;
      }

      p.y -= origin.y;
      this._lastAdjust = new JX.Vector(p.x, p.y);
      p.setPos(this._dragging);

      e.kill();
    },

    _ondrop : function(e) {
      if (!this._dragging) {
        return;
      }

      var p = JX.$V(e);

      var dragging = this._dragging;
      this._dragging = null;

      var target = false;
      var ghost = false;

      var target_list = this._getTargetList(p);
      if (target_list) {
        target = target_list._target;
        ghost = target_list.getGhostNode();
      }

      JX.$V(0, 0).setPos(dragging);

      if (target !== false) {
        JX.DOM.remove(dragging);
        JX.DOM.replace(ghost, dragging);
        this.invoke('didSend', dragging, target_list);
        target_list.invoke('didReceive', dragging, this);
        target_list.invoke('didDrop', dragging, target, this);
      } else {
        this.invoke('didCancelDrag', dragging);
      }

      var group = this._group;
      for (var ii = 0; ii < group.length; ii++) {
        JX.DOM.alterClass(group[ii].getRootNode(), 'drag-target-list', false);
        group[ii]._clearTarget();
        group[ii]._dirtyTargetCache();
        group[ii]._lastAdjust = null;
      }

      if (!this.invoke('didEndDrag', dragging).getPrevented()) {
        JX.DOM.alterClass(dragging, 'drag-dragging', false);
      }

      e.kill();
    },

    lock : function() {
      for (var ii = 0; ii < this._group.length; ii++) {
        this._group[ii]._lock();
      }
      return this;
    },

    _lock : function() {
      this._locked++;
      if (this._locked === 1) {
        this.invoke('didLock');
      }
      return this;
    },

    unlock: function() {
      for (var ii = 0; ii < this._group.length; ii++) {
        this._group[ii]._unlock();
      }
      return this;
    },

    _unlock : function() {
      if (__DEV__) {
        if (!this._locked) {
          JX.$E('JX.Draggable.unlock(): Draggable is not locked!');
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
