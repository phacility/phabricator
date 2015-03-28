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
    _tabletBreakpoint: 768,

    setTabletBreakpoint: function(width) {
      var self = JX.Device;
      self._tabletBreakpoint = width;
      self.recalculate();
    },

    getTabletBreakpoint: function() {
      return JX.Device._tabletBreakpoint;
    },

    recalculate: function() {
      var v = JX.Vector.getViewport();
      var self = JX.Device;

      var device = 'desktop';
      if (v.x <= self._tabletBreakpoint) {
        device = 'tablet';
      }
      if (v.x <= 480) {
        device = 'phone';
      }

      if (device == self._device) {
        return;
      }

      self._device = device;

      var e = document.body;
      JX.DOM.alterClass(e, 'device-phone', (device == 'phone'));
      JX.DOM.alterClass(e, 'device-tablet', (device == 'tablet'));
      JX.DOM.alterClass(e, 'device-desktop', (device == 'desktop'));
      JX.DOM.alterClass(e, 'device', (device != 'desktop'));

      JX.Stratcom.invoke('phabricator-device-change', null, device);
    },

    getDevice : function() {
      var self = JX.Device;
      if (self._device === null) {
        self.recalculate();
      }
      return self._device;
    }
  }
});

JX.behavior('device', function() {
  JX.Stratcom.listen('resize', null, JX.Device.recalculate);
  JX.Device.recalculate();
});
