/**
 * @provides phabricator-scroll-objective-list
 * @requires javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-install
 *           javelin-workflow
 *           javelin-scrollbar
 *           phabricator-scroll-objective
 * @javelin
 */


JX.install('ScrollObjectiveList', {

  construct : function() {
    this._objectives = [];

    var onresize = JX.bind(this, this._dirty);
    JX.Stratcom.listen('resize', null, onresize);
  },

  members: {
    _objectives: null,
    _visible: false,
    _trigger: null,

    newObjective: function() {
      var objective = new JX.ScrollObjective()
        .setObjectiveList(this);

      this._objectives.push(objective);
      this._getNode().appendChild(objective.getNode());

      this._dirty();

      return objective;
    },

    show: function() {
      this._visible = true;
      this._dirty();
      return this;
    },

    hide: function() {
      this._visible = false;
      this._dirty();
      return this;
    },

    _getNode: function() {
      if (!this._node) {
        var node = new JX.$N('div', {className: 'scroll-objective-list'});
        this._node = node;
      }
      return this._node;
    },

    _dirty: function() {
      if (this._trigger !== null) {
        return;
      }

      this._trigger = setTimeout(JX.bind(this, this._redraw), 0);
    },

    _redraw: function() {
      this._trigger = null;

      var node = this._getNode();

      var is_visible =
        (this._visible) &&
        (JX.Device.getDevice() == 'desktop') &&
        (this._objectives.length);

      if (!is_visible) {
        JX.DOM.remove(node);
        return;
      }

      document.body.appendChild(node);

      // If we're on OSX without a mouse or some other system with zero-width
      // trackpad-style scrollbars, adjust the display appropriately.
      var aesthetic = (JX.Scrollbar.getScrollbarControlWidth() === 0);
      JX.DOM.alterClass(node, 'has-aesthetic-scrollbar', aesthetic);

      var d = JX.Vector.getDocument();

      var list_dimensions = JX.Vector.getDim(node);
      var icon_height = 16;
      var list_y = (list_dimensions.y - icon_height);

      var ii;
      var offset;

      // First, build a list of all the items we're going to show.
      var items = [];
      for (ii = 0; ii < this._objectives.length; ii++) {
        var objective = this._objectives[ii];
        var objective_node = objective.getNode();

        var anchor = objective.getAnchor();
        if (!anchor || !objective.isVisible()) {
          JX.DOM.remove(objective_node);
          continue;
        }

        offset = (JX.$V(anchor).y / d.y) * (list_y);

        items.push({
          offset: offset,
          node: objective_node,
          objective: objective
        });
      }

      // Now, sort it from top to bottom.
      items.sort(function(u, v) {
        return u.offset - v.offset;
      });

      // Lay out the items in the objective list, leaving a minimum amount
      // of space between them so they do not overlap.
      var min = null;
      for (ii = 0; ii < items.length; ii++) {
        var item = items[ii];

        offset = item.offset;

        if (min !== null) {
          if (item.objective.shouldStack()) {
            offset = min;
          } else {
            offset = Math.max(offset, min);
          }
        }
        min = offset + 15;

        item.node.style.top = offset + 'px';
        node.appendChild(item.node);
      }

    }

  }

});
