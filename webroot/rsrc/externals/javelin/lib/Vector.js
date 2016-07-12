/**
 * @requires javelin-install
 *           javelin-event
 * @provides javelin-vector
 *
 * @javelin-installs JX.$V
 *
 * @javelin
 */


/**
 * Convenience function that returns a @{class:JX.Vector} instance. This allows
 * you to concisely write things like:
 *
 *  JX.$V(x, y).add(10, 10);                // Explicit coordinates.
 *  JX.$V(node).add(50, 50).setDim(node);   // Position of a node.
 *
 * @param number|Node         If a node, returns the node's position vector.
 *                            If numeric, the x-coordinate for the new vector.
 * @param number?             The y-coordinate for the new vector.
 * @return @{class:JX.Vector} New vector.
 */
JX.$V = function(x, y) {
  return new JX.Vector(x, y);
};


/**
 * Query and update positions and dimensions of nodes (and other things) within
 * within a document. Each vector has two elements, 'x' and 'y', which usually
 * represent width/height ('dimension vector') or left/top ('position vector').
 *
 * Vectors are used to manage the sizes and positions of elements, events,
 * the document, and the viewport (the visible section of the document, i.e.
 * how much of the page the user can actually see in their browser window).
 * Unlike most Javelin classes, @{class:JX.Vector} exposes two bare properties,
 * 'x' and 'y'. You can read and manipulate these directly:
 *
 *   // Give the user information about elements when they click on them.
 *   JX.Stratcom.listen(
 *     'click',
 *     null,
 *     function(e) {
 *       var p = new JX.Vector(e);
 *       var d = JX.Vector.getDim(e.getTarget());
 *
 *       alert('You clicked at <' + p.x + ',' + p.y + '> and the element ' +
 *             'you clicked is ' + d.x + 'px wide and ' + d.y + 'px high.');
 *     });
 *
 * You can also update positions and dimensions using vectors:
 *
 *   // When the user clicks on something, make it 10px wider and 10px taller.
 *   JX.Stratcom.listen(
 *     'click',
 *     null,
 *     function(e) {
 *       var target = e.getTarget();
 *       JX.$V(target).add(10, 10).setDim(target);
 *     });
 *
 * Additionally, vectors can be used to query document and viewport information:
 *
 *   var v = JX.Vector.getViewport(); // Viewport (window) width and height.
 *   var d = JX.Vector.getDocument(); // Document width and height.
 *   var visible_area = parseInt(100 * (v.x * v.y) / (d.x * d.y), 10);
 *   alert('You can currently see ' + visible_area + ' % of the document.');
 *
 * The function @{function:JX.$V} provides convenience construction of common
 * vectors.
 *
 * @task query  Querying Positions and Dimensions
 * @task update Changing Positions and Dimensions
 * @task manip  Manipulating Vectors
 */
