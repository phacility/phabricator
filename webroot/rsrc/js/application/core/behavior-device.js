/**
 * @provides javelin-behavior-device
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
 *           javelin-install
 */

JX.install('Device', {
  statics : {
    _device : null,
    getDevice : function() {
      return JX.Device._device;
    }
  }
});

JX.behavior('device', function(config) {

  function onresize() {
    var v = JX.Vector.getViewport();

    var device = 'desktop';
    if (v.x <= 768) {
      device = 'tablet';
    }
    if (v.x <= 480) {
      device = 'phone';
    }

    if (device == JX.Device.getDevice()) {
      return;
    }

    JX.Device._device = device;

    var e = JX.$(config.id);
    JX.DOM.alterClass(e, 'device-phone', (device == 'phone'));
    JX.DOM.alterClass(e, 'device-tablet', (device == 'tablet'));
    JX.DOM.alterClass(e, 'device-desktop', (device == 'desktop'));

    JX.Stratcom.invoke('phabricator-device-change', null, device);
  }

  JX.Stratcom.listen('resize', null, onresize);
  onresize();
});
