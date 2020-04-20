/**
 * @provides phuix-formation-view
 * @requires javelin-install
 *           javelin-dom
 */

JX.install('PHUIXFormationView', {

  construct: function() {
    this._columns = [];
  },

  members: {
    _columns: null,

    addColumn: function(column) {
      this._columns.push(column);
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
