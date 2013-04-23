/**
 * @provides javelin-behavior-phabricator-tooltips
 * @requires javelin-behavior
 *           javelin-behavior-device
 *           javelin-stratcom
 *           phabricator-tooltip
 * @javelin
 */

JX.behavior('phabricator-tooltips', function(config) {

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    'has-tooltip',
    function (e) {
      if (e.getType() == 'mouseout') {
        JX.Tooltip.hide();
        return;
      }

      if (JX.Device.getDevice() != 'desktop') {
        return;
      }

      var data = e.getNodeData('has-tooltip');

      JX.Tooltip.show(
        e.getNode('has-tooltip'),
        data.size || 120,
        data.align || 'N',
        data.tip);
    });

  // When we leave the page, hide any visible tooltips. If we don't do this,
  // clicking a link with a tooltip and then hitting "back" will give you a
  // phantom tooltip.
  JX.Stratcom.listen(
    'unload',
    null,
    function(e) {
      JX.Tooltip.hide();
    });

});
