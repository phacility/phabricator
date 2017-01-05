/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-dom
 *           javelin-vector
 * @provides phabricator-tooltip
 * @javelin
 */

JX.install('Tooltip', {

  statics: {
    _node: null,
    _lock: 0,

    show : function(root, scale, align, content) {
      var self = JX.Tooltip;

      if (self._lock) {
        return;
      }

      if (__DEV__) {
        switch (align) {
          case 'N':
          case 'E':
          case 'S':
          case 'W':
            break;
          default:
            JX.$E(
              'Only alignments "N" (north), "E" (east), "S" (south), ' +
              'and "W" (west) are supported.'
            );
            break;
        }
      }

      var node_inner = JX.$N(
        'div',
        { className: 'jx-tooltip-inner' },
        [
          JX.$N('div', { className: 'jx-tooltip' }, content),
          JX.$N('div', { className: 'jx-tooltip-anchor' })
        ]);

      var node = JX.$N(
        'div',
        { className: 'jx-tooltip-container' },
        node_inner);

      node.style.maxWidth  = scale + 'px';

      JX.Tooltip.hide();
      self._node = node;

      // Append the tip to the document, but offscreen, so we can measure it.
      node.style.left = '-10000px';
      document.body.appendChild(node);

      // Jump through some hoops trying to auto-position the tooltip
      var pos = self._getSmartPosition(align, root, node);
      pos.setPos(node);
    },

    _getSmartPosition: function (align, root, node) {
      var self = JX.Tooltip;

      // Figure out how to position the tooltip on screen. We will try the
      // configured aligment first.
      var try_alignments = [align];

      // If the configured alignment does not fit, we'll try the opposite
      // alignment.
      var opposites = {
        N: 'S',
        S: 'N',
        E: 'W',
        W: 'E'
      };
      try_alignments.push(opposites[align]);

      // Then we'll try the other alignments, in arbitrary order.
      for (var k in opposites) {
        try_alignments.push(k);
      }

      var use_alignment = null;
      var use_pos = null;
      for (var ii = 0; ii < try_alignments.length; ii++) {
        var try_alignment = try_alignments[ii];

        var pos = self._proposePosition(try_alignment, root, node);
        if (self.isOnScreen(pos, node)) {
          use_alignment = try_alignment;
          use_pos = pos;
          break;
        }
      }

      // If we don't come up with a good answer, default to the configured
      // alignment.
      if (use_alignment === null) {
        use_alignment = align;
        use_pos = self._proposePosition(use_alignment, root, node);
      }

      self._setAnchor(use_alignment);

      return pos;
    },

    _proposePosition: function (align, root, node) {
      var p = JX.$V(root);
      var d = JX.Vector.getDim(root);
      var n = JX.Vector.getDim(node);
      var l = 0;
      var t = 0;

      // Caculate the tip so it's nicely aligned.
      switch (align) {
        case 'N':
          l = parseInt(p.x - ((n.x - d.x) / 2), 10);
          t  = parseInt(p.y - n.y, 10);
          break;
        case 'E':
          l = parseInt(p.x + d.x, 10);
          t  = parseInt(p.y - ((n.y - d.y) / 2), 10);
          break;
        case 'S':
          l = parseInt(p.x - ((n.x - d.x) / 2), 10);
          t  = parseInt(p.y + d.y + 5, 10);
          break;
        case 'W':
          l = parseInt(p.x - n.x - 5, 10);
          t  = parseInt(p.y - ((n.y - d.y) / 2), 10);
          break;
      }

      return new JX.Vector(l, t);
    },

    isOnScreen: function (a, node) {
      var view = this._getViewBoundaries();
      var corners = this._getNodeCornerPositions(a, node);

      // Check if any of the corners are offscreen.
      for (var i = 0; i < corners.length; i++) {
        var corner = corners[i];
        if (corner.x < view.w ||
            corner.y < view.n ||
            corner.x > view.e ||
            corner.y > view.s) {
          return false;
        }
      }
      return true;
    },

    _getNodeCornerPositions: function(pos, node) {
      // Get positions of all four corners of a node.
      var n = JX.Vector.getDim(node);
      return [new JX.Vector(pos.x, pos.y),
              new JX.Vector(pos.x + n.x, pos.y),
              new JX.Vector(pos.x, pos.y + n.y),
              new JX.Vector(pos.x + n.x, pos.y + n.y)];
    },

    _getViewBoundaries: function() {
      var s = JX.Vector.getScroll();
      var v = JX.Vector.getViewport();
      var max_x = s.x + v.x;
      var max_y = s.y + v.y;

      // Even if the corner is technically on the screen, don't allow the
      // tip to display too close to the edge of the screen.
      var margin = 16;

      return {
        w: s.x + margin,
        e: max_x - margin,
        n: s.y + margin,
        s: max_y - margin
      };
    },

    _setAnchor: function (align) {
      // Orient the little tail
      JX.DOM.alterClass(this._node, 'jx-tooltip-align-' + align, true);
    },

    hide : function() {
      if (this._node) {
        JX.DOM.remove(this._node);
        this._node = null;
      }
    },

    lock: function() {
      var self = JX.Tooltip;
      self.hide();
      self._lock++;
    },

    unlock: function() {
      var self = JX.Tooltip;
      self._lock--;
    }
  }
});
