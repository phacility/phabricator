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
    this._frame = frame;

    JX.DOM.listen(frame, 'load', null, JX.bind(this, this._onload));
    this._onload();

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
    JX.DOM.listen(viewport, 'mouseenter', null, JX.bind(this, this._onenter));

    JX.DOM.listen(frame, 'scroll', null, JX.bind(this, this._onscroll));

    // Enabling dispatch for this event on `window` allows us to scroll even
    // if the mouse cursor is dragged outside the window in at least some
    // browsers (for example, Safari on OSX).
    JX.enableDispatch(window, 'mousemove');
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
    },


    /**
     * Get the margin width required to avoid double scrollbars.
     *
     * For most browsers which render a real scrollbar control, this is 0.
     * Adjacent elements may touch the edge of the content directly without
     * overlapping.
     *
     * On OSX with a trackpad, scrollbars are only drawn when content is
     * scrolled. Content panes with internal scrollbars may overlap adjacent
     * scrollbars if they are not laid out with a margin.
     *
     * @return int Control margin width in pixels.
     */
    getScrollbarControlMargin: function() {
      var self = JX.Scrollbar;

      // If this browser and OS don't render a real scrollbar control, we
      // need to leave a margin. Generally, this is OSX with no mouse attached.
      if (self._getScrollbarControlWidth() === 0) {
        return 12;
      }

      return 0;
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
    _lastHeight: null,


    /**
     * Mark this content as the scroll frame.
     *
     * This changes the behavior of the @{class:JX.DOM} scroll functions so the
     * continue to work properly if the main page content is reframed to scroll
     * independently.
     */
    setAsScrollFrame: function() {
      if (this._viewport) {
        // If we activated the scrollbar, the viewport and content nodes become
        // the new scroll and content frames.
        JX.DOM.setContentFrame(this._viewport, this._content);

        // If nothing is focused, or the document body is focused, change focus
        // to the viewport. This makes the arrow keys, spacebar, and page
        // up/page down keys work immediately after the page loads, without
        // requiring a click.

        // Focusing the <div /> itself doesn't work on any browser, so we
        // add a fake, focusable element and focus that instead.
        var focus = document.activeElement;
        if (!focus || focus == window.document.body) {
          var link = JX.$N('a', {href: '#', className: 'jx-scrollbar-link'});
          JX.DOM.listen(link, 'blur', null, function() {
            // When the user clicks anything else, remove this.
            try {
              JX.DOM.remove(link);
            } catch (ignored) {
              // We can get a second blur event, likey related to T447.
              // Fix doesn't seem trivial so just ignore it.
            }
          });
          JX.DOM.listen(link, 'click', null, function(e) {
            // Don't respond to clicks. Since the link isn't visible, this
            // most likely means the user hit enter or something like that.
            e.kill();
          });
          JX.DOM.prependContent(this._viewport, link);
          JX.DOM.focus(link);
        }
      } else {
        // Otherwise, the unaltered content frame is both the scroll frame and
        // content frame.
        JX.DOM.setContentFrame(this._frame, this._frame);
      }
    },


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
      this._dragOrigin = JX.$V(e);

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

      var p = JX.$V(e);
      var offset = (p.y - this._dragOrigin.y);
      var ratio = offset / JX.Vector.getDim(this._bar).y;
      var adjust = ratio * JX.Vector.getDim(this._content).y;

      if (this._shouldSnapback()) {
        if (Math.abs(p.x - this._dragOrigin.x) > 140) {
          adjust = 0;
        }
      }

      this._viewport.scrollTop = this._scrollOrigin + adjust;
    },


    /**
     * Should the scrollbar snap back to the original position if the user
     * drags the mouse away to the left or right, perpendicular to the
     * scrollbar?
     *
     * Scrollbars have this behavior on Windows, but not on OSX or Linux.
     */
    _shouldSnapback: function() {
      // Since this is an OS-specific behavior, detect the OS. We can't
      // reasonably use feature detection here.
      return (navigator.platform.indexOf('Win') > -1);
    },


    /**
     * When the user releases the mouse after a drag, stop moving the
     * viewport.
     */
    _ondrop: function() {
      this._dragOrigin = null;

      // Reset the timer to hide the bar.
      this._showBar();
    },



    /**
     * Something inside the frame fired a load event.
     *
     * The typical case is that an image loaded. This may have changed the
     * height of the scroll area, and we may want to make adjustments.
     */
    _onload: function() {
      var viewport = this.getViewportNode();
      var height = viewport.scrollHeight;
      var visible = JX.Vector.getDim(viewport).y;
      if (this._lastHeight !== null && this._lastHeight != height) {

        // If the viewport was scrollable and was scrolled down to near the
        // bottom, scroll it down to account for the new height. The effect
        // of this rule is to keep panels like the chat column scrolled to
        // the bottom as images load into the thread.
        if (viewport.scrollTop > 0) {
          if ((viewport.scrollTop + visible + 64) >= this._lastHeight) {
            viewport.scrollTop += (height - this._lastHeight);
          }
        }

      }

      this._lastHeight = height;
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
      // We're hiding and showing the bar itself, not just the handle, because
      // pages that contain other panels may have scrollbars underneath the
      // bar. If we don't hide the bar, it ends up eating clicks targeting
      // these panels.

      // Because the bar may be hidden, we can't measure it. Measure the
      // viewport instead.

      var cdim = JX.Vector.getDim(this._content);
      var spos = JX.Vector.getAggregateScrollForNode(this._viewport);
      var vdim = JX.Vector.getDim(this._viewport);

      var ratio = (vdim.y / cdim.y);

      // We're scaling things down very slightly to leave a 2px margin at
      // either end of the scroll gutter, so the bar doesn't quite bump up
      // against the chrome.
      ratio = ratio * (vdim.y / (vdim.y + 4));

      var offset = Math.round(ratio * spos.y) + 2;
      var size = Math.floor(ratio * vdim.y);

      if (size < cdim.y) {
        this._handle.style.top = offset + 'px';
        this._handle.style.height = size + 'px';

        JX.DOM.show(this._bar);
      } else {
        JX.DOM.hide(this._bar);
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
      if (this._dragOrigin !== null) {
        // If we're currently dragging the handle, we never want to hide
        // it.
        return;
      }

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
    },

    getContentNode: function() {
      return this._content || this._frame;
    },

    getViewportNode: function() {
      return this._viewport || this._frame;
    },

    scrollTo: function(scroll) {
      if (this._viewport !== null) {
        this._viewport.scrollTop = scroll;
      } else {
        this._frame.scrollTop = scroll;
      }
      return this;
    }
  }

});
