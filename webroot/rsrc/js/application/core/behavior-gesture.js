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
JX.behavior('phabricator-gesture', function(config) {

  var target = null;
  var swiping = false;
  var p0;
  var p1;

  JX.Stratcom.listen(
    ['touchstart', 'mousedown'],
    'touchable',
    function(e) {
      if (JX.Device.getDevice() == 'desktop') {
        return;
      }
      if (JX.Stratcom.pass()) {
        return;
      }

      if (target && e.getType() == 'touchstart') {
        // This corresponds to a second finger touching while the first finger
        // is held: stop the swipe.
        var event_data = get_swipe_data();
        var event_target = target;
        stop_swipe();
        JX.DOM.invoke(event_target, 'gesture.swipe.cancel', event_data);
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
    ['touchend', 'touchcancel', 'mouseup'],
    null,
    function(e) {
      if (!target) {
        return;
      }

      // NOTE: Clear the event state first so we don't keep swiping if a
      // handler throws.
      var event_target = target;
      var event_data = get_swipe_data();
      stop_swipe();

      JX.DOM.invoke(event_target, 'gesture.swipe.end', event_data);
    });

  function get_swipe_data() {
    var dir = (p1.x > p0.x) ? 'left' : 'right';
    var length = Math.abs(p1.x - p0.x);

    return {
      p0: p0,
      p1: p1,
      dir: dir,
      length: length
    };
  }

  function stop_swipe() {
    target = null;
    swiping = false;
  }

});
