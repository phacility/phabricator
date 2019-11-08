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
    JX.Stratcom.listen('keypress', null, JX.bind(this, this._onkey));
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
    findItemsHandler: null,
    compareHandler: null,
    isDropTargetHandler: null,
    canDragX: false,
    outerContainer: null,
    hasInfiniteHeight: false,
    compareOnMove: false,
    compareOnReorder: false,
    targetChangeHandler: null
  },

  members : {
    _root : null,
    _dragging : null,
    _locked : 0,
    _target : null,
    _lastTarget: null,
    _targets : null,
    _ghostHandler : null,
    _ghostNode : null,
    _group : null,
    _cursorPosition: null,
    _cursorOrigin: null,
    _cursorScroll: null,
    _frame: null,
    _clone: null,
    _offset: null,
    _autoscroll: null,
    _autoscroller: null,
    _autotimer: null,

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

      // See T13452. If this is an ungrabble part of the item, don't start a
      // drag. We use this to allow users to select text on cards.
      var target = e.getTarget();
      if (target) {
        if (JX.Stratcom.hasSigil(target, 'ungrabbable')) {
          return;
        }
      }

      if (JX.Stratcom.pass()) {
        // Let other handlers deal with this event before we do.
        return;
      }

      e.kill();

      var drag = e.getNode(this._sigil);

      this._autoscroll = {};
      this._autoscroller = setInterval(JX.bind(this, this._onautoscroll), 10);
      this._autotimer = null;

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
      JX.DOM.alterClass(document.body, 'jx-dragging', true);

      this._dragging = drag;
      this._clone = clone;
      this._frame = frame;

      var cursor = JX.$V(e);
      this._offset = new JX.Vector(pos.x - cursor.x, pos.y - cursor.y);

      JX.Tooltip.lock();

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
      var infinity;
      if (this._hasGroup()) {
        var group = this._group;
        for (var ii = 0; ii < group.length; ii++) {
          var root = group[ii].getRootNode();
          var rp = JX.$V(root);
          var rd = JX.Vector.getDim(root);

          if (group[ii].getHasInfiniteHeight()) {
            // The math doesn't work out quite right if we actually use
            // Math.Infinity, so approximate infinity as the larger of the
            // document height or viewport height.
            if (!infinity) {
              infinity = Math.max(
                JX.Vector.getViewport().y,
                JX.Vector.getDocument().y);
            }

            rp.y = 0;
            rd.y = infinity;
          }

          var is_target = false;
          if (p.x >= rp.x && p.y >= rp.y) {
            if (p.x <= (rp.x + rd.x) && p.y <= (rp.y + rd.y)) {
              is_target = true;
              target_list = group[ii];
            }
          }

          group[ii]._setIsDropTarget(is_target);
        }
      } else {
        target_list = this;
      }

      return target_list;
    },

    _getTarget: function() {
      return this._target;
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

    _didChangeTarget: function(dst_list, dst_node) {
      if (dst_node === this._lastTarget) {
        return;
      }

      this._lastTarget = dst_node;

      var handler = this.getTargetChangeHandler();
      if (handler) {
        handler(this, this._dragging, dst_list, dst_node);
      }
    },

    _setIsDropTarget: function(is_target) {
      var root = this.getRootNode();
      JX.DOM.alterClass(root, 'drag-target-list', is_target);

      var handler = this.getIsDropTargetHandler();
      if (handler) {
        handler(is_target);
      }

      return this;
    },

    _getOrderedTarget: function(src_list, src_node) {
      var targets = this._getTargets();

      // NOTE: The targets are ordered from the bottom of the column to the
      // top, so we're looking for the first node that we sort below. If we
      // don't find one, we'll sort to the head of the column.

      for (var ii = 0; ii < targets.length; ii++) {
        var target = targets[ii];
        if (this._compareTargets(src_list, src_node, target.item) > 0) {
          return target.item;
        }
      }

      return null;
    },

    _compareTargets: function(src_list, src_node, dst_node) {
      var dst_list = this;
      return this.getCompareHandler()(src_list, src_node, dst_list, dst_node);
    },

    _getCurrentTarget : function(p) {
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
        this._cursorPosition = JX.$V(e);
        this._cursorOrigin = JX.$V(e);
        this._cursorScroll = JX.Vector.getScroll();
      }

      if (!this._dragging) {
        return;
      }

      if (!this._cursorPosition) {
        return;
      }

      if (e.getType() == 'scroll') {
        // If this is a scroll event, the positions of drag targets may have
        // changed.
        this._dirtyTargetCache();

        // Correct the cursor position to account for scrolling.
        var s = JX.Vector.getScroll();
        this._cursorPosition = new JX.$V(
          this._cursorOrigin.x - (this._cursorScroll.x - s.x),
          this._cursorOrigin.y - (this._cursorScroll.y - s.y));
      }

      var p = JX.$V(this._cursorPosition.x, this._cursorPosition.y);

      var group = this._group;
      var target_list = this._getTargetList(p);

      // Compute the size and position of the drop target indicator, because we
      // need to update our static position computations to account for it.

      var compare_handler = this.getCompareHandler();

      var cur_target = false;
      if (target_list) {
        // Determine if we're going to use the compare handler or not: the
        // compare hander locks items into a specific place in the list. For
        // example, on Workboards, some operations permit the user to drag
        // items between lists, but not to reorder items within a list.

        var should_compare = false;

        var is_reorder = (target_list === this);
        var is_move = (target_list !== this);

        if (compare_handler) {
          if (is_reorder && this.getCompareOnReorder()) {
            should_compare = true;
          }
          if (is_move && this.getCompareOnMove()) {
            should_compare = true;
          }
        }

        if (should_compare) {
          cur_target = target_list._getOrderedTarget(this, this._dragging);
        } else {
          cur_target = target_list._getCurrentTarget(p);
        }
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

      this._didChangeTarget(target_list, cur_target);

      this._updateAutoscroll(this._cursorPosition);

      var f = JX.$V(this._frame);
      p.x -= f.x;
      p.y -= f.y;

      p.y += this._offset.y;
      this._clone.style.top = p.y + 'px';

      if (this.getCanDragX()) {
        p.x += this._offset.x;
        this._clone.style.left = p.x + 'px';
      }

      e.kill();
    },

    _updateAutoscroll: function(p) {
      var container = this._getScrollAnchor().parentNode;
      var autoscroll = {};

      var outer = this.getOuterContainer();

      var cpos;
      var cdim;

      while (container) {
        if (outer && (container == outer)) {
          break;
        }

        try {
          cpos = JX.Vector.getPos(container);
          cdim = JX.Vector.getDim(container);
          if (container == document.body) {
            cdim = JX.Vector.getViewport();
            cpos.x += container.scrollLeft;
            cpos.y += container.scrollTop;
          }
        } catch (ignored) {
          break;
        }

        var fuzz = 64;

        if (p.y <= cpos.y + fuzz) {
          autoscroll.up = container;
        }

        if (p.y >= cpos.y + cdim.y - fuzz) {
          autoscroll.down = container;
        }

        if (p.x <= cpos.x + fuzz) {
          autoscroll.left = container;
        }

        if (p.x >= cpos.x + cdim.x - fuzz) {
          autoscroll.right = container;
        }

        if (container == document.body) {
          break;
        }

        container = container.parentNode;
      }

      this._autoscroll = autoscroll;
    },

    _onkey: function(e) {
      // Cancel any current drag if the user presses escape.
      if (this._dragging && (e.getSpecialKey() == 'esc')) {
        e.kill();
        this._drop(null);
        return;
      }
    },

    _ondrop : function(e) {
      if (this._dragging) {
        e.kill();
      }

      var p = JX.$V(e);
      this._drop(p);
    },

    _drop: function(cursor) {
      if (!this._dragging) {
        return;
      }

      var dragging = this._dragging;
      this._dragging = null;
      clearInterval(this._autoscroller);
      this._autoscroller = null;

      JX.DOM.remove(this._frame);
      JX.DOM.alterClass(document.body, 'jx-dragging', false);
      this._frame = null;
      this._clone = null;

      var target = false;
      var ghost = false;

      if (cursor) {
        var target_list = this._getTargetList(cursor);
        if (target_list) {
          target = target_list._target;
          ghost = target_list.getGhostNode();
        }
      }

      JX.$V(0, 0).setPos(dragging);

      if (target === false) {
        this.invoke('didCancelDrag', dragging);
      } else {
        JX.DOM.remove(dragging);
        JX.DOM.replace(ghost, dragging);
        this.invoke('didSend', dragging, target_list);
        target_list.invoke('didReceive', dragging, this);
        target_list.invoke('didDrop', dragging, target, this);
      }

      var group = this._group;
      for (var ii = 0; ii < group.length; ii++) {
        group[ii]._setIsDropTarget(false);
        group[ii]._clearTarget();
      }

      this._didChangeTarget(null, null);

      JX.DOM.alterClass(dragging, 'drag-dragging', false);
      JX.Tooltip.unlock();

      this.invoke('didEndDrag', dragging);
    },

    _getScrollAnchor: function() {
      // If you drag an item from column "A" into column "B", then move the
      // mouse to the top or bottom of the screen, we need to scroll the target
      // column (column "B"), not the original column.

      var group = this._group;
      for (var ii = 0; ii < group.length; ii++) {
        var target = group[ii]._getTarget();
        if (target) {
          return group[ii]._ghostNode;
        }
      }

      return this._dragging;
    },

    _onautoscroll: function() {
      var u = this._autoscroll.up;
      var d = this._autoscroll.down;
      var l = this._autoscroll.left;
      var r = this._autoscroll.right;

      var now = +new Date();

      if (!this._autotimer) {
        this._autotimer = now;
        return;
      }

      var delta = now - this._autotimer;
      this._autotimer = now;

      var amount = 12 * (delta / 10);

      var anchor = this._getScrollAnchor();

      if (u && (u != d)) {
        this._tryScroll(anchor, u, 'scrollTop', amount);
      }

      if (d && (d != u)) {
        this._tryScroll(anchor, d, 'scrollTop', -amount);
      }

      if (l && (l != r)) {
        this._tryScroll(anchor, l, 'scrollLeft', amount);
      }

      if (r && (r != l)) {
        this._tryScroll(anchor, r, 'scrollLeft', -amount);
      }
    },

    /**
     * Walk up the tree from a node to some parent, trying to scroll every
     * container. Stop when we find a container which we're able to scroll.
     */
    _tryScroll: function(from, to, property, amount) {
      var value;

      var container = from.parentNode;
      while (container) {

        // In Safari, we'll eventually reach `window.document`, which is not
        // sufficently node-like to support sigil tests.
        var lock = false;
        if (container === window.document) {
          lock = false;
        } else {
          // Some elements may respond to, e.g., `scrollTop` adjustment, even
          // though they are not scrollable. This sigil disables adjustment
          // for them.
          var lock_sigil;
          if (property == 'scrollTop') {
            lock_sigil = 'lock-scroll-y-while-dragging';
          }

          if (lock_sigil) {
            lock = JX.Stratcom.hasSigil(container, lock_sigil);
          }
        }

        if (!lock) {
          // Read the current scroll value.
          value = container[property];

          // Try to scroll.
          container[property] -= amount;

          // If we scrolled it, we're all done.
          if (container[property] != value) {
            break;
          }

          if (container == to) {
            break;
          }
        }

        container = container.parentNode;
      }
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
