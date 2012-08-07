/**
 * @provides javelin-behavior-device
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
 */

JX.behavior('device', function(config) {

  var current;

  function onresize() {
    var v = JX.Vector.getViewport();

    var device = 'desktop';
    if (v.x <= 768) {
      device = 'tablet';
    }
    if (v.x <= 480) {
      device = 'phone';
    }

    if (device == current) {
      return;
    }

    current = device;

    var e = JX.$(config.id);
    JX.DOM.alterClass(e, 'device-phone', (device == 'phone'));
    JX.DOM.alterClass(e, 'device-tablet', (device == 'tablet'));
    JX.DOM.alterClass(e, 'device-desktop', (device == 'desktop'));
  }

  JX.Stratcom.listen('resize', null, onresize);
  onresize();
});
