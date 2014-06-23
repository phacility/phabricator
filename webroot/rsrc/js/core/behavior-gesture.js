/**
 * @provides javelin-behavior-phabricator-gesture
 * @requires javelin-behavior
 *           javelin-behavior-device
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-dom
 *           javelin-magical-init
 * @javelin
 */

/**
 * Basic gesture recognition. Unstable. Only supports swipes.
 */
JX.behavior('phabricator-gesture', function() {

  var target = null;
  var swiping = false;
  var p0;
  var p1;

  JX.Stratcom.listen(
    ['touchstart', 'touchcancel', 'mousedown'],
    'touchable',
    function(e) {
      if (JX.Device.getDevice() == 'desktop') {
        return;
      }
      if (JX.Stratcom.pass()) {
        return;
      }
      if (target) {
        try {
          JX.DOM.invoke(target, 'gesture.swipe.cancel', get_swipe_data());
        } finally {
          stop_swipe();
        }
        return;
      }

      target = e.getNode('touchable');
      p0 = JX.Vector.getPos(e);
      p1 = JX.Vector.getPos(e);
  });

  JX.enableDispatch(document.body, 'mousemove');
  JX.Stratcom.listen(
    ['touchmove', 'mousemove'],
    null,
    function(e) {
      if (!target) {
        return;
      }
      p1 = JX.Vector.getPos(e);
      if (!swiping) {

        // Here, we haven't started the swipe yet. We're waiting for you to
        // move your finger far enough to make it clear that you intend to
        // swipe or drag, not just tap.

        var dx = (p1.x - p0.x);
        var dy = (p1.y - p0.y);
        var swipe_radius = 20;
        if ((dx * dx) + (dy * dy) >= (swipe_radius * swipe_radius)) {

          // You've moved your finger far enough away from the origin that
          // we're sure you mean to swipe, scroll, or drag, not just tap.
          // Decide if you're trying to scroll or swipe. If your finger's
          // motion has been primarily vertical, assume this is a scroll. If
          // your finger's motion has been primarily horizontal, assume this
          // is a swipe.
          if (dy * dy >= dx * dx) {
            stop_swipe();
            return;
          }
          swiping = true;
          JX.DOM.invoke(target, 'gesture.swipe.start', get_swipe_data());
        }
      }
      if (swiping) {
        if (!e.getNode('touchable')) {
          p1 = JX.$V(p0);
        }
        JX.DOM.invoke(target, 'gesture.swipe.move', get_swipe_data());
        e.prevent();
      }
    });

  JX.Stratcom.listen(
    ['touchend', 'mouseup'],
    null,
    function() {
      if (!target) {
        return;
      }

      try {
        if (swiping) {
          JX.DOM.invoke(target, 'gesture.swipe.end', get_swipe_data());
        }
      } finally {
        stop_swipe();
      }
    });

  function get_swipe_data() {
    var direction = (p1.x > p0.x) ? 'right' : 'left';
    var length = Math.abs(p1.x - p0.x);

    return {
      p0: p0,
      p1: p1,
      direction: direction,
      length: length
    };
  }

  function stop_swipe() {
    target = null;
    swiping = false;
  }

});
