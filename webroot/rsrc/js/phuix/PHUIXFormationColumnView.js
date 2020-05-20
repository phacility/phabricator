/**
 * @provides phuix-formation-column-view
 * @requires javelin-install
 *           javelin-dom
 */

JX.install('PHUIXFormationColumnView', {

  construct: function(node) {
    this._node = node;
  },

  properties: {
    isRightAligned: false,
    isVisible: true,
    expanderNode: null,
    resizerItem: null,
    resizerControl: null,
    width: null,
    widthSettingKey: null,
    visibleSettingKey: null,
    minimumWidth: null,
    maximumWidth: null,
    flank: null
  },

  members: {
    _node: null,
    _resizingWidth: null,
    _resizingBarPosition: null,
    _dragging: null,

    start: function() {
      var onshow = JX.bind(this, this._setVisibility, true);
      var onhide = JX.bind(this, this._setVisibility, false);

      JX.DOM.listen(this._node, 'click', 'phui-flank-header-hide', onhide);

      var expander = this.getExpanderNode();
      if (expander) {
        JX.DOM.listen(expander, 'click', null, onshow);
      }

      var resizer = this.getResizerItem();
      if (resizer) {
        var ondown = JX.bind(this, this._onresizestart);
        JX.DOM.listen(resizer, 'mousedown', null, ondown);

        var onmove = JX.bind(this, this._onresizemove);
        JX.Stratcom.listen('mousemove', null, onmove);

        var onup = JX.bind(this, this._onresizeend);
        JX.Stratcom.listen('mouseup', null, onup);
      }

      this.repaint();
    },

    _onresizestart: function(e) {
      if (!e.isNormalMouseEvent()) {
        return;
      }

      this._dragging = JX.$V(e);
      this._resizingWidth = this.getWidth();
      this._resizingBarPosition = JX.$V(this.getResizerControl());

      // Show the "col-resize" cursor on the whole document while we're
      // dragging, since the mouse will slip off the actual bar fairly often
      // and we don't want it to flicker.
      JX.DOM.alterClass(document.body, 'jx-drag-col', true);

      e.kill();
    },

    _onresizemove: function(e) {
      if (!this._dragging) {
        return;
      }

      var dx = (JX.$V(e).x - this._dragging.x);

      var width;
      if (this.getIsRightAligned()) {
        width = this.getWidth() - dx;
      } else {
        width = this.getWidth() + dx;
      }

      var min_width = this.getMinimumWidth();
      if (min_width) {
        width = Math.max(width, min_width);
      }

      var max_width = this.getMaximumWidth();
      if (max_width) {
        width = Math.min(width, max_width);
      }

      this._resizingWidth = width;

      this._node.style.width = this._resizingWidth + 'px';

      var adjust_x = (this._resizingWidth - this.getWidth());
      if (this.getIsRightAligned()) {
        adjust_x = -adjust_x;
      }

      this.getResizerControl().style.left =
        (this._resizingBarPosition.x + adjust_x) + 'px';

      var flank = this.getFlank();
      if (flank) {
        flank
          .setWidth(this._resizingWidth)
          .repaint();
      }
    },

    _onresizeend: function(e) {
      if (!this._dragging) {
        return;
      }

      this.setWidth(this._resizingWidth);

      JX.DOM.alterClass(document.body, 'jx-drag-col', false);
      this._dragging = null;

      var width_key = this.getWidthSettingKey();
      if (width_key) {
        this._adjustSetting(width_key, this.getWidth());
      }
    },

    _setVisibility: function(visible, e) {
      e.kill();
      this.setVisibility(visible);
    },

    toggleVisibility: function() {
      return this.setVisibility(!this.getIsVisible());
    },

    setVisibility: function(visible) {
      this.setIsVisible(visible);
      this.repaint();

      var visible_key = this.getVisibleSettingKey();
      if (visible_key) {
        this._adjustSetting(visible_key, visible ? 1 : 0);
      }

      return this;
    },

    _adjustSetting: function(key, value) {
      new JX.Request('/settings/adjust/', JX.bag)
        .setData(
          {
            key: key,
            value: value
          })
        .send();
    },

    repaint: function() {
      var resizer = this.getResizerItem();
      var expander = this.getExpanderNode();

      if (this.getIsVisible()) {
        JX.DOM.show(this._node);
        if (resizer) {
          JX.DOM.show(resizer);
        }
        if (expander) {
          JX.DOM.hide(expander);
        }
      } else {
        JX.DOM.hide(this._node);
        if (resizer) {
          JX.DOM.hide(resizer);
        }
        if (expander) {
          JX.DOM.show(expander);
        }
      }

      if (this.getFlank()) {
        this.getFlank().repaint();
      }

    },


  }

});
