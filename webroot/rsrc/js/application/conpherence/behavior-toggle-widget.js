/**
 * @provides javelin-behavior-toggle-widget
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-workflow
 *           javelin-stratcom
 */

JX.behavior('toggle-widget', function(config) {

  var device;

  function init() {
    device = JX.Device.getDevice();
    if (device != 'phone') {
      var node = JX.$('conpherence-main-layout');
      JX.DOM.alterClass(node, 'hide-widgets', !config.show);
      JX.Stratcom.invoke('resize');
    } else {
      config.show = 0;
    }
  }
  init();

  function _toggleColumn(e) {
    e.kill();
    var node = JX.$('conpherence-main-layout');
    config.show = !config.show;
    JX.DOM.alterClass(node, 'hide-widgets', !config.show);
    JX.Stratcom.invoke('resize');

    if (device != 'phone') {
      new JX.Request(config.settingsURI)
        .setData({value: (config.show ? 1 : 0)})
        .send();
    }
  }

  JX.Stratcom.listen(
    'click',
    'conpherence-widget-toggle',
    _toggleColumn);

});
