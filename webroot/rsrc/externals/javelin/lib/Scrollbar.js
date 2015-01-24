/**
 * @provides javelin-scrollbar
 * @requires javelin-install
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-vector
 * @javelin
 */

/**
 * Provides an aesthetic scrollbar.
 *
 * This shoves an element's scrollbar under a hidden overflow and draws a
 * pretty looking fake one in its place. This makes complex UIs with multiple
 * independently scrollable panels less hideous by (a) making the scrollbar
 * itself prettier and (b) reclaiming the space occupied by the scrollbar.
 *
 * Note that on OSX the heavy scrollbars are normally drawn only if you have
 * a mouse connected. OSX uses more aesthetic touchpad scrollbars normally,
 * which these scrollbars emulate.
 *
 * This class was initially adapted from "Trackpad Scroll Emulator", by
 * Jonathan Nicol. See <https://github.com/jnicol/trackpad-scroll-emulator>.
 */
JX.install('Scrollbar', {

  construct: function(frame) {
    // Before doing anything, check if the scrollbar control has a measurable
    // width. If it doesn't, we're already in an environment with an aesthetic
    // scrollbar (like Safari on OSX with no mouse connected, or an iPhone)
    // and we don't need to do anything.
    if (JX.Scrollbar._getScrollbarControlWidth() === 0) {
      return;
    }

    // Wrap the frame content in a bunch of nodes. The frame itself stays on
    // the outside so that any positioning information the node had isn't
    // disrupted.

    // We put a "viewport" node inside of it, which is what actually scrolls.
    // This is the node that gets a scrollbar, but we make the viewport very
    // slightly too wide for the frame. That hides the scrollbar underneath
    // the edge of the frame.

    // We put a "content" node inside of the viewport. This allows us to
    // measure the content height so we can resize and offset the scrollbar
    // handle properly.

    // We move all the actual frame content into the "content" node. So it
    // ends up wrapped by the "content" node, then by the "viewport" node,
    // and finally by the original "frame" node.

    JX.DOM.alterClass(frame, 'jx-scrollbar-frame', true);

    var content = JX.$N('div', {className: 'jx-scrollbar-content'});
    while (frame.firstChild) {
      JX.DOM.appendContent(content, frame.firstChild);
    }

    var viewport = JX.$N('div', {className: 'jx-scrollbar-viewport'}, content);
    JX.DOM.appendContent(frame, viewport);

    this._frame = frame;
    this._viewport = viewport;
    this._content = content;

    // The handle is the visible node which you can click and drag.
    this._handle = JX.$N('div', {className: 'jx-scrollbar-handle'});

    // The bar is the area the handle slides up and down in.
    this._bar = JX.$N('div', {className: 'jx-scrollbar-bar'}, this._handle);

    JX.DOM.prependContent(frame, this._bar);

    JX.DOM.listen(this._handle, 'mousedown', null, JX.bind(this, this._ondrag));
    JX.DOM.listen(this._bar, 'mousedown', null, JX.bind(this, this._onjump));

    JX.enableDispatch(document.body, 'mouseenter');
    JX.enableDispatch(document.body, 'mousemove');

    JX.DOM.listen(viewport, 'mouseenter', null, JX.bind(this, this._onenter));
    JX.DOM.listen(frame, 'scroll', null, JX.bind(this, this._onscroll));

    JX.DOM.listen(viewport, 'mouseenter', null, JX.bind(this, this._onenter));
    JX.DOM.listen(viewport, 'mouseenter', null, JX.bind(this, this._onenter));

    JX.Stratcom.listen('mousemove', null, JX.bind(this, this._onmove));
    JX.Stratcom.listen('mouseup', null, JX.bind(this, this._ondrop));
    JX.Stratcom.listen('resize', null, JX.bind(this, this._onresize));

    this._resizeViewport();
    this._resizeBar();
  },

  statics: {
    _controlWidth: null,

    /**
     * Compute the width of the browser's scrollbar control, in pixels.
     */
    _getScrollbarControlWidth: function() {
      var self = JX.Scrollbar;

      if (self._controlWidth === null) {
        var tmp = JX.$N('div', {className: 'jx-scrollbar-test'}, '-');
        document.body.appendChild(tmp);
        var d1 = JX.Vector.getDim(tmp);
        tmp.style.overflowY = 'scroll';
        var d2 = JX.Vector.getDim(tmp);
        JX.DOM.remove(tmp);

        self._controlWidth = (d2.x - d1.x);
      }

      return self._controlWidth;
    }

  },

  members: {
    _frame: null,
    _viewport: null,
    _content: null,

    _bar: null,
    _handle: null,

    _timeout: null,
    _dragOrigin: null,
    _scrollOrigin: null,


    /**
     * After the user scrolls the page, show the scrollbar to give them
     * feedback about their position.
     */
    _onscroll: function() {
      this._showBar();
    },


    /**
     * When the user mouses over the viewport, show the scrollbar.
     */
    _onenter: function() {
      this._showBar();
    },


    /**
     * When the user resizes the window, recalculate everything.
     */
    _onresize: function() {
      this._resizeViewport();
      this._resizeBar();
    },


    /**
     * When the user clicks the bar area (but not the handle), jump up or
     * down a page.
     */
    _onjump: function(e) {
      if (e.getTarget() === this._handle) {
        return;
      }

      var distance = JX.Vector.getDim(this._viewport).y * (7/8);
      var epos = JX.$V(e);
      var hpos = JX.$V(this._handle);

      if (epos.y > hpos.y) {
        this._viewport.scrollTop += distance;
      } else {
        this._viewport.scrollTop -= distance;
      }
    },


    /**
     * When the user clicks the scroll handle, begin dragging it.
     */
    _ondrag: function(e) {
      e.kill();

      // Store the position where the drag started.
      this._dragOrigin = JX.$V(e).y;

      // Store the original position of the handle.
      this._scrollOrigin = this._viewport.scrollTop;
    },


    /**
     * As the user drags the scroll handle up or down, scroll the viewport.
     */
    _onmove: function(e) {
      if (this._dragOrigin === null) {
        return;
      }

      var offset = (JX.$V(e).y - this._dragOrigin);
      var ratio = offset / JX.Vector.getDim(this._bar).y;
      var adjust = ratio * JX.Vector.getDim(this._content).y;

      this._viewport.scrollTop = this._scrollOrigin + adjust;
    },


    /**
     * When the user releases the mouse after a drag, stop moving the
     * viewport.
     */
    _ondrop: function() {
      this._dragOrigin = null;
    },


    /**
     * Shove the scrollbar on the viewport under the edge of the frame so the
     * user can't see it.
     */
    _resizeViewport: function() {
      var fdim = JX.Vector.getDim(this._frame);
      fdim.x += JX.Scrollbar._getScrollbarControlWidth();
      fdim.setDim(this._viewport);
    },


    /**
     * Figure out the correct size and offset of the scrollbar handle.
     */
    _resizeBar: function() {
      var cdim = JX.Vector.getDim(this._content);
      var spos = JX.Vector.getAggregateScrollForNode(this._viewport);
      var bdim = JX.Vector.getDim(this._bar);

      var ratio = bdim.y / cdim.y;

      var offset = Math.round(ratio * spos.y) + 2;
      var size = Math.floor(ratio * (bdim.y - 2)) - 2;

      if (size < cdim.y) {
        this._handle.style.top = offset + 'px';
        this._handle.style.height = size + 'px';

        JX.DOM.show(this._handle);
      } else {
        JX.DOM.hide(this._handle);
      }
    },


    /**
     * Show the scrollbar for the next second.
     */
    _showBar: function() {
      this._resizeBar();

      JX.DOM.alterClass(this._handle, 'jx-scrollbar-visible', true);

      this._clearTimeout();
      this._timeout = setTimeout(JX.bind(this, this._hideBar), 1000);
    },


    /**
     * Hide the scrollbar.
     */
    _hideBar: function() {
      JX.DOM.alterClass(this._handle, 'jx-scrollbar-visible', false);
      this._clearTimeout();
    },


    /**
     * Clear the scrollbar hide timeout, if one is set.
     */
    _clearTimeout: function() {
      if (this._timeout) {
        clearTimeout(this._timeout);
        this._timeout = null;
      }
    }
  }

});