JX.install('Vector', {

  /**
   * Construct a vector, either from explicit coordinates or from a node
   * or event. You can pass two Numbers to construct an explicit vector:
   *
   *   var p = new JX.Vector(35, 42);
   *
   * Otherwise, you can pass a @{class:JX.Event} or a Node to implicitly
   * construct a vector:
   *
   *   var q = new JX.Vector(some_event);
   *   var r = new JX.Vector(some_node);
   *
   * These are just like calling JX.Vector.getPos() on the @{class:JX.Event} or
   * Node.
   *
   * For convenience, @{function:JX.$V} constructs a new vector so you don't
   * need to use the 'new' keyword. That is, these are equivalent:
   *
   *   var s = new JX.Vector(x, y);
   *   var t = JX.$V(x, y);
   *
   * Methods like @{method:getScroll}, @{method:getViewport} and
   * @{method:getDocument} also create new vectors.
   *
   * Once you have a vector, you can manipulate it with add():
   *
   *   var u = JX.$V(35, 42);
   *   var v = u.add(5, -12); // v = <40, 30>
   *
   * @param wild      'x' component of the vector, or a @{class:JX.Event}, or a
   *                  Node.
   * @param Number?   If providing an 'x' component, the 'y' component of the
   *                  vector.
   * @return @{class:JX.Vector} Specified vector.
   * @task query
   */
  construct : function(x, y) {
    if (typeof y == 'undefined') {
      return JX.Vector.getPos(x);
    }

    this.x = (x === null) ? null : parseFloat(x);
    this.y = (y === null) ? null : parseFloat(y);
  },

  members : {
    x : null,
    y : null,

    /**
     * Move a node around by setting the position of a Node to the vector's
     * coordinates. For instance, if you want to move an element to the top left
     * corner of the document, you could do this (assuming it has 'position:
     * absolute'):
     *
     *   JX.$V(0, 0).setPos(node);
     *
     * @param Node Node to move.
     * @return this
     * @task update
     */
    setPos : function(node) {
      node.style.left = (this.x === null) ? '' : (parseInt(this.x, 10) + 'px');
      node.style.top  = (this.y === null) ? '' : (parseInt(this.y, 10) + 'px');
      return this;
    },

    /**
     * Change the size of a node by setting its dimensions to the vector's
     * coordinates. For instance, if you want to change an element to be 100px
     * by 100px:
     *
     *   JX.$V(100, 100).setDim(node);
     *
     * Or if you want to expand a node's dimensions by 50px:
     *
     *   JX.$V(node).add(50, 50).setDim(node);
     *
     * @param Node Node to resize.
     * @return this
     * @task update
     */
    setDim : function(node) {
      node.style.width =
        (this.x === null) ? '' : (parseInt(this.x, 10) + 'px');
      node.style.height =
        (this.y === null) ? '' : (parseInt(this.y, 10) + 'px');
      return this;
    },

    /**
     * Change a vector's x and y coordinates by adding numbers to them, or
     * adding the coordinates of another vector. For example:
     *
     *   var u = JX.$V(3, 4).add(100, 200); // u = <103, 204>
     *
     * You can also add another vector:
     *
     *   var q = JX.$V(777, 999);
     *   var r = JX.$V(1000, 2000);
     *   var s = q.add(r); // s = <1777, 2999>
     *
     * Note that this method returns a new vector. It does not modify the
     * 'this' vector.
     *
     * @param wild      Value to add to the vector's x component, or another
     *                  vector.
     * @param Number?   Value to add to the vector's y component.
     * @return @{class:JX.Vector} New vector, with summed components.
     * @task manip
     */
    add : function(x, y) {
      if (x instanceof JX.Vector) {
        y = x.y;
        x = x.x;
      }
      return new JX.Vector(this.x + parseFloat(x), this.y + parseFloat(y));
    }
  },

  statics : {
    _viewport: null,

    /**
     * Determine where in a document an element is (or where an event, like
     * a click, occurred) by building a new vector containing the position of a
     * Node or @{class:JX.Event}. The 'x' component of the vector will
     * correspond to the pixel offset of the argument relative to the left edge
     * of the document, and the 'y' component will correspond to the pixel
     * offset of the argument relative to the top edge of the document. Note
     * that all vectors are generated in document coordinates, so the scroll
     * position does not affect them.
     *
     * See also @{method:getDim}, used to determine an element's dimensions.
     *
     * @param  Node|@{class:JX.Event}  Node or event to determine the position
     *                                 of.
     * @return @{class:JX.Vector}      New vector with the argument's position.
     * @task query
     */
    getPos : function(node) {
      JX.Event && (node instanceof JX.Event) && (node = node.getRawEvent());

      if (node.getBoundingClientRect) {
        var rect;
        try {
          rect = node.getBoundingClientRect();
        } catch (e) {
          rect = { top : 0, left : 0 };
        }
        return new JX.Vector(
          rect.left + window.pageXOffset,
          rect.top + window.pageYOffset);
      }

      if (('pageX' in node) || ('clientX' in node)) {
        var c = JX.Vector._viewport;
        return new JX.Vector(
          node.pageX || (node.clientX + c.scrollLeft),
          node.pageY || (node.clientY + c.scrollTop)
        );
      }

      var x = 0;
      var y = 0;
      do {
        var offsetParent = node.offsetParent;
        var scrollLeft = 0;
        var scrollTop = 0;
        if (offsetParent && offsetParent != document.body) {
          scrollLeft = offsetParent.scrollLeft;
          scrollTop = offsetParent.scrollTop;
        }
        x += (node.offsetLeft - scrollLeft);
        y += (node.offsetTop - scrollTop);
        node = offsetParent;
      } while (node && node != document.body);

      return new JX.Vector(x, y);
    },

    /**
     * Determine the width and height of a node by building a new vector with
     * dimension information. The 'x' component of the vector will correspond
     * to the element's width in pixels, and the 'y' component will correspond
     * to its height in pixels.
     *
     * See also @{method:getPos}, used to determine an element's position.
     *
     * @param  Node      Node to determine the display size of.
     * @return @{JX.$V}  New vector with the node's dimensions.
     * @task query
     */
    getDim : function(node) {
      return new JX.Vector(node.offsetWidth, node.offsetHeight);
    },

    /**
     * Determine the current scroll position by building a new vector where
     * the 'x' component corresponds to how many pixels the user has scrolled
     * from the left edge of the document, and the 'y' component corresponds to
     * how many pixels the user has scrolled from the top edge of the document.
     *
     * See also @{method:getViewport}, used to determine the size of the
     * viewport.
     *
     * @return @{JX.$V}  New vector with the document scroll position.
     * @task query
     */
    getScroll : function() {
      // We can't use JX.Vector._viewport here because there's diversity between
      // browsers with respect to where position/dimension and scroll position
      // information is stored.
      var b = document.body;
      var e = document.documentElement;
      return new JX.Vector(
        window.pageXOffset || b.scrollLeft || e.scrollLeft,
        window.pageYOffset || b.scrollTop || e.scrollTop
      );
    },


    /**
     * Get the aggregate scroll offsets for a node and all of its parents.
     *
     * Note that this excludes scroll at the document level, because it does
     * not normally impact operations in document coordinates, which everything
     * on this class returns. Use @{method:getScroll} to get the document scroll
     * position.
     *
     * @param   Node        Node to determine offsets for.
     * @return  JX.Vector   New vector with aggregate scroll offsets.
     */
    getAggregateScrollForNode: function(node) {
      var x = 0;
      var y = 0;

      do {
        if (node == document.body || node == document.documentElement) {
          break;
        }

        x += node.scrollLeft || 0;
        y += node.scrollTop || 0;
        node = node.parentNode;
      } while (node);

      return new JX.$V(x, y);
    },


    /**
     * Get the sum of a node's position and its parent scroll offsets.
     *
     * @param   Node        Node to determine aggregate position for.
     * @return  JX.Vector   New vector with aggregate position.
     */
    getPosWithScroll: function(node) {
      return JX.$V(node).add(JX.Vector.getAggregateScrollForNode(node));
    },


    /**
     * Determine the size of the viewport (basically, the browser window) by
     * building a new vector where the 'x' component corresponds to the width
     * of the viewport in pixels and the 'y' component corresponds to the height
     * of the viewport in pixels.
     *
     * See also @{method:getScroll}, used to determine the position of the
     * viewport, and @{method:getDocument}, used to determine the size of the
     * entire document.
     *
     * @return @{class:JX.Vector}  New vector with the viewport dimensions.
     * @task query
     */
    getViewport : function() {
      var c = JX.Vector._viewport;
      return new JX.Vector(
        window.innerWidth || c.clientWidth || 0,
        window.innerHeight || c.clientHeight || 0
      );
    },

    /**
     * Determine the size of the document, including any area outside the
     * current viewport which the user would need to scroll in order to see, by
     * building a new vector where the 'x' component corresponds to the document
     * width in pixels and the 'y' component corresponds to the document height
     * in pixels.
     *
     * @return @{class:JX.Vector} New vector with the document dimensions.
     * @task query
     */
    getDocument : function() {
      var c = JX.Vector._viewport;
      return new JX.Vector(c.scrollWidth || 0, c.scrollHeight || 0);
    }
  },

  /**
   * On initialization, the browser-dependent viewport root is determined and
   * stored.
   *
   * In ##__DEV__##, @{class:JX.Vector} installs a toString() method so
   * vectors print in a debuggable way:
   *
   *   <23, 92>
   *
   * This string representation of vectors is not available in a production
   * context.
   *
   * @return void
   */
  initialize : function() {
    JX.Vector._viewport = document.documentElement || document.body;

    if (__DEV__) {
      JX.Vector.prototype.toString = function() {
        return '<' + this.x + ', ' + this.y + '>';
      };
    }
  }

});
