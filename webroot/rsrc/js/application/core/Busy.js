/**
 * @requires javelin-install
 *           javelin-dom
 * @provides phabricator-busy
 * @javelin
 */

/**
 * Show a "busy" indicator onscreen so the user knows something awesome is
 * happening, and that the awesome thing isn't the application breaking or
 * locking up.
 *
 * Example usage:
 *
 *   JX.Busy.start();
 *   // Do something...
 *   JX.Busy.done();
 *
 * Calls to `start()` should be paired with calls to `done()`.
 */
JX.install('Busy', {

  statics : {
    _depth : 0,
    start : function() {
      var self = JX.Busy;
      if (!self._depth) {
        self._indicator = JX.$N('div', {className: 'busy'});
        self._indicator.style.display = 'none';
        document.body.appendChild(self._indicator);

        // Don't actually show the indicator for a little while, to prevent
        // it from flashing briefly for every Ajax request.
        setTimeout(function() {
          self._indicator && (self._indicator.style.display = '');
        }, 500);
      }
      self._depth++;
    },
    done : function() {
      var self = JX.Busy;
      --self._depth;

      if (!self._depth) {
        JX.DOM.remove(self._indicator);
        self._indicator = null;
      }
    }
  }

});
