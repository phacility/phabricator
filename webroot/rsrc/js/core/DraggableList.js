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
    _target : null,
    _targets : null,
    _ghostHandler : null,
    _ghostNode : null,
    _group : null,
    _lastMousePosition: null,
    _frame: null,
    _clone: null,
    _offset: null,

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

      var items = handler();

      // Make sure the clone element is never included as a target.
      for (var ii = 0; ii < items.length; ii++) {
        if (items[ii] === this._clone) {
          items.splice(ii, 1);
          break;
        }
      }

      return items;
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

      var drag = e.getNode(this._sigil);

      for (var ii = 0; ii < this._group.length; ii++) {
        this._group[ii]._clearTarget();
      }

      var pos = JX.$V(drag);
      var dim = JX.Vector.getDim(drag);

      // Create and adjust the ghost nodes.
      for (var jj = 0; jj < this._group.length; jj++) {
        var ghost = this._group[jj].getGhostNode();
        ghost.style.height = dim.y + 'px';
      }

      // Here's what's going on: we're cloning the thing that's being dragged.
      // This is the "clone", stored in "this._clone". We're going to leave the
      // original where it is in the document, and put the clone at top-level
      // so it can be freely dragged around the whole document, even if it's
      // inside a container with overflow hidden.

      // Because the clone has been moved up, CSS classes which rely on some
      // parent selector won't work. Draggable objects need to pick up all of
      // their CSS properties without relying on container classes. This isn't
      // great, but leaving them where they are in the document creates a large
      // number of positioning problems with scrollable, absolute, relative,
      // or overflow hidden containers.

      // Note that we don't actually want to let the user drag it outside the
      // document. One problem is that doing so lets the user drag objects
      // infinitely far to the right by dragging them to the edge so the
      // document extends, scrolling the document, dragging them to the edge
      // of the new larger document, scrolling the document, and so on forever.

      // To prevent this, we're putting a "frame" (stored in "this._frame") at
      // top level, then putting the clone inside the frame. The frame has the
      // same size as the entire viewport, and overflow hidden, so dragging the
      // item outside the document just cuts it off.

      // Create the clone for dragging.
      var clone = drag.cloneNode(true);

      pos.setPos(clone);
      dim.setDim(clone);

      JX.DOM.alterClass(drag, 'drag-dragging', true);
      JX.DOM.alterClass(clone, 'drag-clone', true);

      var frame = JX.$N('div', {className: 'drag-frame'});
      frame.appendChild(clone);

      document.body.appendChild(frame);

      this._dragging = drag;
      this._clone = clone;
      this._frame = frame;

      var cursor = JX.$V(e);
      this._offset = new JX.Vector(pos.x - cursor.x, pos.y - cursor.y);

      this.invoke('didBeginDrag', this._dragging);
    },

    _getTargets : function() {
      if (this._targets === null) {
        var targets = [];
        var items = this.findItems();
        for (var ii = 0; ii < items.length; ii++) {
          var item = items[ii];

          var ipos = JX.$V(item);

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

      // Clear the target position cache, since adding or removing ghosts
      // changes element positions.
      this._dirtyTargetCache();

      return this;
    },

    _getCurrentTarget : function(p) {
      var ghost = this.getGhostNode();
      var targets = this._getTargets();
      var dragging = this._dragging;

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
        trigger = targets[ii].y;

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
          if (cur_target === dragging) {
            cur_target = false;
          }
          if (targets[ii - 1] && (targets[ii - 1].item === dragging)) {
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

      var f = JX.$V(this._frame);
      p.x -= f.x;
      p.y -= f.y;

      p.y += this._offset.y;
      this._clone.style.top = p.y + 'px';

      if (this._canDragX()) {
        p.x += this._offset.x;
        this._clone.style.left = p.x + 'px';
      }

      e.kill();
    },

    _ondrop : function(e) {
      if (!this._dragging) {
        return;
      }

      var p = JX.$V(e);

      var dragging = this._dragging;
      this._dragging = null;

      JX.DOM.remove(this._frame);
      this._frame = null;
      this._clone = null;

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
