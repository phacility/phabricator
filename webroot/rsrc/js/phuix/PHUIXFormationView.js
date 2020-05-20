/**
 * @provides phuix-formation-view
 * @requires javelin-install
 *           javelin-dom
 *           phuix-formation-column-view
 *           phuix-formation-flank-view
 */

JX.install('PHUIXFormationView', {

  construct: function(node) {
    this._node = node;
    this._columns = [];

    var config = JX.Stratcom.getData(this._node);

    var items = config.items;
    var count = items.length;
    for (var ii = 0; ii < count; ii++) {
      var item = items[ii];
      var item_node = JX.$(item.itemID);

      var column = new JX.PHUIXFormationColumnView(item_node)
        .setIsRightAligned(item.isRightAligned)
        .setWidth(item.width)
        .setIsVisible(item.isVisible);

      if (item.expanderID) {
        column.setExpanderNode(JX.$(item.expanderID));
      }

      if (item.resizer) {
        column
          .setWidthSettingKey(item.resizer.widthKey)
          .setVisibleSettingKey(item.resizer.visibleKey)
          .setMinimumWidth(item.resizer.minimumWidth)
          .setMaximumWidth(item.resizer.maximumWidth)
          .setResizerItem(JX.$(item.resizer.itemID))
          .setResizerControl(JX.$(item.resizer.controlID));
      }

      var spec = item.column;
      if (spec) {
        if (spec.type === 'flank') {
          var flank_node = JX.$(spec.nodeID);

          var head = JX.$(spec.headID);
          var body = JX.$(spec.bodyID);
          var tail = JX.$(spec.tailID);

          var flank = new JX.PHUIXFormationFlankView(
            flank_node,
            head,
            body,
            tail);

          flank.setIsFixed(spec.isFixed);

          column.setFlank(flank);
        }
      }

      this.addColumn(column);
    }
  },

  members: {
    _node: null,
    _columns: null,

    addColumn: function(column) {
      this._columns.push(column);
    },

    getColumn: function(idx) {
      return this._columns[idx];
    },

    start: function() {
      JX.enableDispatch(document.body, 'mousemove');

      for (var ii = 0; ii < this._columns.length; ii++) {
        this._columns[ii].start();
      }

      var repaint = JX.bind(this, this.repaint);
      JX.Stratcom.listen(['scroll', 'resize'], null, repaint);

      this.repaint();
    },

    repaint: function(e) {
      // Unless we've scrolled past it, the page has a 44px main menu banner.
      var menu_height = (44 - JX.Vector.getScroll().y);

      // When the buoyant header is visible, move the menu down below it. This
      // is a bit of a hack.
      var banner_height = 0;
      try {
        var banner = JX.$('diff-banner');
        banner_height = JX.Vector.getDim(banner).y;
      } catch (error) {
        // Ignore if there's no banner on the page.
      }

      var header_height = Math.max(0, menu_height, banner_height);

      var column;
      var flank;
      for (var ii = 0; ii < this._columns.length; ii++) {
        column = this._columns[ii];

        flank = column.getFlank();
        if (!flank) {
          continue;
        }

        flank
          .setBannerHeight(header_height)
          .setWidth(column.getWidth())
          .repaint();
      }
    }

  }

});
