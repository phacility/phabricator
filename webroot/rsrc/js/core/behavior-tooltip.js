/**
 * @provides javelin-behavior-phabricator-tooltips
 * @requires javelin-behavior
 *           javelin-behavior-device
 *           javelin-stratcom
 *           phabricator-tooltip
 * @javelin
 */

JX.behavior('phabricator-tooltips', function() {

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

  function wipe() {
    JX.Tooltip.hide();
  }

  // Hide tips when any key is pressed. This prevents tips from ending up locked
  // on screen if you make a keypress which removes the underlying node (for
  // example, submitting an inline comment in Differential). See T4586.
  JX.Stratcom.listen('keydown', null, wipe);


  // Hide tips on mouseup. This removes tips on buttons in dialogs after the
  // buttons are clicked.
  JX.Stratcom.listen('mouseup', null, wipe);

  // When we leave the page, hide any visible tooltips. If we don't do this,
  // clicking a link with a tooltip and then hitting "back" will give you a
  // phantom tooltip.
  JX.Stratcom.listen('unload', null, wipe);

});
