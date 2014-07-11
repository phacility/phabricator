/**
 * @requires javelin-install
 *           javelin-dom
 * @provides javelin-mask
 * @javelin
 */

/**
 * Show a "mask" over the page for lightboxes or dialogs. This is used by
 * Workflow to draw visual attention to modal dialogs.
 *
 *   JX.Mask.show();
 *   // Show a dialog, lightbox, or other modal UI.
 *   JX.Mask.hide();
 *
 * Masks are stackable, if modal UIs need to spawn other modal UIs.
 *
 * The mask has class `jx-mask`, which you should apply styles to. For example:
 *
 *   .jx-mask {
 *     opacity: 0.8;
 *     background: #000000;
 *     position: fixed;
 *     top: 0;
 *     bottom: 0;
 *     left: 0;
 *     right: 0;
 *     z-index: 2;
 *   }
 *
 * You can create multiple mask styles and select them with the `mask_type`
 * parameter to `show()` (for instance, a light mask for dialogs and a dark
 * mask for lightboxing):
 *
 *   JX.Mask.show('jx-light-mask');
 *   // ...
 *   JX.Mask.hide();
 *
 * This will be applied as a class name to the mask element, which you can
 * customize in CSS:
 *
 *   .jx-light-mask {
 *     background: #ffffff;
 *   }
 *
 * The mask has sigil `jx-mask`, which can be used to intercept events
 * targeting it, like clicks on the mask.
 */
JX.install('Mask', {
  statics : {
    _stack : [],
    _mask : null,
    _currentType : null,


    /**
     * Show a mask over the document. Multiple calls push masks onto a stack.
     *
     * @param string Optional class name to apply to the mask, if you have
     *               multiple masks (e.g., one dark and one light).
     * @return void
     */
    show : function(mask_type) {
      var self = JX.Mask;
      mask_type = mask_type || null;

      if (!self._stack.length) {
        self._mask = JX.$N('div', {className: 'jx-mask', sigil: 'jx-mask'});
        document.body.appendChild(self._mask);
      }

      self._adjustType(mask_type);
      JX.Mask._stack.push(mask_type);
    },

    /**
     * Hide the current mask. The mask stack is popped, which may reveal another
     * mask below the current mask.
     *
     * @return void
     */
    hide : function() {
      var self = JX.Mask;
      var mask_type = self._stack.pop();

      self._adjustType(mask_type);

      if (!self._stack.length) {
        JX.DOM.remove(JX.Mask._mask);
        JX.Mask._mask = null;
      }
    },


    _adjustType : function(new_type) {
      var self = JX.Mask;
      if (self._currentType) {
        JX.DOM.alterClass(self._mask, self._currentType, false);
        self._currentType = null;
      }
      if (new_type) {
        JX.DOM.alterClass(self._mask, new_type, true);
        self._currentType = new_type;
      }
    }
  }
});
